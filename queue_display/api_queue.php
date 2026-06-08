<?php
ob_start();
require_once __DIR__ . '/config.php';

set_exception_handler(function ($e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
});

try {
    $conn = getDbConnection();

    // Group orders by TransactionID to determine status:
    //   pending_count > 0              → PREPARING (has items still in kitchen)
    //   pending_count = 0 + done > 0  → READY (all items checked out)
    // Only count top-level / standalone items (ProductSetType >= 0) to avoid
    // double-counting set sub-items — same approach as serve_display.
    $sql = "
        SELECT
            o.TransactionID,
            o.ComputerID,
            MAX(TRIM(COALESCE(tr.QueueName, '')))            AS QueueName,
            SUM(CASE WHEN o.ProcessStatus IN (0,2) THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN o.ProcessStatus = 1      THEN 1 ELSE 0 END) AS done_count,
            MIN(o.SubmitOrderDateTime)                       AS first_submit,
            MAX(o.FinishDateTime)                            AS last_finish
        FROM orderprocessdetailfront o
        LEFT JOIN ordertransactionfront tr
            ON  tr.TransactionID = o.TransactionID
            AND tr.ComputerID    = o.ComputerID
        WHERE o.SubmitOrderDateTime >= CURDATE()
          AND o.ProcessStatus IN (0, 1, 2)
          AND o.ProductSetType >= 0
        GROUP BY o.TransactionID, o.ComputerID
        ORDER BY first_submit ASC
    ";

    $result = $conn->query($sql);
    if (!$result) throw new Exception('Query error: ' . $conn->error);

    $preparing = array();
    $ready     = array();

    while ($row = $result->fetch_assoc()) {
        $q = trim((string)$row['QueueName']);
        if ($q === '') {
            $q = str_pad((int)$row['TransactionID'], 4, '0', STR_PAD_LEFT);
        }
        $t = (string)($row['last_finish'] ?: $row['first_submit']);

        if ((int)$row['pending_count'] > 0) {
            $preparing[] = array('q' => $q, 't' => (string)$row['first_submit']);
        } elseif ((int)$row['done_count'] > 0) {
            $ready[] = array('q' => $q, 't' => $t);
        }
    }
    $conn->close();

    // READY: most recently finished first
    usort($ready, function ($a, $b) { return strcmp($b['t'], $a['t']); });
    // PREPARING: newest order first
    usort($preparing, function ($a, $b) { return strcmp($b['t'], $a['t']); });

    $readyLimit = defined('READY_LIMIT')     ? (int)READY_LIMIT     : 30;
    $prepLimit  = defined('PREPARING_LIMIT') ? (int)PREPARING_LIMIT : 30;
    $ready      = array_slice($ready,     0, $readyLimit);
    $preparing  = array_slice($preparing, 0, $prepLimit);

    jsonResponse(array(
        'success'         => true,
        'ready'           => array_column($ready,     'q'),
        'preparing'       => array_column($preparing, 'q'),
        'latest_ready'    => !empty($ready) ? $ready[0]['q'] : '',
        'latest_ready_at' => !empty($ready) ? $ready[0]['t'] : '',
    ));

} catch (Exception $e) {
    jsonResponse(array('success' => false, 'error' => $e->getMessage()));
}
