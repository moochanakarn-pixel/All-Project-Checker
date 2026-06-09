<?php
ob_start();
require_once __DIR__ . '/config.php';

set_exception_handler(function ($e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
});

try {
    $conn = getDbConnection();

    // ── 1. ตรวจตารางมีอยู่มั้ย (case-insensitive ใช้ information_schema) ─────
    $tableCheck = $conn->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND LOWER(TABLE_NAME) = 'orderprocessdetail_displaystatusinqueue'
          LIMIT 1"
    );
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        $conn->close();
        jsonResponse(array(
            'success'        => false,
            'setup_required' => true,
            'error'          => 'ไม่พบตาราง OrderProcessDetail_DisplayStatusInQueue',
            'detail'         => 'ระบบ POS ยังไม่ได้อัพเดทฐานข้อมูลให้รองรับ Queue Display',
            'steps'          => array(
                'รัน SQL สร้างตาราง OrderProcessDetail_DisplayStatusInQueue ในฐานข้อมูล POS',
                'รัน SQL: INSERT INTO ProgramProperty / ProgramPropertyValue ตามสคริปต์ที่ได้รับ',
                'รัน SQL: UPDATE programpropertyvalue SET propertyvalue = 1 WHERE propertyid = 172',
                'Restart service POS หรือ reload หน้าจอแสดงผล',
            ),
        ));
    }

    // ── 2. ตรวจ feature property 172 ── แยก "ไม่มีแถว" vs "ปิดอยู่" ────────
    $propResult = $conn->query(
        "SELECT PropertyValue FROM programpropertyvalue WHERE PropertyID = 172 LIMIT 1"
    );
    if (!$propResult || $propResult->num_rows === 0) {
        $conn->close();
        jsonResponse(array(
            'success'        => false,
            'setup_required' => true,
            'error'          => 'ไม่พบ PropertyID 172 ในฐานข้อมูล',
            'detail'         => 'ยังไม่ได้รัน SQL script สำหรับ Queue Display feature',
            'steps'          => array(
                'รัน SQL: INSERT INTO ProgramProperty ตามสคริปต์ที่ได้รับ',
                'รัน SQL: INSERT INTO ProgramPropertyValue ตามสคริปต์ที่ได้รับ',
                'รัน SQL: UPDATE programpropertyvalue SET propertyvalue = 1 WHERE propertyid = 172',
                'Restart service POS หรือ reload หน้าจอแสดงผล',
            ),
        ));
    }
    $propValue = (int)$propResult->fetch_assoc()['PropertyValue'];
    if ($propValue !== 1) {
        $conn->close();
        jsonResponse(array(
            'success'        => false,
            'setup_required' => true,
            'error'          => 'Queue Display feature ยังไม่ได้เปิดใช้งาน (PropertyValue = ' . $propValue . ')',
            'detail'         => 'PropertyID 172 มีอยู่แล้วแต่ค่าเป็น ' . $propValue . ' ต้องตั้งเป็น 1',
            'steps'          => array(
                'เปิด Back Office → ระบบจัดการ → ตั้งค่าคอมพิวเตอร์',
                'เลือก Computer ที่เป็น KDS/Checker แล้วตั้ง Computer Type = Queue Terminal',
                'รัน SQL: UPDATE programpropertyvalue SET propertyvalue = 1 WHERE propertyid = 172',
                'Restart service POS หรือ reload หน้าจอแสดงผล',
            ),
        ));
    }

    // ── 3. ตรวจ Computer ID ─────────────────────────────────────────────────────
    $computerId = defined('QUEUE_COMPUTER_ID') ? (int)QUEUE_COMPUTER_ID : 0;
    if ($computerId <= 0) {
        $conn->close();
        jsonResponse(array(
            'success'        => false,
            'setup_required' => true,
            'error'          => 'ยังไม่ได้ตั้งค่า Computer ID',
            'detail'         => 'กรุณาเลือก Computer ที่เป็นจอแสดงคิวจากหน้าตั้งค่า',
            'steps'          => array(
                'กดมุมบนซ้ายของหน้าจอ 3 ครั้งเพื่อเข้าหน้าตั้งค่า',
                'ไปที่หัวข้อ "คอมพิวเตอร์จอแสดงคิว"',
                'กด "โหลดรายการ" แล้วเลือก Computer ที่ตรงกับจอนี้',
                'กด "บันทึกการตั้งค่า"',
            ),
        ));
    }

    // ── 4. ดึงชื่อ computer และชื่อร้าน ────────────────────────────────────────
    $computerName = '';
    $cnResult = $conn->query(
        "SELECT ComputerName FROM computername WHERE ComputerID = {$computerId} LIMIT 1"
    );
    if ($cnResult && $cnResult->num_rows > 0) {
        $computerName = trim((string)$cnResult->fetch_assoc()['ComputerName']);
    }

    $shopName = '';
    $snResult = $conn->query(
        "SELECT ProductLevelName FROM productlevel LIMIT 1"
    );
    if ($snResult && $snResult->num_rows > 0) {
        $shopName = trim((string)$snResult->fetch_assoc()['ProductLevelName']);
    }

    // ── 5. ดึงข้อมูล queue ────────────────────────────────────────────────────
    $readyMins = defined('READY_DISPLAY_MINUTES') ? (int)READY_DISPLAY_MINUTES : 40;
    // กรอง READY ที่เสร็จเกิน N นาทีออก (0 = แสดงทั้งวัน)
    // FinishTime IS NULL ต้องผ่านด้วย (READY แต่ยังไม่มีเวลาบันทึก)
    $readyTimeFilter = $readyMins > 0
        ? "AND (dsq.ProcessStatus = 0 OR dsq.FinishTime IS NULL OR dsq.FinishTime >= DATE_SUB(NOW(), INTERVAL {$readyMins} MINUTE))"
        : '';

    $sql = "
        SELECT
            dsq.TransactionID,
            dsq.ComputerID,
            dsq.ProcessStatus,
            dsq.IsNewStatus,
            dsq.SubmitOrderDateTime,
            dsq.FinishTime,
            TRIM(COALESCE(tr.QueueName, '')) AS QueueName
        FROM OrderProcessDetail_DisplayStatusInQueue dsq
        LEFT JOIN ordertransactionfront tr
            ON  tr.TransactionID = dsq.TransactionID
            AND tr.ComputerID    = dsq.ComputerID
        WHERE dsq.OrderDate = CURDATE()
          AND dsq.ProcessStatus IN (0, 1)
          {$readyTimeFilter}
        ORDER BY dsq.SubmitOrderDateTime ASC
    ";

    $result = $conn->query($sql);
    if (!$result) throw new Exception('Query error: ' . $conn->error);

    $preparing   = array();
    $ready       = array();
    $hasNewReady = false;

    while ($row = $result->fetch_assoc()) {
        $q = trim((string)$row['QueueName']);
        if ($q === '') {
            $q = str_pad((int)$row['TransactionID'], 4, '0', STR_PAD_LEFT);
        }

        if ((int)$row['ProcessStatus'] === 1) {
            if ((int)$row['IsNewStatus'] === 1) $hasNewReady = true;
            $ready[] = array(
                'q' => $q,
                't' => (string)($row['FinishTime'] ?: $row['SubmitOrderDateTime']),
            );
        } else {
            // ProcessStatus = 0 → PREPARING
            $preparing[] = array(
                'q' => $q,
                't' => (string)$row['SubmitOrderDateTime'],
            );
        }
    }
    $conn->close();

    // READY: เสร็จล่าสุดขึ้นก่อน
    usort($ready,     function ($a, $b) { return strcmp($b['t'], $a['t']); });
    // PREPARING: รอนานสุดขึ้นก่อน
    usort($preparing, function ($a, $b) { return strcmp($a['t'], $b['t']); });

    $readyLimit = defined('READY_LIMIT')     ? (int)READY_LIMIT     : 30;
    $prepLimit  = defined('PREPARING_LIMIT') ? (int)PREPARING_LIMIT : 30;
    $ready      = array_slice($ready,     0, $readyLimit);
    $preparing  = array_slice($preparing, 0, $prepLimit);

    jsonResponse(array(
        'success'         => true,
        'computer_name'   => $computerName,
        'shop_name'       => $shopName,
        'ready'           => array_column($ready,     'q'),
        'preparing'       => array_column($preparing, 'q'),
        'latest_ready'    => !empty($ready) ? $ready[0]['q'] : '',
        'latest_ready_at' => !empty($ready) ? $ready[0]['t'] : '',
        'has_new_ready'   => $hasNewReady,
    ));

} catch (Exception $e) {
    jsonResponse(array('success' => false, 'error' => $e->getMessage()));
}
