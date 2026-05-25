<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';
session_write_close();

function getEffectiveComputerId()
{
    static $cid = null;
    if ($cid === null) {
        $requested = isset($_REQUEST['cid']) ? (int)$_REQUEST['cid'] : 0;
        $cid = $requested > 0 ? $requested : (int)CURRENT_COMPUTER_ID;
    }
    return $cid;
}

function requestedMethod()
{
    return isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : 'GET';
}

function requestedAction()
{
    return isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : 'list';
}

function systemSettingsSnapshot()
{
    $local = getLocalSettings();
    $db = getDbConfig();
    return array(
        'db_host' => (string)localSetting($local, 'db_host', $db['host']),
        'db_port' => (int)localSetting($local, 'db_port', $db['port']),
        'db_name' => (string)localSetting($local, 'db_name', $db['name']),
        'current_computer_id' => (int)localSetting($local, 'current_computer_id', defined('CURRENT_COMPUTER_ID') ? CURRENT_COMPUTER_ID : 0),
        'current_computer_name' => (string)localSetting($local, 'current_computer_name', defined('CURRENT_COMPUTER_NAME') ? CURRENT_COMPUTER_NAME : ''),
        'finish_staff_id' => (int)localSetting($local, 'finish_staff_id', defined('DEFAULT_FINISH_STAFF_ID') ? DEFAULT_FINISH_STAFF_ID : 0),
        'threshold_yellow' => (int)localSetting($local, 'threshold_yellow', defined('ALERT_THRESHOLD_YELLOW_DEFAULT') ? ALERT_THRESHOLD_YELLOW_DEFAULT : 10),
        'threshold_red' => (int)localSetting($local, 'threshold_red', defined('ALERT_THRESHOLD_RED_DEFAULT') ? ALERT_THRESHOLD_RED_DEFAULT : 20),
        'sound_enabled' => !empty(localSetting($local, 'sound_enabled', defined('SOUND_ALERT_ENABLED_DEFAULT') ? SOUND_ALERT_ENABLED_DEFAULT : false)) ? 1 : 0,
        'barcode_camera_enabled' => !empty(localSetting($local, 'barcode_camera_enabled', defined('BARCODE_CAMERA_ENABLED_DEFAULT') ? BARCODE_CAMERA_ENABLED_DEFAULT : true)) ? 1 : 0,
        'kds_two_step_checkout' => !empty(localSetting($local, 'kds_two_step_checkout', defined('KDS_TWO_STEP_CHECKOUT_DEFAULT') ? KDS_TWO_STEP_CHECKOUT_DEFAULT : false)) ? 1 : 0,
    );
}

function connectWithSystemSettings($settings)
{
    $currentDb = getDbConfig();
    $db = normalizeDbConfig(array(
        'host' => $settings['db_host'],
        'port' => (int)$settings['db_port'],
        'name' => $settings['db_name'],
        'user' => $currentDb['user'],
        'pass' => $currentDb['pass'],
    ));

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], (int)$db['port']);
    if ($conn->connect_error) {
        throw new Exception('เชื่อมต่อไม่ผ่าน: ' . $conn->connect_error);
    }
    if (!$conn->set_charset('utf8')) {
        $error = $conn->error;
        $conn->close();
        throw new Exception('เชื่อมต่อผ่าน แต่ตั้งค่า charset ไม่สำเร็จ: ' . $error);
    }
    return $conn;
}

function lookupStaffDisplayNameByConnection($conn, $staffId)
{
    $staffId = (int)$staffId;
    if ($staffId <= 0) {
        return '';
    }

    $sql = "
        SELECT
            StaffID,
            COALESCE(NULLIF(TRIM(StaffCode), ''), '') AS StaffCode,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(StaffFirstName, ''), ' ', COALESCE(StaffLastName, ''))), ''), '') AS StaffName
        FROM staffs
        WHERE StaffID = ?
          AND Deleted = 0
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    $name = '';
    if ($result && ($row = $result->fetch_assoc())) {
        $parts = array();
        if (isset($row['StaffCode']) && trim((string)$row['StaffCode']) !== '') {
            $parts[] = trim((string)$row['StaffCode']);
        }
        if (isset($row['StaffName']) && trim((string)$row['StaffName']) !== '') {
            $parts[] = trim((string)$row['StaffName']);
        }
        if (!$parts) {
            $parts[] = 'Staff #' . $staffId;
        }
        $name = implode(' - ', $parts);
    }
    $stmt->close();
    return $name;
}

function handleGetSystemSettings()
{
    $settings = systemSettingsSnapshot();
    $staffName = '';
    $connectionMessage = '';
    try {
        $conn = connectWithSystemSettings($settings);
        $staffName = lookupStaffDisplayNameByConnection($conn, $settings['finish_staff_id']);
        $connectionMessage = 'เชื่อมต่อฐานข้อมูลปัจจุบันได้';
        $conn->close();
    } catch (Throwable $e) {
        $connectionMessage = $e->getMessage();
    }

    jsonResponse(array(
        'success' => true,
        'settings' => $settings,
        'staff_name' => $staffName,
        'connection_message' => $connectionMessage,
        'machine_display_name' => function_exists('getMachineDisplayName') ? getMachineDisplayName() : '',
    ));
}

function handleLookupStaffName()
{
    $snapshot = systemSettingsSnapshot();
    $staffId = isset($_REQUEST['staff_id']) ? (int)$_REQUEST['staff_id'] : 0;
    if ($staffId <= 0) {
        jsonResponse(array('success' => true, 'staff_name' => ''));
        return;
    }
    try {
        $conn = connectWithSystemSettings($snapshot);
        $staffName = lookupStaffDisplayNameByConnection($conn, $staffId);
        $conn->close();
        jsonResponse(array('success' => true, 'staff_name' => $staffName));
    } catch (Throwable $e) {
        jsonResponse(array('success' => false, 'error' => $e->getMessage()), 500);
    }
}

try {
    $method = requestedMethod();
    $action = requestedAction();

    // staff_display เป็น view-only — block ทุก write action
    $blockedActions = [
        'checkout_one', 'confirm_one', 'undo_one', 'resolve_status',
        'checkout_barcode', 'set_product_out_of_stock',
        'save_system_settings', 'test_system_settings_connection',
    ];
    if (in_array($action, $blockedActions, true)) {
        http_response_code(403);
        jsonResponse(['success' => false, 'error' => 'Not allowed on this display']);
        exit;
    }

    if ($method === 'GET' && $action === 'get_system_settings') {
        handleGetSystemSettings();
    }

    if ($method === 'GET' && $action === 'lookup_staff_name') {
        handleLookupStaffName();
    }

    $conn = getDbConnection();

    if ($method === 'POST' && $action === 'lookup_staff') {
        lookupStaff($conn);
    }

    if ($method === 'GET' && $action === 'list_serve_view') {
        listServeView($conn);
    }

    if ($method === 'GET' && $action === 'list_serve_table_orders') {
        listServeTableOrders($conn);
    }

    if ($method === 'GET' && $action === 'list_zones') {
        listZones($conn);
    }

    if ($method === 'GET' && $action === 'list_tables_in_zone') {
        listTablesInZone($conn);
    }

    if ($method === 'GET' && $action === 'list_table_orders') {
        listTableOrders($conn);
    }

    if ($method === 'GET' && $action === 'list_active') {
        listActiveData($conn);
    }

    if ($method === 'GET' && $action === 'list_finished') {
        listFinishedData($conn);
    }

    if ($method === 'GET' && $action === 'list_print_server_printers') {
        listPrintServerPrinters();
    }

    if ($method === 'GET' && $action === 'list_out_of_stock_products') {
        listOutOfStockProducts($conn);
    }

    if ($method === 'GET' && ($action === 'list' || $action === '')) {
        listData($conn);
    }

    jsonResponse(array(
        'success' => false,
        'error' => 'Unknown action',
    ), 400);
} catch (Throwable $e) {
    writeUsageLog('ERROR', ['action' => $action ?? '-', 'msg' => substr($e->getMessage(), 0, 200)]);
    jsonResponse(array(
        'success' => false,
        'error' => $e->getMessage(),
    ), 500);
}

function listData($conn)
{
    $overridePrintServerUrl = requestString('print_server_url', '');
    $activeRows = fetchActiveRows($conn);
    $finishedRows = fetchFinishedRows($conn);

    jsonResponse(array(
        'success' => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'stats' => buildStats($activeRows, $finishedRows),
        'active_rows' => $activeRows,
        'recent_finished_rows' => $finishedRows,
        'filters' => buildFilterInfo($conn, $overridePrintServerUrl),
    ));
}

function listZones($conn)
{
    $rows = fetchAllRows($conn, "SELECT ZoneID, ZoneName FROM tablezone WHERE (Deleted = 0 OR Deleted IS NULL) ORDER BY ZoneID ASC");
    jsonResponse(array('success' => true, 'zones' => $rows ?: array()));
}

function listTablesInZone($conn)
{
    $zoneId = requestInt('zone_id', 0);
    if ($zoneId <= 0) {
        jsonResponse(array('success' => false, 'error' => 'zone_id required'));
        return;
    }
    $stmt = $conn->prepare(
        "SELECT TableID, TableName FROM tableno WHERE ZoneID = ? AND (Deleted = 0 OR Deleted IS NULL) ORDER BY TableID ASC LIMIT 500"
    );
    if (!$stmt) {
        jsonResponse(array('success' => false, 'error' => $conn->error));
        return;
    }
    $stmt->bind_param('i', $zoneId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tables = array();
    while ($row = $result->fetch_assoc()) {
        $tables[] = array('TableID' => $row['TableID'], 'TableName' => $row['TableName']);
    }
    $stmt->close();
    jsonResponse(array('success' => true, 'zone_id' => $zoneId, 'tables' => $tables));
}

function listTableOrders($conn)
{
    $tableId       = requestString('table_id', '');
    $transactionId = requestInt('transaction_id', 0);
    $orderDate     = requestString('order_date', '');
    $sessionStart  = requestString('session_start', '');
    if ($tableId === '') {
        jsonResponse(array('success' => false, 'error' => 'table_id required'));
        return;
    }
    $cid = getEffectiveComputerId();
    writeUsageLog('TABLE_OPEN', ['table_id' => $tableId, 'cid' => $cid]);
    $rows            = fetchTableOrders($conn, $tableId, $transactionId, $orderDate, $sessionStart);
    $allowedPrinters = fetchAllowedPrinterIds($conn, $cid);
    jsonResponse(array(
        'success'             => true,
        'generated_at'        => date('Y-m-d H:i:s'),
        'table_id'            => $tableId,
        'allowed_printer_ids' => $allowedPrinters,
        'rows'                => $rows,
    ));
}

function fetchTableOrders($conn, $tableId, $transactionId = 0, $orderDate = '', $sessionStart = '')
{
    $selectCols = "
            opf.ProcessID,
            opf.SubProcessID,
            opf.TransactionID,
            opf.ComputerID,
            opf.OrderDetailID,
            opf.PrinterID,
            opf.IsMoveOrder,
            opf.ProductID,
            opf.ProductName,
            opf.ProductAmount,
            opf.ProductSetType,
            opf.ParentProcessID,
            opf.SubmitOrderDateTime,
            opf.FinishDateTime,
            opf.OrderNo,
            opf.OrderDate,
            opf.TableID,
            opf.DisplayTableName,
            opf.ProcessStatus,
            opf.SaleModeID,
            COALESCE(sm.SaleModeName, '-') AS SaleModeName,
            CASE
                WHEN EXISTS(
                     SELECT 1 FROM ordertransactionfront otf2
                     WHERE otf2.TransactionStatusID = 7
                       AND (
                           (opf.TransactionID > 0 AND otf2.TransactionID = opf.TransactionID)
                           OR
                           (opf.TransactionID = 0 AND otf2.TableID = opf.TableID)
                       )
                 )
                THEN 7
                ELSE 0
            END AS TransactionStatusID,
            CASE
                WHEN opf.TransactionID > 0 THEN 1
                ELSE 0
            END AS IsOldSession";
    $join = "LEFT JOIN salemode sm ON sm.SaleModeID = opf.SaleModeID AND sm.Deleted = 0";
    $order = "ORDER BY opf.SubmitOrderDateTime ASC, opf.ProcessID ASC, opf.SubProcessID ASC";

    // session_start กรองเฉพาะออเดอร์ของ session ปัจจุบัน (ไม่รวม session เก่าของลูกค้าก่อนหน้า)
    $sessionFilter = '';
    if ($sessionStart !== '' && $transactionId === 0) {
        $sessionFilter = ' AND opf.SubmitOrderDateTime >= ?';
    }

    // ตรวจประเภทของ table_id เพื่อเลือก WHERE ที่ถูกต้อง
    // "12"        → numeric TableID  → WHERE opf.TableID = 12
    // "sm6_t0"    → synthetic key    → WHERE opf.TableID = 0 AND opf.SaleModeID = 6
    // "otf123"    → OTF TransactionID → WHERE odf.TransactionID = 123 (JOIN orderdetailfront)
    // "LM111"     → DisplayTableName → WHERE opf.DisplayTableName = 'LM111'
    $tableIdInt  = is_numeric($tableId) ? (int)$tableId : -1;
    $saleModeId  = 0;
    $otfTxId     = 0;
    $displayName = '';
    if ($tableIdInt > 0) {
        $tableWhere   = 'opf.TableID = ?';
        $tableType    = 'i';
        $bindTableVal = $tableIdInt;
    } elseif (preg_match('/^sm(\d+)_t0$/', $tableId, $m)) {
        $saleModeId   = (int)$m[1];
        $tableWhere   = 'opf.TableID = 0 AND opf.SaleModeID = ?';
        $tableType    = 'i';
        $bindTableVal = $saleModeId;
    } elseif (preg_match('/^otf(\d+)$/', $tableId, $m)) {
        $otfTxId      = (int)$m[1];
        $tableWhere   = 'odf.TransactionID = ?';
        $tableType    = 'i';
        $bindTableVal = $otfTxId;
        $join .= "\nINNER JOIN (SELECT ComputerID, OrderDetailID, MAX(TransactionID) AS TransactionID FROM orderdetailfront GROUP BY ComputerID, OrderDetailID) odf"
               . "\n    ON odf.ComputerID = opf.ComputerID"
               . "\n   AND odf.OrderDetailID = opf.OrderDetailID";
    } else {
        $displayName  = $tableId;
        $tableWhere   = 'opf.DisplayTableName = ?';
        $tableType    = 's';
        $bindTableVal = $displayName;
    }

    if ($transactionId > 0) {
        $sql  = "SELECT $selectCols FROM orderprocessdetailfront opf $join WHERE $tableWhere AND opf.TransactionID = ? $order";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return array();
        $stmt->bind_param($tableType . 'i', $bindTableVal, $transactionId);
    } elseif ($orderDate !== '') {
        $sql  = "SELECT $selectCols FROM orderprocessdetailfront opf $join WHERE $tableWhere AND opf.OrderDate = ?$sessionFilter $order";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return array();
        if ($sessionFilter !== '') {
            $stmt->bind_param($tableType . 'ss', $bindTableVal, $orderDate, $sessionStart);
        } else {
            $stmt->bind_param($tableType . 's', $bindTableVal, $orderDate);
        }
    } else {
        $sql  = "SELECT $selectCols FROM orderprocessdetailfront opf $join WHERE $tableWhere AND opf.OrderDate = CURDATE()$sessionFilter $order";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return array();
        if ($sessionFilter !== '') {
            $stmt->bind_param($tableType . 's', $bindTableVal, $sessionStart);
        } else {
            $stmt->bind_param($tableType, $bindTableVal);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = array();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return attachCommentsToRows($conn, $rows);
}

function listActiveData($conn)
{
    $overridePrintServerUrl = requestString('print_server_url', '');
    $activeRows      = fetchActiveRows($conn);
    $allowedPrinters = fetchAllowedPrinterIds($conn, getEffectiveComputerId());

    jsonResponse(array(
        'success'             => true,
        'generated_at'        => date('Y-m-d H:i:s'),
        'stats'               => buildStats($activeRows, array()),
        'active_rows'         => $activeRows,
        'allowed_printer_ids' => $allowedPrinters,
        'filters'             => buildFilterInfo($conn, $overridePrintServerUrl),
    ));
}

function listFinishedData($conn)
{
    $overridePrintServerUrl = requestString('print_server_url', '');
    $finishedRows = fetchFinishedRows($conn);

    jsonResponse(array(
        'success' => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'recent_finished_rows' => $finishedRows,
        'filters' => buildFilterInfo($conn, $overridePrintServerUrl),
    ));
}

function listServeView($conn)
{
    $rows = fetchServeRows($conn);
    jsonResponse(array(
        'success'      => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'rows'         => $rows,
    ));
}

function listServeTableOrders($conn)
{
    $tableId     = requestString('table_id', '');
    $orderDate   = requestString('order_date', '');
    $transactionId = requestInt('transaction_id', 0);

    if ($tableId === '') {
        jsonResponse(array('success' => false, 'error' => 'table_id required'));
        return;
    }

    writeUsageLog('SERVE_TABLE_OPEN', ['table_id' => $tableId]);
    $rows = fetchServeTableOrders($conn, $tableId, $transactionId, $orderDate);
    jsonResponse(array(
        'success' => true,
        'rows'    => $rows,
    ));
}

function fetchServeRows($conn)
{
    $sql = "
        SELECT
            opf.ProcessID,
            opf.TableID,
            opf.DisplayTableName,
            opf.TransactionID,
            opf.OrderDate,
            opf.ProcessStatus,
            opf.IsMoveOrder,
            opf.ServingDateTime,
            opf.SubmitOrderDateTime
        FROM orderprocessdetailfront opf
        WHERE opf.ProcessStatus NOT IN (" . (int)PROCESS_STATUS_VOIDED . ")
          AND opf.OrderDate = CURDATE()
          AND opf.ProductSetType NOT IN (14, 15)
        ORDER BY opf.TableID ASC, opf.ProcessID ASC
    ";

    $rawRows = fetchAllRows($conn, $sql);

    // group by table
    $tables = array();
    foreach ($rawRows as $row) {
        $isMoved  = (int)$row['IsMoveOrder'] === 1 && strpos((string)$row['DisplayTableName'], '->') !== false;
        $dispName = (string)$row['DisplayTableName'];
        $tableId  = (string)$row['TableID'];

        if ($isMoved) {
            $parts   = explode('->', $dispName);
            $tableId = trim(end($parts));
            $dispName = $tableId;
        }

        $status = (int)$row['ProcessStatus'];
        $served = !empty($row['ServingDateTime']);

        if (!isset($tables[$tableId])) {
            $tables[$tableId] = array(
                'key'         => $tableId,
                'name'        => $dispName,
                'transaction_id' => (int)$row['TransactionID'],
                'order_date'  => (string)$row['OrderDate'],
                'cooking'     => 0,
                'ready'       => 0,
                'served'      => 0,
            );
        }

        $isDone = $status === (int)PROCESS_STATUS_FINISHED || $status === (int)PROCESS_STATUS_RESOLVED;

        if ($served) {
            $tables[$tableId]['served']++;
        } elseif ($isDone) {
            $tables[$tableId]['ready']++;
        } else {
            $tables[$tableId]['cooking']++;
        }
    }

    // คืนเฉพาะโต๊ะที่มีของพร้อมเสิร์ฟหรือยังทำอยู่ (ไม่เอาโต๊ะที่เสิร์ฟครบแล้วทั้งหมด)
    $result = array();
    foreach ($tables as $t) {
        if ($t['cooking'] > 0 || $t['ready'] > 0) {
            $result[] = $t;
        }
    }
    return array_values($result);
}

function fetchServeTableOrders($conn, $tableId, $transactionId = 0, $orderDate = '')
{
    $selectCols = "
            opf.ProcessID,
            opf.SubProcessID,
            opf.TransactionID,
            opf.PrinterID,
            opf.IsMoveOrder,
            opf.ProductID,
            opf.ProductName,
            opf.ProductAmount,
            opf.ProductSetType,
            opf.ParentProcessID,
            opf.SubmitOrderDateTime,
            opf.FinishDateTime,
            opf.ServingDateTime,
            opf.OrderNo,
            opf.OrderDate,
            opf.TableID,
            opf.DisplayTableName,
            opf.ProcessStatus,
            opf.SaleModeID,
            COALESCE(sm.SaleModeName, '-') AS SaleModeName";
    $join  = "LEFT JOIN salemode sm ON sm.SaleModeID = opf.SaleModeID AND sm.Deleted = 0";
    $order = "ORDER BY opf.SubmitOrderDateTime ASC, opf.ProcessID ASC, opf.SubProcessID ASC";

    // ตรวจประเภท table_id เหมือน fetchTableOrders
    $tableIdInt  = is_numeric($tableId) ? (int)$tableId : -1;
    $otfTxId     = 0;
    if ($tableIdInt > 0) {
        $tableWhere   = 'opf.TableID = ?';
        $tableType    = 'i';
        $bindTableVal = $tableIdInt;
    } elseif (preg_match('/^sm(\d+)_t0$/', $tableId, $m)) {
        $tableWhere   = 'opf.TableID = 0 AND opf.SaleModeID = ?';
        $tableType    = 'i';
        $bindTableVal = (int)$m[1];
    } elseif (preg_match('/^otf(\d+)$/', $tableId, $m)) {
        $otfTxId      = (int)$m[1];
        $tableWhere   = 'odf.TransactionID = ?';
        $tableType    = 'i';
        $bindTableVal = $otfTxId;
        $join .= "\nINNER JOIN (SELECT ComputerID, OrderDetailID, MAX(TransactionID) AS TransactionID FROM orderdetailfront GROUP BY ComputerID, OrderDetailID) odf"
               . "\n    ON odf.ComputerID = opf.ComputerID AND odf.OrderDetailID = opf.OrderDetailID";
    } else {
        $tableWhere   = 'opf.DisplayTableName = ?';
        $tableType    = 's';
        $bindTableVal = $tableId;
    }

    if ($transactionId > 0) {
        $sql  = "SELECT $selectCols FROM orderprocessdetailfront opf $join WHERE $tableWhere AND opf.TransactionID = ? $order";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return array();
        $stmt->bind_param($tableType . 'i', $bindTableVal, $transactionId);
    } elseif ($orderDate !== '') {
        $sql  = "SELECT $selectCols FROM orderprocessdetailfront opf $join WHERE $tableWhere AND opf.OrderDate = ? $order";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return array();
        $stmt->bind_param($tableType . 's', $bindTableVal, $orderDate);
    } else {
        $sql  = "SELECT $selectCols FROM orderprocessdetailfront opf $join WHERE $tableWhere AND opf.OrderDate = CURDATE() $order";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return array();
        $stmt->bind_param($tableType, $bindTableVal);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = array();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return attachCommentsToRows($conn, $rows);
}

function buildFilterInfo($conn = null, $overridePrintServerUrl = '')
{
    $displayPrinters = array();
    if ($conn instanceof mysqli) {
        $displayPrinters = fetchAvailablePrinters($conn, getEffectiveComputerId());
    }
    $normalizedPrintServerUrl = normalizePrintServerBaseUrl($overridePrintServerUrl);
    $checkoutPrinters = resolveCheckoutPrinterOptions($conn, $normalizedPrintServerUrl);

    return array(
        'active_today_only' => (bool)ACTIVE_ROWS_TODAY_ONLY,
        'finished_today_only' => (bool)FINISHED_ROWS_TODAY_ONLY,
        'current_computer_id' => getEffectiveComputerId(),
        'allowed_printer_ids' => array_values(array_map('intval', array_column($displayPrinters, 'printer_id'))),
        'available_printers' => $checkoutPrinters,
        'display_printers' => $displayPrinters,
        'default_checkout_printer_name' => (string)DEFAULT_CHECKOUT_PRINTER_NAME,
        'allow_checkout_printer_selection' => (bool)ALLOW_CHECKOUT_PRINTER_SELECTION,
        'checkout_print_provider' => (string)CHECKOUT_PRINT_PROVIDER,
        'print_server_url' => $normalizedPrintServerUrl,
    );
}

function resolveCheckoutPrinterOptions($conn = null, $overridePrintServerUrl = '')
{
    $provider = defined('CHECKOUT_PRINT_PROVIDER') ? strtolower(trim((string)CHECKOUT_PRINT_PROVIDER)) : 'none';

    if ($provider === 'print_server') {
        return fetchPrintServerPrinters($overridePrintServerUrl, true);
    }

    if ($provider === 'queue' && $conn instanceof mysqli) {
        return fetchAvailablePrinters($conn, getEffectiveComputerId());
    }

    return array();
}

function listPrintServerPrinters()
{
    $overridePrintServerUrl = requestString('print_server_url', '');
    $normalizedPrintServerUrl = normalizePrintServerBaseUrl($overridePrintServerUrl);
    $printers = fetchPrintServerPrinters($normalizedPrintServerUrl, false);

    jsonResponse(array(
        'success' => true,
        'print_server_url' => $normalizedPrintServerUrl,
        'printers' => $printers,
    ));
}

function fetchPrintServerPrinters($overrideBase = '', $silent = true)
{
    static $cache = array();

    $normalizedBase = normalizePrintServerBaseUrl($overrideBase);
    if ($normalizedBase === '') {
        return array();
    }

    $cacheKey = $normalizedBase;
    if ($silent && isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $printers = array();
    $url = buildPrintServerEndpoint('printers', $normalizedBase);
    if ($url === '') {
        return $printers;
    }

    try {
        $response = performJsonHttpRequest($url, 'GET');
        $items = array();
        if (isset($response['printers']) && is_array($response['printers'])) {
            $items = $response['printers'];
        } elseif (isset($response[0])) {
            $items = $response;
        }

        foreach ($items as $item) {
            $printerName = isset($item['printer_name']) ? trim((string)$item['printer_name']) : '';
            if ($printerName === '' && isset($item['name'])) {
                $printerName = trim((string)$item['name']);
            }
            if ($printerName === '') {
                continue;
            }

            $printers[] = array(
                'printer_name' => $printerName,
                'printer_label' => isset($item['printer_label']) && trim((string)$item['printer_label']) !== ''
                    ? trim((string)$item['printer_label'])
                    : $printerName,
                'is_default' => !empty($item['is_default']) ? 1 : 0,
                'driver_name' => isset($item['driver_name']) ? trim((string)$item['driver_name']) : '',
                'port_name' => isset($item['port_name']) ? trim((string)$item['port_name']) : '',
                'source' => 'print_server',
            );
        }

        $cache[$cacheKey] = $printers;
    } catch (Throwable $e) {
        if ($silent) {
            $cache[$cacheKey] = array();
            return array();
        }
        throw $e;
    }

    return $printers;
}

function normalizePrintServerBaseUrl($overrideBase = '')
{
    $base = trim((string)$overrideBase);
    if ($base === '') {
        $base = defined('PRINT_SERVER_URL') ? trim((string)PRINT_SERVER_URL) : '';
    }
    if ($base === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $base)) {
        $base = 'http://' . $base;
    }

    $parts = @parse_url($base);
    if (!is_array($parts) || empty($parts['host'])) {
        return $base;
    }

    $scheme = isset($parts['scheme']) ? strtolower((string)$parts['scheme']) : 'http';
    $host = (string)$parts['host'];
    $port = isset($parts['port']) ? (int)$parts['port'] : 0;
    $path = isset($parts['path']) ? (string)$parts['path'] : '';
    $query = isset($parts['query']) ? (string)$parts['query'] : '';

    if ($path === '' || $path === '/') {
        if ($port <= 0) {
            $port = 5001;
        }
        $path = '/print_server.php';
    }

    $normalized = $scheme . '://' . $host;
    if ($port > 0) {
        $normalized .= ':' . $port;
    }
    $normalized .= $path;
    if ($query !== '') {
        $normalized .= '?' . $query;
    }

    return $normalized;
}

function buildPrintServerEndpoint($action, $overrideBase = '')
{
    $base = normalizePrintServerBaseUrl($overrideBase);
    if ($base === '') {
        return '';
    }

    $separator = (strpos($base, '?') === false) ? '?' : '&';
    return $base . $separator . 'action=' . rawurlencode((string)$action);
}

function performJsonHttpRequest($url, $method, $payload = null)
{
    $method = strtoupper((string)$method);
    $headers = array('Accept: application/json');
    $token = defined('PRINT_SERVER_SHARED_TOKEN') ? trim((string)PRINT_SERVER_SHARED_TOKEN) : '';
    if ($token !== '') {
        $headers[] = 'X-Print-Server-Token: ' . $token;
    }

    $body = null;
    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new Exception('ไม่สามารถสร้าง JSON สำหรับ Print Server ได้');
        }
        $headers[] = 'Content-Type: application/json; charset=utf-8';
    }

    $timeout = defined('PRINT_SERVER_TIMEOUT_SECONDS') ? max(1, (int)PRINT_SERVER_TIMEOUT_SECONDS) : 4;
    $responseBody = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('ติดต่อ Print Server ไม่สำเร็จ: ' . $err);
        }
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create(array(
            'http' => array(
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body !== null ? $body : '',
                'timeout' => $timeout,
                'ignore_errors' => true,
            ),
        ));
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new Exception('ติดต่อ Print Server ไม่สำเร็จ');
        }
        global $http_response_header;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $statusCode = (int)$m[1];
        }
    }

    $data = json_decode((string)$responseBody, true);
    if (!is_array($data)) {
        throw new Exception('Print Server ส่งข้อมูลไม่ใช่ JSON');
    }

    if ($statusCode >= 400 || (isset($data['success']) && !$data['success'])) {
        $message = isset($data['error']) ? (string)$data['error'] : ('Print Server ตอบกลับไม่สำเร็จ (' . $statusCode . ')');
        throw new Exception($message);
    }

    return $data;
}

function fetchAvailablePrinters($conn, $computerId)
{
    static $cache = array();

    $computerId = (int)$computerId;
    if ($computerId <= 0) {
        return array();
    }

    if (isset($cache[$computerId])) {
        return $cache[$computerId];
    }

    $sql = "
        SELECT DISTINCT
            cap.PrinterID,
            COALESCE(NULLIF(TRIM(p.PrinterName), ''), CONCAT('Printer #', cap.PrinterID)) AS PrinterName,
            COALESCE(p.PrinterDeviceName, '') AS PrinterDeviceName
        FROM checkeraccessprinter cap
        LEFT JOIN printers p
            ON p.PrinterID = cap.PrinterID
           AND p.Deleted = 0
        WHERE cap.ComputerID = ?
        ORDER BY cap.PrinterID ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $computerId);
    $stmt->execute();
    $result = $stmt->get_result();

    $printers = array();
    while ($result && ($row = $result->fetch_assoc())) {
        $printerId = isset($row['PrinterID']) ? (int)$row['PrinterID'] : 0;
        if ($printerId <= 0) {
            continue;
        }

        $printers[] = array(
            'printer_id' => $printerId,
            'printer_name' => isset($row['PrinterName']) ? trim((string)$row['PrinterName']) : ('Printer #' . $printerId),
            'printer_device_name' => isset($row['PrinterDeviceName']) ? trim((string)$row['PrinterDeviceName']) : '',
        );
    }
    $stmt->close();

    $cache[$computerId] = $printers;
    return $printers;
}

function fetchAllowedPrinterIds($conn, $computerId)
{
    $printers = fetchAvailablePrinters($conn, $computerId);
    $ids = array();
    foreach ($printers as $printer) {
        $printerId = isset($printer['printer_id']) ? (int)$printer['printer_id'] : 0;
        if ($printerId > 0) {
            $ids[$printerId] = $printerId;
        }
    }

    return array_values($ids);
}

function appendAllowedPrinterFilter(array &$where, array $allowedPrinterIds, $alias)
{
    // ถ้าไม่มี printer config สำหรับ computer นี้ = ไม่กรอง (แสดงทุก order)
    if (!$allowedPrinterIds) {
        return;
    }

    $safeIds = array();
    foreach ($allowedPrinterIds as $printerId) {
        $printerId = (int)$printerId;
        if ($printerId > 0) {
            $safeIds[] = $printerId;
        }
    }

    if (!$safeIds) {
        return;
    }

    $where[] = $alias . '.PrinterID IN (' . implode(', ', $safeIds) . ')';
}

function buildStats($activeRows, $finishedRows)
{
    $activeCount = count($activeRows);
    $activeQty = 0;
    foreach ($activeRows as $row) {
        $activeQty += (float)$row['ProductAmount'];
    }

    return array(
        'active_rows' => $activeCount,
        'active_qty' => $activeQty,
        'recent_finished_rows' => count($finishedRows),
    );
}

function fetchActiveRows($conn)
{
    $allowedPrinterIds = fetchAllowedPrinterIds($conn, getEffectiveComputerId());

    // รวม voided/deleted (98) ด้วยเพื่อแสดงสีเทา
    $statusList = implode(', ', array(
        (int)PROCESS_STATUS_ACTIVE,
        (int)PROCESS_STATUS_IN_PROCESS,
        (int)PROCESS_STATUS_VOIDED
    ));
    $where = array('opf.ProcessStatus IN (' . $statusList . ')');
    appendAllowedPrinterFilter($where, $allowedPrinterIds, 'opf');
    if (ACTIVE_ROWS_TODAY_ONLY) {
        $where[] = 'opf.OrderDate = CURDATE()';
    }

    $activeSql = "
        SELECT
            opf.ProductLevelID,
            opf.ProcessID,
            opf.SubProcessID,
            opf.PrinterID,
            opf.TransactionID,
            opf.ComputerID,
            opf.OrderDetailID,
            opf.ProductID,
            opf.ProductName,
            opf.ProductAmount,
            opf.ProductSetType,
            opf.ParentProcessID,
            opf.SubmitOrderDateTime,
            opf.FinishDateTime,
            opf.OrderNo,
            opf.OrderDate,
            opf.TableID,
            opf.DisplayTableName,
            opf.ProcessStatus,
            opf.IsMoveOrder,
            opf.SaleModeID,
            COALESCE(sm.SaleModeName, '-') AS SaleModeName,
            COALESCE(odf.TransactionID, 0) AS OtfTransactionID,
            COALESCE(otf.QueueName, '') AS QueueName,
            CASE
                WHEN EXISTS(
                     SELECT 1 FROM ordertransactionfront otf2
                     WHERE otf2.TransactionStatusID = 7
                       AND (
                           (opf.TransactionID > 0 AND otf2.TransactionID = opf.TransactionID)
                           OR
                           (opf.TransactionID = 0 AND otf2.TableID = opf.TableID)
                       )
                 )
                THEN 7
                ELSE 0
            END AS TransactionStatusID,
            CASE
                WHEN opf.TransactionID > 0 THEN 1
                ELSE 0
            END AS IsOldSession
        FROM orderprocessdetailfront opf
        LEFT JOIN salemode sm
            ON sm.SaleModeID = opf.SaleModeID
           AND sm.Deleted = 0
        LEFT JOIN (
            SELECT ComputerID, OrderDetailID, MAX(TransactionID) AS TransactionID
            FROM orderdetailfront
            GROUP BY ComputerID, OrderDetailID
        ) odf
            ON odf.ComputerID    = opf.ComputerID
           AND odf.OrderDetailID = opf.OrderDetailID
        LEFT JOIN ordertransactionfront otf
            ON otf.TransactionID = odf.TransactionID
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
            opf.SubmitOrderDateTime ASC,
            opf.ProcessID ASC,
            opf.SubProcessID ASC,
            opf.PrinterID ASC
    ";

    $rows = fetchAllRows($conn, $activeSql);
    return attachCommentsToRows($conn, $rows);
}

function fetchFinishedRows($conn)
{
    $allowedPrinterIds = fetchAllowedPrinterIds($conn, getEffectiveComputerId());

    $where = array('opf.ProcessStatus = ' . (int)PROCESS_STATUS_FINISHED);
    appendAllowedPrinterFilter($where, $allowedPrinterIds, 'opf');
    if (FINISHED_ROWS_TODAY_ONLY) {
        $where[] = "(opf.OrderDate = CURDATE() OR (opf.FinishDateTime >= CURDATE() AND opf.FinishDateTime < DATE_ADD(CURDATE(), INTERVAL 1 DAY)))";
    }

    $finishedSql = "
        SELECT
            opf.ProductLevelID,
            opf.ProcessID,
            opf.SubProcessID,
            opf.PrinterID,
            opf.TransactionID,
            opf.ComputerID,
            opf.OrderDetailID,
            opf.ProductID,
            opf.ProductName,
            opf.ProductAmount,
            opf.ProductSetType,
            opf.ParentProcessID,
            opf.SubmitOrderDateTime,
            opf.FinishDateTime,
            opf.OrderNo,
            opf.OrderDate,
            opf.TableID,
            opf.DisplayTableName,
            opf.ProcessStatus,
            opf.IsMoveOrder,
            opf.SaleModeID,
            opf.FinishStaffID,
            COALESCE(sm.SaleModeName, '-') AS SaleModeName,
            COALESCE(odf.TransactionID, 0) AS OtfTransactionID,
            COALESCE(otf.QueueName, '') AS QueueName,
            CASE
                WHEN EXISTS(
                     SELECT 1 FROM ordertransactionfront otf2
                     WHERE otf2.TransactionStatusID = 7
                       AND (
                           (opf.TransactionID > 0 AND otf2.TransactionID = opf.TransactionID)
                           OR
                           (opf.TransactionID = 0 AND otf2.TableID = opf.TableID)
                       )
                 )
                THEN 7
                ELSE 0
            END AS TransactionStatusID,
            CASE
                WHEN opf.TransactionID > 0 THEN 1
                ELSE 0
            END AS IsOldSession
        FROM orderprocessdetailfront opf
        LEFT JOIN salemode sm
            ON sm.SaleModeID = opf.SaleModeID
           AND sm.Deleted = 0
        LEFT JOIN (
            SELECT ComputerID, OrderDetailID, MAX(TransactionID) AS TransactionID
            FROM orderdetailfront
            GROUP BY ComputerID, OrderDetailID
        ) odf
            ON odf.ComputerID    = opf.ComputerID
           AND odf.OrderDetailID = opf.OrderDetailID
        LEFT JOIN ordertransactionfront otf
            ON otf.TransactionID = odf.TransactionID
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
            opf.FinishDateTime DESC,
            opf.ProcessID DESC,
            opf.SubProcessID DESC
    ";

    if ((int)RECENT_FINISHED_LIMIT > 0) {
        $finishedSql .= " LIMIT " . (int)RECENT_FINISHED_LIMIT;
    }

    $rows = fetchAllRows($conn, $finishedSql);
    return attachCommentsToRows($conn, $rows);
}

function attachCommentsToRows($conn, $rows)
{
    if (!$rows) {
        return array();
    }

    // คำนวณ flag สถานะพิเศษแต่ละ row
    foreach ($rows as &$row) {
        $status     = isset($row['ProcessStatus'])      ? (int)$row['ProcessStatus']      : 0;
        $isMoved    = isset($row['IsMoveOrder'])         ? (int)$row['IsMoveOrder']         : 0;
        $txStatus   = isset($row['TransactionStatusID']) ? (int)$row['TransactionStatusID'] : 0;
        $oldSession = isset($row['IsOldSession'])        ? (int)$row['IsOldSession']        : 0;
        $dispName   = isset($row['DisplayTableName'])    ? trim((string)$row['DisplayTableName']) : '';

        $row['is_voided']      = ($status === (int)PROCESS_STATUS_VOIDED);
        $row['is_moved']       = ($isMoved === 1 && strpos($dispName, '->') !== false);
        $row['is_combined']    = (!$row['is_voided'] && !$row['is_moved'] && $txStatus === 7);
        $row['is_old_session'] = (!$row['is_voided'] && !$row['is_moved'] && !$row['is_combined'] && $oldSession === 1);

        // ปลายทางของ move: '2->4' → '4'
        $row['moved_to'] = '';
        if ($row['is_moved'] && strpos($dispName, '->') !== false) {
            $parts = explode('->', $dispName);
            $row['moved_to'] = trim(end($parts));
        }

        $row['comments'] = array();
    }
    unset($row);

    // ดึง comment โดยอิง ProcessID เป็นหลัก (ตรงกับ kds_allcomment)
    $processIds = array();
    foreach ($rows as $row) {
        $processId = isset($row['ProcessID']) ? (int)$row['ProcessID'] : 0;
        $parentProcessId = isset($row['ParentProcessID']) ? (int)$row['ParentProcessID'] : 0;
        if ($processId > 0) {
            $processIds[$processId] = true;
        }
        if ($parentProcessId > 0) {
            $processIds[$parentProcessId] = true;
        }
    }
    $commentsMap = $processIds ? fetchCommentsByProcessIds($conn, array_keys($processIds)) : array();

    foreach ($rows as &$row) {
        $processId = isset($row['ProcessID']) ? (int)$row['ProcessID'] : 0;
        $parentProcessId = isset($row['ParentProcessID']) ? (int)$row['ParentProcessID'] : 0;

        if ($processId > 0 && isset($commentsMap[$processId])) {
            $row['comments'] = array_values($commentsMap[$processId]);
        } elseif ($parentProcessId > 0 && isset($commentsMap[$parentProcessId])) {
            $row['comments'] = array_values($commentsMap[$parentProcessId]);
        } else {
            $row['comments'] = array();
        }
    }
    unset($row);

    return mergeChildProcessRowsIntoParents($rows);
}

function mergeChildProcessRowsIntoParents($rows)
{
    if (!$rows) {
        return array();
    }

    $parentIndexMap = array();
    foreach ($rows as $index => $row) {
        $parentIndexMap[makeProcessRowMapKey($row)] = $index;
        if (!isset($rows[$index]['comments']) || !is_array($rows[$index]['comments'])) {
            $rows[$index]['comments'] = array();
        }
        if (!isset($rows[$index]['parent_name'])) {
            $rows[$index]['parent_name'] = null;
        }
    }

    $hiddenParents   = array();
    $hiddenChildren  = array();
    $insertsByParent = array();

    foreach ($rows as $index => $row) {
        $parentProcessId = isset($row['ParentProcessID']) ? (int)$row['ParentProcessID'] : 0;
        $productSetType  = isset($row['ProductSetType'])  ? (int)$row['ProductSetType']  : 0;

        if ($parentProcessId <= 0) continue;

        $parentKey = makeParentLookupKey($row, $parentProcessId);
        if (!isset($parentIndexMap[$parentKey])) continue;

        $parentIndex = $parentIndexMap[$parentKey];
        $parentRow   = $rows[$parentIndex];

        if (in_array($productSetType, array(14, 15), true)) {
            // comment / เพิ่มราคา → merge เข้า comments[] ของ parent
            $rows[$parentIndex]['comments'] = appendProcessRowAsComment($rows[$parentIndex]['comments'], $row);
            $hiddenChildren[$index] = true;
        } else {
            // สินค้าชุด (SETA) → การ์ดแยกพร้อม parent_name + inherit status จาก parent
            $newCard                        = $row;
            $newCard['parent_name']         = trim((string)(isset($parentRow['ProductName']) ? $parentRow['ProductName'] : ''));
            $newCard['comments']            = array();
            $newCard['TableID']             = $parentRow['TableID'];
            $newCard['DisplayTableName']    = $parentRow['DisplayTableName'];
            $newCard['OrderNo']             = $parentRow['OrderNo'];
            $newCard['SaleModeID']          = $parentRow['SaleModeID'];
            $newCard['SaleModeName']        = isset($parentRow['SaleModeName']) ? $parentRow['SaleModeName'] : '-';
            $newCard['OtfTransactionID']    = isset($parentRow['OtfTransactionID']) ? $parentRow['OtfTransactionID'] : 0;
            $newCard['QueueName']           = isset($parentRow['QueueName']) ? $parentRow['QueueName'] : '';
            $newCard['SubmitOrderDateTime'] = $parentRow['SubmitOrderDateTime'];
            // inherit flags พิเศษจาก parent
            if (!empty($parentRow['is_voided']))      $newCard['is_voided']      = true;
            if (!empty($parentRow['is_moved']))        { $newCard['is_moved']     = true; $newCard['moved_to'] = $parentRow['moved_to']; }
            if (!empty($parentRow['is_combined']))     $newCard['is_combined']    = true;
            if (!empty($parentRow['is_old_session']))  $newCard['is_old_session'] = true;

            $insertsByParent[$parentIndex][] = $newCard;
            $hiddenParents[$parentIndex]     = true;
            $hiddenChildren[$index]          = true;
        }
    }

    $visibleRows = array();
    foreach ($rows as $index => $row) {
        if (isset($hiddenChildren[$index])) continue;
        if (isset($hiddenParents[$index])) {
            if (isset($insertsByParent[$index])) {
                foreach ($insertsByParent[$index] as $card) {
                    $visibleRows[] = $card;
                }
            }
            continue;
        }
        $visibleRows[] = $row;
    }

    return $visibleRows;
}

function makeProcessRowMapKey($row)
{
    return (int)(isset($row['ProductLevelID']) ? $row['ProductLevelID'] : 0)
        . '|' . (int)(isset($row['ProcessID']) ? $row['ProcessID'] : 0)
        . '|' . (int)(isset($row['PrinterID']) ? $row['PrinterID'] : 0);
}

function makeParentLookupKey($row, $parentProcessId)
{
    return (int)(isset($row['ProductLevelID']) ? $row['ProductLevelID'] : 0)
        . '|' . (int)$parentProcessId
        . '|' . (int)(isset($row['PrinterID']) ? $row['PrinterID'] : 0);
}

function appendProcessRowAsComment($comments, $row)
{
    $comments = is_array($comments) ? array_values($comments) : array();
    $comment = array(
        'text' => trim((string)(isset($row['ProductName']) ? $row['ProductName'] : '')),
        'amount' => isset($row['ProductAmount']) ? (float)$row['ProductAmount'] : 0,
        'type' => isset($row['ProductSetType']) ? (int)$row['ProductSetType'] : 0,
        'label' => commentTypeLabel(isset($row['ProductSetType']) ? (int)$row['ProductSetType'] : 0),
        'is_priced' => ((int)(isset($row['ProductSetType']) ? $row['ProductSetType'] : 0) === 15),
        'is_free_text' => false,
    );

    if ($comment['text'] === '') {
        return $comments;
    }

    $dedupeKey = $comment['type'] . '|' . $comment['text'] . '|' . toDecimalString($comment['amount'], 2);
    $existing = array();
    foreach ($comments as $item) {
        $existingKey = (int)(isset($item['type']) ? $item['type'] : 0)
            . '|' . trim((string)(isset($item['text']) ? $item['text'] : ''))
            . '|' . toDecimalString(isset($item['amount']) ? (float)$item['amount'] : 0, 2);
        $existing[$existingKey] = true;
    }

    if (!isset($existing[$dedupeKey])) {
        $comments[] = $comment;
    }

    return $comments;
}

function fetchCommentsByProcessIds($conn, $processIds)
{
    if (!$processIds) {
        return array();
    }

    $processIds = array_values(array_unique(array_map('intval', $processIds)));
    $processIds = array_values(array_filter($processIds, function ($value) {
        return $value > 0;
    }));
    if (!$processIds) {
        return array();
    }

    $placeholders = implode(', ', array_fill(0, count($processIds), '?'));
    $sql = "
        SELECT
            c.ProcessID AS ProcessID,
            c.OrderComment,
            c.CommentAmount,
            c.CommentSetType
        FROM (" . getKdsAllCommentSql() . ") c
        WHERE c.ProcessID IN (" . $placeholders . ")
          AND c.ProcessID <> 0
          AND c.OrderComment IS NOT NULL
          AND c.OrderComment <> ''
        ORDER BY
            c.ProcessID ASC,
            CASE
                WHEN c.CommentSetType = 15 THEN 2
                ELSE 1
            END ASC,
            c.OrderComment ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return array();
    }

    $types = str_repeat('i', count($processIds));
    $bindValues = $processIds;
    $bindParams = array($types);
    foreach ($bindValues as $index => $value) {
        $bindParams[] = &$bindValues[$index];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);

    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        return array();
    }

    $map = array();
    while ($dbRow = $result->fetch_assoc()) {
        $comment = normalizeCommentRow(array(
            'OrderComment'   => $dbRow['OrderComment'],
            'CommentAmount'  => $dbRow['CommentAmount'],
            'CommentSetType' => $dbRow['CommentSetType'],
        ));
        if (!$comment) {
            continue;
        }

        $processId = (int)$dbRow['ProcessID'];
        $dedupeKey = $comment['type'] . '|' . $comment['text'] . '|' . toDecimalString($comment['amount'], 2);

        if (!isset($map[$processId])) {
            $map[$processId] = array();
        }
        if (!isset($map[$processId][$dedupeKey])) {
            $map[$processId][$dedupeKey] = $comment;
        }
    }
    $stmt->close();

    return $map;
}

function fetchCommentsByRowKeys($conn, $rowKeys)
{
    if (!$rowKeys) {
        return array();
    }

    $conditions = array();
    foreach ($rowKeys as $rowKey) {
        $transactionId = isset($rowKey['TransactionID']) ? (int)$rowKey['TransactionID'] : 0;
        $computerId = isset($rowKey['ComputerID']) ? (int)$rowKey['ComputerID'] : 0;
        $orderDetailId = isset($rowKey['OrderDetailID']) ? (int)$rowKey['OrderDetailID'] : 0;
        if ($transactionId > 0 && $computerId > 0 && $orderDetailId > 0) {
            $conditions[] = sprintf('(c.TransactionID = %d AND c.ComputerID = %d AND c.OrderDetailID = %d)', $transactionId, $computerId, $orderDetailId);
        }
    }

    if (!$conditions) {
        return array();
    }

    $sql = "
        SELECT
            c.TransactionID,
            c.ComputerID,
            c.OrderDetailID,
            c.OrderComment,
            c.CommentAmount,
            c.CommentSetType
        FROM (" . getKdsAllCommentSql() . " ) c
        WHERE (" . implode(' OR ', $conditions) . ")
          AND c.OrderComment IS NOT NULL
          AND c.OrderComment <> ''
        ORDER BY
            c.TransactionID ASC,
            c.ComputerID ASC,
            c.OrderDetailID ASC,
            CASE
                WHEN c.CommentSetType = 15 THEN 2
                ELSE 1
            END ASC,
            c.OrderComment ASC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return array();
    }

    $map = array();
    while ($row = $result->fetch_assoc()) {
        $comment = normalizeCommentRow($row);
        if (!$comment) {
            continue;
        }

        $key = (int)$row['TransactionID'] . '|' . (int)$row['ComputerID'] . '|' . (int)$row['OrderDetailID'];
        $dedupeKey = $comment['type'] . '|' . $comment['text'] . '|' . toDecimalString($comment['amount'], 2);

        if (!isset($map[$key])) {
            $map[$key] = array();
        }
        $map[$key][$dedupeKey] = $comment;
    }

    return $map;
}

function normalizeCommentRow($row)
{
    $text = trim((string)(isset($row['OrderComment']) ? $row['OrderComment'] : ''));
    if ($text === '') {
        return null;
    }

    $type = isset($row['CommentSetType']) ? (int)$row['CommentSetType'] : 0;
    $amount = isset($row['CommentAmount']) ? (float)$row['CommentAmount'] : 1;

    return array(
        'text' => $text,
        'amount' => $amount,
        'type' => $type,
        'label' => commentTypeLabel($type),
        'is_priced' => ($type === 15),
        'is_free_text' => ($type === 0),
    );
}

function commentTypeLabel($type)
{
    return ((int)$type === 15) ? 'คอมเมนต์เพิ่มราคา' : 'คอมเมนต์';
}

function getKdsAllCommentSql()
{
    return "
        SELECT
            od.TransactionID AS TransactionID,
            od.ComputerID AS ComputerID,
            od.OrderDetailID AS OrderDetailID,
            od.ProcessID AS ProcessID,
            p.ProductName AS OrderComment,
            oc.Amount AS CommentAmount,
            oc.ProductSetType AS CommentSetType
        FROM orderdetailfront od
        INNER JOIN ordercommentlinkfront oc
            ON od.TransactionID = oc.TransactionID
           AND od.ComputerID = oc.ComputerID
           AND od.OrderDetailID = oc.CommentForOrderID
        INNER JOIN products p
            ON oc.ProductID = p.ProductID
        WHERE od.ProcessID <> 0

        UNION ALL

        SELECT
            od.TransactionID AS TransactionID,
            od.ComputerID AS ComputerID,
            od.OrderDetailID AS OrderDetailID,
            od.ProcessID AS ProcessID,
            od.Comment AS OrderComment,
            1 AS CommentAmount,
            0 AS CommentSetType
        FROM orderdetailfront od
        WHERE od.Comment IS NOT NULL
          AND od.Comment <> ''
          AND od.ProcessID <> 0

        UNION ALL

        SELECT
            op.TransactionID AS TransactionID,
            op.ComputerID AS ComputerID,
            op.OrderDetailID AS OrderDetailID,
            od.ProcessID AS ProcessID,
            od.Comment AS OrderComment,
            1 AS CommentAmount,
            0 AS CommentSetType
        FROM orderprocessdetailfront op
        INNER JOIN orderdetail od
            ON od.TransactionID = op.TransactionID
           AND od.ComputerID = op.ComputerID
           AND od.OrderDetailID = op.OrderDetailID
        WHERE od.Comment IS NOT NULL
          AND od.Comment <> ''
          AND op.TransactionID <> 0
          AND op.ComputerID <> 0
          AND op.OrderDetailID <> 0
          AND od.ProcessID <> 0

        UNION ALL

        SELECT
            op.TransactionID AS TransactionID,
            op.ComputerID AS ComputerID,
            op.OrderDetailID AS OrderDetailID,
            op.ProcessID AS ProcessID,
            p.ProductName AS OrderComment,
            oc.Amount AS CommentAmount,
            14 AS CommentSetType
        FROM orderprocessdetailfront op
        INNER JOIN ordercommentdetail oc
            ON op.OrderDetailID = oc.OrderDetailID
           AND op.TransactionID = oc.TransactionID
           AND op.ComputerID = oc.ComputerID
        INNER JOIN products p
            ON oc.CommentID = p.ProductID
        WHERE op.TransactionID <> 0
          AND op.ComputerID <> 0
          AND op.OrderDetailID <> 0

        UNION ALL

        SELECT
            op.TransactionID AS TransactionID,
            op.ComputerID AS ComputerID,
            op.OrderDetailID AS OrderDetailID,
            op.ProcessID AS ProcessID,
            p.ProductName AS OrderComment,
            od.Amount AS CommentAmount,
            15 AS CommentSetType
        FROM orderprocessdetailfront op
        INNER JOIN ordercommentwithpricedetail oc
            ON op.OrderDetailID = oc.OrderLinkID
           AND op.TransactionID = oc.TransactionID
           AND op.ComputerID = oc.ComputerID
        INNER JOIN orderdetail od
            ON od.TransactionID = oc.TransactionID
           AND od.ComputerID = oc.ComputerID
           AND od.OrderDetailID = oc.OrderDetailID
        INNER JOIN products p
            ON oc.ProductID = p.ProductID
        WHERE op.TransactionID <> 0
          AND op.ComputerID <> 0
          AND op.OrderDetailID <> 0
    ";
}

function fetchAllRows($conn, $sql)
{
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    $rows = array();
    while ($row = $result->fetch_assoc()) {
        $row['ProductAmount'] = isset($row['ProductAmount']) ? (float)$row['ProductAmount'] : 0;
        $rows[] = $row;
    }

    return $rows;
}

function lookupStaff($conn)
{
    $staffCode = isset($_POST['staff_code']) ? trim((string)$_POST['staff_code']) : '';
    if ($staffCode === '') {
        jsonResponse(array('success' => false, 'error' => 'กรุณากรอกรหัสพนักงาน'));
        return;
    }

    $sql = "
        SELECT StaffID,
               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(StaffFirstName,''),' ',COALESCE(StaffLastName,''))),''  ), '') AS StaffName
        FROM staffs
        WHERE StaffCode = ?
          AND Deleted = 0
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        jsonResponse(array('success' => false, 'error' => 'DB error'), 500);
        return;
    }
    $stmt->bind_param('s', $staffCode);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && ($row = $result->fetch_assoc())) {
        $staffId   = (int)$row['StaffID'];
        $staffName = trim((string)$row['StaffName']) ?: 'Staff #' . $staffId;
        $stmt->close();
        jsonResponse(array('success' => true, 'staff_id' => $staffId, 'staff_name' => $staffName));
    } else {
        $stmt->close();
        jsonResponse(array('success' => false, 'error' => 'ไม่พบรหัสพนักงาน'));
    }
}

function listOutOfStockProducts($conn)
{
    $keyword = requestString('q', '');
    $rows = fetchOutOfStockProducts($conn, $keyword);

    jsonResponse(array(
        'success' => true,
        'rows' => $rows,
        'count' => count($rows),
    ));
}

function fetchOutOfStockProducts($conn, $keyword = '')
{
    $where = array(
        'p.Deleted = 0',
        'p.ProductActivate = 1',
        'COALESCE(pg.IsComment,0) = 0'
    );

    $types = '';
    $params = array();
    if ($keyword !== '') {
        $where[] = '(p.ProductCode LIKE ? OR p.ProductName LIKE ? OR p.ProductName1 LIKE ? OR pd.ProductDeptName LIKE ? OR pg.ProductGroupName LIKE ?)';
        $like = '%' . $keyword . '%';
        $types = 'sssss';
        $params = array($like, $like, $like, $like, $like);
    }

    $sql = "
        SELECT
            p.ProductID,
            p.ProductCode,
            p.ProductName,
            p.ProductName1,
            p.IsOutOfStock,
            p.UpdateDate,
            p.UpdateBy,
            COALESCE(pd.ProductDeptName, '-') AS ProductDeptName,
            COALESCE(pg.ProductGroupName, '-') AS ProductGroupName
        FROM products p
        LEFT JOIN productdept pd
            ON pd.ProductDeptID = p.ProductDeptID
           AND pd.Deleted = 0
        LEFT JOIN productgroup pg
            ON pg.ProductGroupID = pd.ProductGroupID
           AND pg.Deleted = 0
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
            p.IsOutOfStock ASC,
            pg.ProductGroupName ASC,
            pd.ProductDeptName ASC,
            p.ProductOrdering ASC,
            p.ProductCode ASC,
            p.ProductID ASC";

    if ((int)OUT_OF_STOCK_SHOW_LIMIT > 0) {
        $sql .= ' LIMIT ' . (int)OUT_OF_STOCK_SHOW_LIMIT;
    }

    if ($types === '') {
        return fetchAllRows($conn, $sql);
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

function requestString($key, $default = null)
{
    if (!isset($_REQUEST[$key])) {
        return $default !== null ? (string)$default : '';
    }

    return trim((string)$_REQUEST[$key]);
}

function requestInt($key, $default = null)
{
    if (!isset($_REQUEST[$key]) || $_REQUEST[$key] === '') {
        if ($default !== null) {
            return (int)$default;
        }
        throw new Exception('ข้อมูลไม่ครบ: ' . $key);
    }

    return (int)$_REQUEST[$key];
}
