<?php
// screen_lock.php v10 — server-side screen counter (ใช้ file แทน DB)
// 1. auth_check.php: require_once __DIR__ . '/screen_lock.php';
// 2. checker.php in <head>: screenLockJavascript();

if (!defined('SCREEN_LOCK_ENABLED'))       { define('SCREEN_LOCK_ENABLED',       true); }
if (!defined('SCREEN_LOCK_HEARTBEAT_SEC')) { define('SCREEN_LOCK_HEARTBEAT_SEC', 8);    }
if (!defined('SCREEN_LOCK_MAX_SCREENS'))   { define('SCREEN_LOCK_MAX_SCREENS',   3);    }
// timeout: ถ้า instance ไม่ ping นานกว่านี้ถือว่าปิดไปแล้ว
if (!defined('SCREEN_LOCK_TIMEOUT_SEC'))   { define('SCREEN_LOCK_TIMEOUT_SEC',   20);   }

if (!SCREEN_LOCK_ENABLED) {
    if (!function_exists('screenLockJavascript')) {
        function screenLockJavascript() { return; }
    }
    return;
}

$GLOBALS['_slMaxScreens'] = (int)SCREEN_LOCK_MAX_SCREENS;
$GLOBALS['_slHbMs']       = (int)(SCREEN_LOCK_HEARTBEAT_SEC * 1000);

if (!function_exists('_slComputeBase')) {
    function _slComputeBase() {
        $myDir      = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
        $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? (string)$_SERVER['SCRIPT_FILENAME'] : '';
        $scriptDir  = $scriptFile !== ''
            ? str_replace('\\', '/', dirname(realpath($scriptFile) ?: $scriptFile))
            : $myDir;
        $levelsUp = 0;
        $d = $scriptDir;
        while (strlen($d) > strlen($myDir) && $d !== dirname($d)) {
            $d = dirname($d);
            $levelsUp++;
        }
        $urlDir = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '')), '/');
        for ($i = 0; $i < $levelsUp; $i++) {
            $urlDir = rtrim(str_replace('\\', '/', dirname($urlDir)), '/');
        }
        return ($urlDir === '' || $urlDir === '.') ? '' : $urlDir;
    }
}

if (!function_exists('screenLockJavascript')) {
    function screenLockJavascript() {
        $cfg = array(
            'hb'         => $GLOBALS['_slHbMs'],
            'maxScreens' => $GLOBALS['_slMaxScreens'],
            't1'         => 'เกินจำนวนหน้าจอที่อนุญาต',
            'd1'         => 'ระบบอนุญาตสูงสุด ' . $GLOBALS['_slMaxScreens'] . ' หน้าจอ กรุณาปิดหน้าจออื่นก่อน',
            't2'         => 'ถูก Kick ออก',
            'd2'         => 'มีการบังคับเข้าใช้งานจากหน้าจออื่น',
            'btn'        => 'บังคับเข้าใช้งาน (kick หน้าจอเก่าสุด)',
            'note'       => 'หน้าจอที่เปิดนานที่สุดจะถูกล็อคออกอัตโนมัติ',
        );
        $json  = json_encode($cfg, JSON_UNESCAPED_UNICODE);
        $slBase = function_exists('_computeCheckerBase') ? _computeCheckerBase() : _slComputeBase();
        $jsUrl  = $slBase . '/screen_lock.js';
        echo '<script>window._kdsLockCfg=' . $json . ';</script>' . "\n";
        echo '<script src="' . htmlspecialchars($jsUrl, ENT_QUOTES) . '"></script>' . "\n";
    }
}
