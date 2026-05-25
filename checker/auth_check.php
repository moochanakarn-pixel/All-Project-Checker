<?php
// require_once __DIR__ . '/auth_check.php';

// session ถูก start ใน checker.php แล้ว — ไม่ต้อง start ซ้ำ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['kds_staff_id']   = $_SESSION['kds_staff_id']   ?? 0;
$_SESSION['kds_staff_name'] = $_SESSION['kds_staff_name'] ?? 'Guest';
$_SESSION['kds_staff_code'] = $_SESSION['kds_staff_code'] ?? '';

require_once __DIR__ . '/screen_lock.php';
