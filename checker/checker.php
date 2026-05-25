<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';
$machineDisplayName = getMachineDisplayName();
// คำนวณ base URL ของโฟลเดอร์ checker/ โดยใช้ SCRIPT_NAME (URL) แทน DOCUMENT_ROOT (filesystem)
// รองรับ IIS virtual application ที่ URL path ไม่ตรงกับ filesystem path
function _computeCheckerBase() {
    $myDir     = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
    $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? (string)$_SERVER['SCRIPT_FILENAME'] : '';
    $scriptDir  = $scriptFile !== ''
        ? str_replace('\\', '/', dirname(realpath($scriptFile) ?: $scriptFile))
        : $myDir;

    // นับว่า myDir อยู่สูงกว่า scriptDir กี่ชั้น
    $levelsUp = 0;
    $d = $scriptDir;
    while (strlen($d) > strlen($myDir) && $d !== dirname($d)) {
        $d = dirname($d);
        $levelsUp++;
    }

    // เดิน URL ขึ้นไปเท่ากัน
    $scriptUrl = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
    $urlDir    = rtrim(str_replace('\\', '/', dirname($scriptUrl)), '/');
    for ($i = 0; $i < $levelsUp; $i++) {
        $urlDir = rtrim(str_replace('\\', '/', dirname($urlDir)), '/');
    }
    return ($urlDir === '' || $urlDir === '.') ? '' : $urlDir;
}
$_ckBase = _computeCheckerBase();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <link rel="manifest" href="<?php echo $_ckBase; ?>/manifest.php?from=<?php echo urlencode($_SERVER['SCRIPT_NAME']); ?>&base=<?php echo urlencode($_ckBase); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Checker KDS">
    <meta name="theme-color" content="#1683FF">
    <link rel="apple-touch-icon" href="<?php echo $_ckBase; ?>/icon-512.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $_ckBase; ?>/icon-192.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo h(APP_TITLE); ?></title>
    <script src="<?php echo $_ckBase; ?>/jsqr.min.js"></script>
    <?php
    // หา subfolder ที่ settings.local.php อยู่ เพื่อส่งกลับไปให้ api รู้ว่าต้องเขียนไฟล์ไหน
    $_ckSettingsFile = getSettingsLocalFilePath();
    $_ckSettingsDir  = str_replace('\\', '/', dirname(realpath($_ckSettingsFile) ?: $_ckSettingsFile));
    $_ckMyDir        = str_replace('\\', '/', __DIR__);
    $_ckSettingsSub  = ($_ckSettingsDir === $_ckMyDir)
        ? ''
        : ltrim(substr($_ckSettingsDir, strlen($_ckMyDir)), '/');
    ?>
    <script>
        window._kdsBase        = <?php echo json_encode($_ckBase); ?>;
        window._kdsCid         = <?php echo (int)CURRENT_COMPUTER_ID; ?>;
        window._kdsSettingsSub = <?php echo json_encode($_ckSettingsSub); ?>;
        function kdsApiFetch(url, opts, timeoutMs) {
            var sep = url.indexOf('?') >= 0 ? '&' : '?';
            var fullUrl = url + sep + 'kds_cid=' + _kdsCid + '&kds_sub=' + encodeURIComponent(_kdsSettingsSub);
            var ms = timeoutMs || 10000;
            var controller = new AbortController();
            var timer = setTimeout(function() { controller.abort(); }, ms);
            var merged = Object.assign({}, opts || {}, { signal: controller.signal });
            return fetch(fullUrl, merged).finally(function() { clearTimeout(timer); });
        }
    </script>
    <?php screenLockJavascript(); ?>
    <style>
        :root{
            --bg:#edf5ff;
            --bg-2:#fff7ed;
            --surface:#ffffff;
            --surface-soft:#f8fbff;
            --text:#122033;
            --muted:#6b7a90;
            --line:#dbe8f7;
            --primary:#1683ff;
            --primary-dark:#0f69cf;
            --secondary:#ff8a1f;
            --secondary-soft:#fff1e4;
            --success:#12a150;
            --success-soft:#e6f8ee;
            --danger:#e44c3a;
            --danger-soft:#ffe8e4;
            --shadow:0 12px 28px rgba(15, 23, 42, .10);
            --shadow-soft:0 8px 18px rgba(22, 131, 255, .08);
            --radius:22px;
            --card-min-w:220px;
        }
        *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
        html,body{height:100%}
        body{
            margin:0;
            font-family:Tahoma, Arial, sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at top left, rgba(22,131,255,.12), transparent 28%),
                radial-gradient(circle at top right, rgba(255,138,31,.14), transparent 24%),
                linear-gradient(180deg, var(--bg), var(--bg-2));
        }
        body.drawer-open{overflow:hidden}
        button,input{font:inherit}

        /* ── Topbar ── */
        .topbar{
            position:sticky;top:0;z-index:30;
            padding:8px 14px 7px;
            backdrop-filter:blur(12px);
            background:linear-gradient(135deg, rgba(8,58,112,.92), rgba(22,131,255,.88), rgba(255,138,31,.84));
            color:#fff;
            box-shadow:0 8px 20px rgba(8,58,112,.18);
        }
        .topbar-inner{
            max-width:1920px;margin:0 auto;
            display:flex;flex-wrap:wrap;align-items:center;gap:8px;
            justify-content:space-between;
        }
        .brand h1{margin:0;font-size:22px;line-height:1.1;letter-spacing:.2px;white-space:nowrap}
        .controls{display:flex;flex-wrap:wrap;gap:7px;align-items:center}
        .field-card{
            display:flex;align-items:center;gap:6px;padding:5px 8px;
            background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);border-radius:14px
        }
        .field-card label{font-size:12px;color:#fff;white-space:nowrap}
        .field-card input,
        .field-card select{
            height:34px;border:none;border-radius:10px;padding:0 8px;
            background:#fff;color:var(--text);font-weight:bold;font-size:14px
        }
        .field-card input{
            width:72px;text-align:center;
        }
        .field-card select{
            min-width:156px;max-width:220px
        }
        .field-card-url input{
            width:280px;max-width:42vw;min-width:180px;text-align:left
        }
        .field-card-barcode{
            gap:8px
        }
        .field-card-barcode label{font-size:12px;color:#fff;white-space:nowrap}
        .field-card-barcode input{
            width:150px;max-width:200px;min-width:108px;text-align:left;letter-spacing:.8px
        }
        .barcode-tools{display:flex;flex-wrap:wrap;gap:7px;align-items:center}
        .barcode-hint{
            display:inline-flex;align-items:center;min-height:34px;padding:0 12px;border-radius:10px;
            background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);
            color:#fff;font-size:12px;font-weight:bold;white-space:nowrap
        }
        .btn{
            appearance:none;border:none;border-radius:12px;min-height:36px;padding:0 12px;font-size:13px;font-weight:bold;
            cursor:pointer;touch-action:manipulation;transition:transform .12s ease,filter .12s ease,opacity .12s ease;
            box-shadow:var(--shadow-soft)
        }
        .btn:active{transform:scale(.985)}
        .btn:disabled{opacity:.65;cursor:not-allowed}
        .btn-primary{background:#fff;color:var(--primary-dark)}
        .btn-accent{background:linear-gradient(135deg,#fff,#fef3e7);color:#8a4b00}
        .btn-success{background:linear-gradient(135deg,var(--secondary),#ffad59);color:#fff}
        .btn-neutral{background:#eef4fb;color:#1f324a}
        .btn-ghost{background:#e9f3ff;color:#0f5bba}
        .btn-danger{background:var(--danger-soft);color:#b33023}
        .btn-checkout-soft{background:linear-gradient(135deg,#fff7ed,#ffe3c2);color:#a34b00;border:1px solid #ffd2a4}
        .btn-checkout-dark{background:linear-gradient(135deg,var(--secondary),#ffad59);color:#fff}

        /* ── Stats bar (compact single row) ── */
        .stats{
            display:flex;gap:8px;padding:8px 14px 4px;
            max-width:1920px;margin:0 auto;
        }
        .stat{
            display:flex;align-items:center;gap:8px;
            background:var(--surface);border:1px solid var(--line);border-radius:14px;
            padding:7px 14px;box-shadow:var(--shadow)
        }
        .stat-label{font-size:12px;color:var(--muted);white-space:nowrap}
        .stat-value{font-size:20px;font-weight:bold;line-height:1}

        /* ── Main page ── */
        .page{max-width:1920px;margin:0 auto;padding:6px 10px 16px}
        .panel{
            background:rgba(255,255,255,.92);border:1px solid rgba(255,255,255,.75);border-radius:20px;
            box-shadow:var(--shadow);overflow:hidden
        }
        .panel-head{
            display:flex;justify-content:space-between;align-items:center;gap:10px;
            padding:10px 14px;border-bottom:1px solid var(--line);
            background:var(--surface)
        }
        .panel-title{margin:0;font-size:17px;font-weight:bold}
        .panel-badge{
            flex:0 0 auto;display:inline-flex;align-items:center;min-height:30px;padding:4px 12px;border-radius:999px;
            background:var(--secondary-soft);color:#9a5200;font-size:13px;font-weight:bold
        }

        /* ── Cards grid: target 8 cards visible ── */
        .cards{
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(var(--card-min-w,220px), 1fr));
            gap:8px;padding:10px
        }
        .card{
            background:linear-gradient(180deg,var(--surface),var(--surface-soft));border:1px solid var(--line);
            border-radius:16px;padding:10px;box-shadow:0 4px 12px rgba(17,56,92,.05);
            display:flex;flex-direction:column
        }
        .card-actions{margin-top:auto}
        .card.warn-yellow{border-color:#ffe066;background:linear-gradient(180deg,#fffde7,#fffbf0);box-shadow:0 0 0 3px rgba(255,214,0,.18)}
        .card.warn-red   {border-color:#ffb3ab;background:linear-gradient(180deg,#fff2f0,#fff8f7);box-shadow:0 0 0 3px rgba(228,76,58,.14)}
        .card.checkout-soft{border-color:#ffd2a4;background:linear-gradient(180deg,#fffaf4,#fff1e4);box-shadow:0 0 0 3px rgba(255,138,31,.10)}
        .card.checkout-dark{border-color:#ffd8b0;background:linear-gradient(180deg,#fff7ed,#fff1e4);box-shadow:0 0 0 3px rgba(255,138,31,.14)}
        /* สถานะพิเศษ */
        .card.moved   {border-color:#93c5fd;background:linear-gradient(180deg,#eff6ff,#f5f9ff);box-shadow:0 0 0 3px rgba(59,130,246,.12)}
        .card.combined{border-color:#a78bfa;background:linear-gradient(180deg,#f5f3ff,#faf9ff);box-shadow:0 0 0 3px rgba(139,92,246,.12)}
        .status-badge{
            display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;
            font-size:11px;font-weight:bold;margin-bottom:6px
        }
        .status-badge.moved   {background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff}
        .status-badge.combined{background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff}
        .status-badge.checkout-soft{background:linear-gradient(135deg,#fff1e4,#ffe0bf);color:#a34b00;border:1px solid #ffc792}
        .status-badge.checkout-dark{background:linear-gradient(135deg,var(--secondary),#ffad59);color:#fff}
        .card-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:start;margin-bottom:8px}
        .table-name{font-size:20px;font-weight:bold;line-height:1.1;word-break:break-word}

        .order-line{margin-top:3px;color:var(--muted);font-size:11px;line-height:1.4}
        .qty-badge{
            min-width:52px;min-height:52px;display:flex;align-items:center;justify-content:center;padding:6px;
            border-radius:14px;background:linear-gradient(135deg,var(--secondary-soft),#fff);
            color:#b35e00;border:1px solid #ffd8b0;font-size:22px;font-weight:bold
        }
        .qty-badge.checkout-soft{background:linear-gradient(135deg,#fff1e4,#fffaf5);border-color:#ffd2a4;color:#a34b00}
        .qty-badge.checkout-dark{background:linear-gradient(135deg,var(--secondary),#ffad59);border-color:#ffd8b0;color:#fff}
        .product-block{margin:0 0 8px}
        .product-name{margin:0;font-size:17px;line-height:1.2;word-break:break-word;font-weight:bold}
        .product-total-hint{margin-top:6px;display:block;padding:0;background:transparent;border:none;border-radius:0;font-size:12px;font-weight:bold;color:var(--primary)}
        .parent-name-label{
            display:inline-block;margin-bottom:3px;font-size:11px;font-weight:bold;color:#fff;
            background:linear-gradient(135deg,#16a34a,#15803d);
            padding:2px 8px;border-radius:999px;letter-spacing:.3px
        }
        .set-compact-badge{
            display:inline-block;margin-top:3px;font-size:11px;font-weight:bold;color:#6d28d9;
            background:#ede9fe;border:1px solid #c4b5fd;
            padding:2px 8px;border-radius:999px;letter-spacing:.3px
        }
        .card-voided{border-color:#fca5a5;background:linear-gradient(180deg,#fff5f5,#fff0f0);opacity:.92}
        .voided-badge{display:inline-block;margin-bottom:8px;font-size:12px;font-weight:bold;color:#b91c1c;
            background:#fee2e2;border:1px solid #fca5a5;padding:3px 10px;border-radius:999px}
        .btn-confirm-void{background:#dc2626;color:#fff;border:none;border-radius:12px;
            font-size:13px;font-weight:bold;cursor:pointer;padding:0 12px;min-height:42px;width:100%}
        .btn-confirm-void:hover{background:#b91c1c}
        .btn-confirm-void:disabled{opacity:.5;cursor:not-allowed}
        .product-comments-inline{margin-top:8px;display:flex;flex-direction:column;gap:6px}
        .comment-inline{font-size:13px;line-height:1.4;color:#5b2c00;word-break:break-word;padding:8px 10px;border-radius:12px;border:1px solid #ffd8b0;background:#fff8f0}
        .comment-inline .label{font-weight:bold;margin-right:4px;color:#9a5200}
        .comment-inline.priced{color:#8b4a00;border-color:#ffc792;background:#fff1e4}
        .queue-tags{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:8px}
        .tag{
            display:inline-flex;align-items:center;min-height:26px;padding:4px 9px;border-radius:999px;
            font-size:11px;font-weight:bold;background:var(--surface-soft);color:var(--primary);border:1px solid var(--line)
        }
        .tag.wait{background:var(--secondary-soft);color:#9f5200;border-color:#ffd2a4}
        .tag.good{background:var(--success-soft);color:#11783c;border-color:#bfeacc}
        .tag.urgent{background:#ffe8e4;color:#b33023;border-color:#ffb3ab}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;margin-bottom:8px}
        .field{padding:7px 10px;border-radius:12px;background:var(--surface-soft);border:1px solid var(--line)}
        .field-label{font-size:11px;color:var(--muted);margin-bottom:2px}
        .field-value{font-size:13px;font-weight:bold;word-break:break-word}
        .card-actions{display:flex;gap:8px}
        .card-actions .btn{flex:1;min-height:42px;font-size:13px}
        .field.is-special{background:#fff8f0;border-color:#ffd8b0}
        .field.is-special .field-label{color:#9a5200}
        .btn-warning{background:linear-gradient(135deg,#f59e0b,#f97316);color:#fff}
        .empty{padding:24px 14px;text-align:center;color:var(--muted);font-size:14px}

        /* ── Notice ── */
        .notice{
            position:fixed;left:14px;right:14px;bottom:calc(14px + env(safe-area-inset-bottom));z-index:80;display:none;
            padding:12px 14px;border-radius:16px;font-size:14px;font-weight:bold;box-shadow:0 16px 32px rgba(15,23,42,.16)
        }
        .notice.success{display:block;background:var(--success-soft);color:#0f7b3b;border:1px solid #bfeacc}
        .notice.error  {display:block;background:var(--danger-soft);color:#b33023;border:1px solid #f6beb7}

        /* ── Drawer ── */
        .drawer-backdrop{
            position:fixed;inset:0;background:rgba(7,23,42,.42);backdrop-filter:blur(4px);
            opacity:0;pointer-events:none;transition:opacity .18s ease;z-index:70
        }
        .drawer-backdrop.open{opacity:1;pointer-events:auto}
        .drawer{
            position:fixed;top:0;right:0;width:min(460px,100vw);height:100vh;background:var(--surface);z-index:71;
            box-shadow:-20px 0 40px rgba(15,23,42,.18);transform:translateX(100%);transition:transform .22s ease;
            display:flex;flex-direction:column
        }
        .drawer.open{transform:translateX(0)}
        .drawer-head{
            display:flex;justify-content:space-between;gap:10px;align-items:flex-start;
            padding:16px 16px 12px;border-bottom:1px solid var(--line);
            background:var(--surface)
        }
        .drawer-title{margin:0;font-size:20px;line-height:1.12}
        .drawer-sub{margin-top:4px;font-size:12px;color:var(--muted)}
        .drawer-close{min-width:44px;padding:0 12px}
        .drawer-list{padding:10px 12px 20px;display:flex;flex-direction:column;gap:8px;overflow:auto;flex:1}
        .finished-item{border:1px solid var(--line);border-radius:16px;padding:11px;background:var(--surface)}
        .finished-top{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:start;margin-bottom:7px}
        .finished-name{font-size:16px;font-weight:bold;line-height:1.25}
        .finished-qty{font-size:20px;font-weight:bold;color:var(--success)}
        .finished-meta{font-size:12px;color:var(--muted);line-height:1.5;margin-bottom:8px}
        .comment-list{display:flex;flex-direction:column;gap:6px;margin:10px 0 0}
        .comment-list.compact{margin-top:6px}
        .comment-group{
            display:flex;flex-wrap:wrap;align-items:flex-start;gap:6px;padding:7px 10px;
            border-radius:12px;border:1px solid #ffe0bc;background:#fff8f0;color:#6a3900;font-size:12px;line-height:1.4
        }
        .comment-group.priced{background:#fff1e4;border-color:#ffc792}
        .comment-group-label{font-weight:bold;white-space:nowrap;color:#9a5200}
        .comment-group-items{flex:1 1 auto;min-width:0;word-break:break-word}

        /* ── FAB ── */
        .fab{
            position:fixed;right:14px;bottom:calc(18px + env(safe-area-inset-bottom));z-index:65;display:none;
            min-height:52px;padding:0 16px;border-radius:999px;
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            color:#fff;border:none;font-weight:bold;font-size:14px;box-shadow:0 14px 28px rgba(22,131,255,.28)
        }

        /* ── Modal ── */
        .modal-backdrop{position:fixed;inset:0;background:rgba(7,23,42,.45);backdrop-filter:blur(4px);opacity:0;pointer-events:none;transition:opacity .18s ease;z-index:80}
        .modal-backdrop.open{opacity:1;pointer-events:auto}
        .modal{
            position:fixed;top:50%;left:50%;transform:translate(-50%,-54%);
            width:min(480px,95vw);background:var(--surface);border-radius:24px;
            box-shadow:0 24px 56px rgba(15,23,42,.22);z-index:81;
            transition:transform .2s ease,opacity .2s ease;opacity:0;pointer-events:none;
            max-height:92vh;display:flex;flex-direction:column
        }
        .modal.open{transform:translate(-50%,-50%);opacity:1;pointer-events:auto}
        .modal-head{padding:18px 20px 12px;border-bottom:1px solid var(--line);flex-shrink:0}
        .modal-title{margin:0;font-size:19px;font-weight:bold}
        .modal-sub{margin-top:3px;font-size:12px;color:var(--muted)}
        .modal-body{padding:16px 20px;overflow-y:auto;flex:1}
        .modal-section{margin-bottom:18px}
        .modal-section:last-child{margin-bottom:0}
        .modal-section-title{font-size:13px;font-weight:bold;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
        .modal-row{display:flex;align-items:center;gap:10px;margin-bottom:12px}
        .modal-row:last-child{margin-bottom:0}
        .modal-swatch{width:24px;height:24px;border-radius:7px;flex-shrink:0;border:2px solid rgba(0,0,0,.08)}
        .modal-swatch.yellow{background:#ffe066}
        .modal-swatch.red{background:#ffb3ab}
        .modal-label{flex:1;font-size:14px;font-weight:600}
        .modal-input{width:72px;height:36px;border:2px solid var(--line);border-radius:10px;padding:0 10px;font-size:15px;font-weight:bold;text-align:center;color:var(--text)}
        .modal-unit{font-size:13px;color:var(--muted);white-space:nowrap}
        .modal-foot{padding:12px 20px 16px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:8px;flex-shrink:0}
        .setting-check{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:var(--surface-soft)}
        .setting-check input{width:18px;height:18px;flex:0 0 auto;margin-top:2px;cursor:pointer}
        .setting-check-title{font-size:14px;font-weight:600;color:var(--text)}
        .setting-check-sub{margin-top:3px;font-size:12px;color:var(--muted);line-height:1.45}
        .filter-section{display:flex;flex-direction:column;gap:8px}
        .filter-section-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px}
        .filter-section-sub{font-size:12px;color:var(--muted);margin-bottom:6px}
        .filter-chips{display:flex;flex-wrap:wrap;gap:6px}
        .filter-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;border:1.5px solid var(--line);background:var(--surface-soft);font-size:13px;font-weight:600;color:var(--text);cursor:pointer;user-select:none;transition:border-color .15s,background .15s}
        .filter-chip input[type=checkbox]{display:none}
        .filter-chip.active{border-color:var(--primary);background:var(--primary);color:#fff}
        .setting-stack{display:flex;flex-direction:column;gap:14px}
        .setting-grid{display:grid;grid-template-columns:1fr 160px;gap:12px}
        .setting-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .setting-field label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:6px}
        .setting-field input{width:100%;height:42px;border:2px solid var(--line);border-radius:12px;padding:0 12px;font-size:14px;font-weight:700;color:var(--text);background:var(--surface)}
        .setting-field input:focus{outline:none;border-color:#9ecbff;box-shadow:0 0 0 4px rgba(22,131,255,.10)}
        .setting-help{font-size:12px;color:var(--muted);line-height:1.45;margin-top:6px}
        .settings-status{padding:10px 12px;border-radius:12px;background:var(--surface-soft);border:1px solid var(--line);font-size:13px;color:var(--text);line-height:1.5;display:none}
        .settings-status.show{display:block}
        .settings-status.success{background:#e6f8ee;border-color:#bfeacc;color:#0f7b3b}
        .settings-status.error{background:#ffe8e4;border-color:#f6beb7;color:#b33023}
        .settings-inline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .settings-staff-name{font-size:13px;font-weight:700;color:var(--primary)}
        .settings-headline{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
        .settings-pill{display:inline-flex;align-items:center;min-height:30px;padding:0 10px;border-radius:999px;background:var(--surface-soft);border:1px solid var(--line);font-size:12px;font-weight:700;color:var(--text)}
        .modal-tabs{display:flex;gap:4px;padding:0 20px 0;border-bottom:1px solid var(--line);margin-top:4px}
        .modal-tab{background:none;border:none;border-bottom:2px solid transparent;padding:8px 14px;font-size:13px;font-weight:700;color:var(--muted);cursor:pointer;margin-bottom:-1px;border-radius:0;transition:color .15s}
        .modal-tab.active{color:var(--primary);border-bottom-color:var(--primary)}
        .tab-panel{display:none}.tab-panel.active{display:block}
        .appearance-btn-group{display:flex;flex-wrap:wrap;gap:8px}
        .appearance-btn{padding:7px 16px;border-radius:10px;border:2px solid var(--line);background:var(--surface);font-size:13px;font-weight:700;color:var(--text);cursor:pointer;transition:border-color .15s,background .15s}
        .appearance-btn.active{border-color:var(--primary);background:var(--primary);color:#fff}

        .camera-wrap{display:flex;flex-direction:column;gap:12px}
        .camera-frame{position:relative;border-radius:18px;overflow:hidden;background:#0b1220;min-height:320px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.06)}
        .camera-video{display:block;width:100%;height:min(62vh,520px);object-fit:cover;background:#0b1220}
        .camera-overlay{position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center;background:linear-gradient(180deg,rgba(0,0,0,.08),rgba(0,0,0,.18))}
        .camera-guide{width:min(82%,420px);height:min(28vw,140px);max-height:140px;border:3px solid rgba(255,255,255,.92);border-radius:18px;box-shadow:0 0 0 9999px rgba(0,0,0,.18)}
        .camera-status{padding:10px 12px;border-radius:12px;background:var(--surface-soft);border:1px solid var(--line);font-size:13px;color:var(--muted);line-height:1.5}
        .camera-modal .modal-body{padding:14px 16px}
        /* sound upload zone */
        .sound-upload-zone{
            border:2px dashed var(--line);border-radius:14px;padding:14px 16px;
            display:flex;flex-direction:column;gap:8px;background:var(--surface-soft)
        }
        .sound-upload-row{display:flex;align-items:center;gap:10px}
        .sound-name{flex:1;font-size:13px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .sound-name.loaded{color:var(--success);font-weight:600}
        .sound-preview-btn{min-height:32px;padding:0 12px;font-size:12px}
        .sound-file-input{display:none}

        /* ── Responsive ── */
        @media (max-width:1200px){
            .cards{grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}
        }
        @media (max-width:820px){
            .cards{grid-template-columns:repeat(2,1fr)}
            .grid{grid-template-columns:1fr}
            .panel-head{padding:10px 12px}
            .fab{display:none !important}
        }
        @media (max-width:760px){
            .field-card-url input,.field-card-barcode input{width:100%;max-width:100%;min-width:0}
        }
        @media (max-width:700px){.setting-grid,.setting-grid-2{grid-template-columns:1fr}}
        @media (max-width:560px){
            .topbar{padding:8px 10px 7px}
            .page{padding:6px 8px 14px}
            .brand h1{font-size:18px}
            .stats{padding:6px 8px 3px}
            .stat{padding:5px 10px}
            .stat-value{font-size:17px}
            .cards{grid-template-columns:repeat(2,1fr);padding:8px;gap:7px}
            .card{padding:9px;border-radius:14px}
            .table-name{font-size:17px}
            .qty-badge{min-width:44px;min-height:44px;font-size:18px;border-radius:12px}
            .product-name{font-size:14px}
            .card-actions .btn{min-height:38px;padding:0 8px}
            .drawer{width:100vw;top:auto;bottom:0;height:min(82vh,720px);transform:translateY(100%);right:0;border-radius:22px 22px 0 0}
            .drawer.open{transform:translateY(0)}
        }
        @media (max-width:820px){
            .camera-modal{top:0;left:0;width:100vw;max-width:none;height:100dvh;max-height:none;border-radius:0;transform:translateY(100%)}
            .camera-modal.open{transform:translateY(0)}
            .camera-modal .modal-head{padding:16px 16px 10px}
            .camera-modal .modal-body{padding:12px 12px 8px}
            .camera-modal .modal-foot{padding:10px 12px calc(12px + env(safe-area-inset-bottom))}
            .camera-frame{min-height:calc(100dvh - 220px);border-radius:16px}

        .soldout-search{width:100%;height:42px;border:2px solid var(--line);border-radius:12px;padding:0 14px;font-size:15px;font-weight:600;color:var(--text);outline:none}
        .soldout-note{margin-top:8px;font-size:12px;color:var(--muted)}
        .soldout-list{display:flex;flex-direction:column;gap:10px;max-height:52vh;overflow:auto}
        .soldout-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 14px;border:1px solid var(--line);border-radius:14px;background:var(--surface)}
        .soldout-item.closed{background:#f9fafb;border-color:#d1d5db}
        .soldout-meta{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6px}
        .soldout-chip{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#eef2ff;color:#1d4ed8}
        .soldout-chip.closed{background:#fee2e2;color:#b91c1c}
        .soldout-name{font-size:18px;font-weight:800;color:var(--head);line-height:1.3}
        .soldout-sub{margin-top:4px;font-size:12px;color:var(--muted);line-height:1.5}
        .soldout-actions{display:flex;gap:8px;align-items:center}
        .btn-small{padding:10px 14px;font-size:13px;border-radius:12px}
            .camera-video{height:calc(100dvh - 240px)}
            .camera-guide{width:88%;height:min(34vw,150px)}
        }
    
        /* ── Fullscreen Button ── */
        .btn-fullscreen{
            display:inline-flex;align-items:center;gap:5px;
            padding:5px 11px;border-radius:9px;border:none;cursor:pointer;
            font-size:13px;font-weight:600;white-space:nowrap;
            background:rgba(255,255,255,0.18);color:#fff;
            backdrop-filter:blur(6px);
            transition:background .18s,transform .12s;
            flex-shrink:0;
        }
        .btn-fullscreen:hover{background:rgba(255,255,255,0.30);transform:scale(1.04)}
        .btn-fullscreen svg{width:15px;height:15px;flex-shrink:0}

        /* fullscreen auto-hide UI */
        .fs-ui-hidden .stats,
        .fs-ui-hidden .panel-head { opacity:0; max-height:0; overflow:hidden; margin:0; padding:0; pointer-events:none; transition:opacity .35s, max-height .35s; }
        .stats { max-height:80px; transition:opacity .35s, max-height .35s; }
        .panel-head { max-height:80px; transition:opacity .35s, max-height .35s; }
</style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <div><h1><?php echo h(APP_TITLE); ?></h1><div id="machineDisplayName" style="margin-top:4px;font-size:12px;opacity:.95;font-weight:bold;display:<?php echo $machineDisplayName !== '' ? 'block' : 'none'; ?>"><?php echo h($machineDisplayName); ?></div></div>
            </div>
            <div class="controls">
                <div id="staffLoginArea" style="display:flex;align-items:center;gap:6px">
                    <input type="text" id="topStaffCodeInput" placeholder="Staff Code" autocomplete="off" style="text-transform:uppercase;width:110px;padding:6px 10px;border:1px solid var(--line,#e5e7eb);border-radius:8px;font-size:16px;background:#fff;color:#111">
                    <button type="button" class="btn btn-primary" id="topStaffLoginBtn" style="min-height:36px;padding:0 12px;font-size:13px">เข้าสู่ระบบ</button>
                </div>
                <div id="staffLoggedArea" style="display:none;align-items:center;gap:6px">
                    <span id="topStaffNameLabel" style="font-size:13px;font-weight:bold;color:#fff;background:rgba(0,0,0,.18);padding:4px 10px;border-radius:8px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
                    <button type="button" class="btn btn-ghost" id="topStaffLogoutBtn" style="min-height:36px;padding:0 10px;font-size:13px">ออกจากระบบ</button>
                </div>
                <button type="button" class="btn btn-accent" id="openSystemSettingsBtn" style="display:none">⚙️ ตั้งค่าระบบ</button>
                <button type="button" class="btn btn-neutral" id="openSoldOutBtn">🥫 ปิดสินค้าหมด</button>
                <div class="barcode-tools" id="barcodeTools">
                    <div class="field-card field-card-barcode">
                        <label for="barcodeInput">สแกนบาร์โค้ด</label>
                        <input type="text" id="barcodeInput" inputmode="numeric" autocomplete="off" placeholder="ยิงบาร์โค้ดแล้วเช็คเอาต์ทันที">
                    </div>
                    <button type="button" class="btn btn-neutral" id="openBarcodeCameraBtn">📷 สแกนกล้อง</button>
                </div>
                <button type="button" class="btn btn-neutral" id="openZoneBtn">📍 โซน: <span id="zoneLabel">ทั้งหมด</span></button>
                <button type="button" class="btn btn-ghost js-open-finished" id="openFinishedBtn">✅ เสร็จแล้ว <span id="topFinishedCount">0</span></button>
                <button type="button" class="btn btn-primary" id="refreshBtn">🔄 รีเฟรช</button>
                <button type="button" class="btn-fullscreen" id="fsBtn" title="เต็มจอ">
                    <svg class="fs-ico-enter" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                    <svg class="fs-ico-exit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" style="display:none"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/></svg>
                    เต็มจอ
                </button>
            </div>
        </div>
    </div>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">คิวค้าง</div>
            <div class="stat-value" id="statActiveRows">0</div>
        </div>
        <div class="stat">
            <div class="stat-label">รายการ</div>
            <div class="stat-value" id="statActiveQty">0</div>
        </div>
        <div class="stat">
            <div class="stat-label">สถานะ</div>
            <div class="stat-value" id="statStatusText" style="font-size:15px;color:var(--success)">พร้อมใช้งาน</div>
        </div>
    </div>
    <div class="page">
        <div class="layout">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">คิวครัวที่ยังค้างอยู่</h2>
                        
                    </div>
                    <div class="panel-badge" id="queueSummary">กำลังโหลด...</div>
                </div>
                <div class="cards" id="activeCards">
                    <div class="empty">กำลังโหลดข้อมูล...</div>
                </div>
            </section>
        </div>
    </div>

    <button type="button" class="fab js-open-finished" id="openFinishedFab">ดูรายการเสร็จ <span id="fabFinishedCount">0</span></button>

    <div class="drawer-backdrop" id="finishedDrawerBackdrop"></div>
    <aside class="drawer" id="finishedDrawer" aria-hidden="true">
        <div class="drawer-head">
            <div>
                <h2 class="drawer-title">รายการที่เสร็จแล้ววันนี้</h2>
                <div class="drawer-sub">แสดงรายการที่เสร็จแล้วทั้งหมดของวันนี้ และกดย้อนกลับ 1 รายการได้</div>
            </div>
            <button type="button" class="btn btn-neutral drawer-close js-close-finished">ปิด</button>
        </div>
        <div class="drawer-list" id="recentFinishedList">
            <div class="empty">ยังไม่มีรายการ</div>
        </div>
    </aside>

    <div class="modal-backdrop" id="timerSettingsBackdrop"></div>
    <div class="modal" id="timerSettingsModal" role="dialog" aria-modal="true" aria-labelledby="timerSettingsTitle">
        <div class="modal-head">
            <h2 class="modal-title" id="timerSettingsTitle">⚙️ ตั้งค่า</h2>
        </div>
        <div class="modal-tabs">
            <button class="modal-tab active" id="tabBtnSystem">⚙️ ระบบ</button>
            <button class="modal-tab" id="tabBtnAppearance">🎨 หน้าตา</button>
        </div>
        <div class="modal-body">
            <div id="tabPanelSystem" class="tab-panel active">
            <div class="settings-status" id="systemSettingsStatusBox"></div>

            <div class="modal-section">
                <div class="settings-headline">
                    <div class="modal-section-title">ฐานข้อมูล</div>
                    <span class="settings-pill" id="dbUserHint" style="display:none">User: -</span>
                </div>
                <div class="setting-stack">
                    <div class="setting-field">
                        <label for="settingsDbHost">DB Host / IP</label>
                        <input type="text" id="settingsDbHost" placeholder="เช่น 127.0.0.1 หรือ 192.168.1.10">
                    </div>
                    <input type="hidden" id="settingsDbPort" value="3307">
                    <div class="setting-grid">
                        <div class="setting-field">
                            <label for="settingsDbName">Database Name</label>
                            <input type="text" id="settingsDbName" placeholder="เช่น ini76">
                        </div>
                        <div class="setting-field" style="justify-content:flex-end">
                            <button type="button" class="btn btn-neutral" id="testDbConnectionBtn" style="width:100%;margin-top:22px">ทดสอบการเชื่อมต่อ</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-section">
                <div class="modal-section-title">เครื่องที่ใช้งาน</div>
                <div class="setting-grid">
                    <div class="setting-field">
                        <label for="settingsComputerId">Computer ID</label>
                        <input type="number" id="settingsComputerId" min="1" placeholder="เช่น 2">
                    </div>
                    <div class="setting-field">
                        <label>ชื่อเครื่องที่จะแสดง</label>
                        <input type="hidden" id="settingsComputerName">
                        <div id="settingsComputerNameBox" class="settings-status"></div>
                    </div>
                </div>
                <div class="setting-grid" style="margin-top:10px">
                    <div class="setting-field">
                        <label for="settingsShopId">Shop ID <span style="color:var(--muted);font-weight:normal">(สำหรับกรอง Zone)</span></label>
                        <select id="settingsShopId" style="width:100%;height:38px;border:2px solid var(--line);border-radius:10px;padding:0 10px;font-size:14px;color:var(--text);background:var(--surface)">
                            <option value="0">-- ดึงทุก Shop --</option>
                        </select>
                    </div>
                    <div class="setting-field">
                        <label>ชื่อร้าน</label>
                        <div class="settings-status show" id="settingsShopNameBox" style="font-size:14px">-</div>
                    </div>
                </div>
            </div>

            <div class="modal-section">
                <div class="modal-section-title">ผู้ใช้งานและการแจ้งเตือน</div>
                <div class="setting-stack">
                    <div class="setting-grid">
                        <div class="setting-field">
                            <label for="settingsStaffCode">รหัสพนักงาน (Staff Code)</label>
                            <input type="text" id="settingsStaffCode" placeholder="กรอก Staff Code" autocomplete="off" style="text-transform:uppercase">
                            <input type="hidden" id="settingsFinishStaffId">
                        </div>
                        <div class="setting-field">
                            <label>ชื่อพนักงาน</label>
                            <div class="settings-status show" id="settingsStaffNameBox">ยังไม่ได้เลือกพนักงาน</div>
                        </div>
                    </div>
                    <label class="setting-check">
                        <div>
                            <div class="setting-check-title">ซ่อนช่อง Login พนักงานบน topbar</div>
                            <div class="setting-check-sub">ใช้รหัสพนักงานด้านบนแทนการ login — เหมาะกับสถานีที่ไม่ต้องการให้เปลี่ยนพนักงาน</div>
                        </div>
                        <input type="checkbox" id="hideStaffLogin">
                    </label>
                    <label class="setting-check">
                        <div>
                            <div class="setting-check-title">ให้ครัวยืนยันรายการที่ถูกยกเลิก</div>
                            <div class="setting-check-sub">เปิด = โชว์รายการที่ถูกยกเลิกในครัวพร้อมปุ่มยืนยัน / ปิด = ยืนยันอัตโนมัติทันที (ค่าเริ่มต้น)</div>
                        </div>
                        <input type="checkbox" id="voidConfirmMode">
                    </label>
                    <label class="setting-check">
                        <div>
                            <div class="setting-check-title">แสดงเฉพาะออเดอร์วันนี้ (Active)</div>
                            <div class="setting-check-sub">เปิด = กรองเฉพาะ OrderDate = วันนี้ (ค่าเริ่มต้น) / ปิด = แสดงทุกออเดอร์ที่ยังค้างอยู่ เหมาะกับร้านที่รันข้ามเที่ยงคืน</div>
                        </div>
                        <input type="checkbox" id="activeRowsTodayOnly">
                    </label>
                    <label class="setting-check">
                        <div>
                            <div class="setting-check-title">แสดงเลขออเดอร์บนการ์ด</div>
                            <div class="setting-check-sub">แสดงเลข Order# (ProcessID) มุมบนขวาของการ์ด — ใช้ตรวจสอบกับใบเสร็จหรือสแกนบาร์โค้ด</div>
                        </div>
                        <input type="checkbox" id="showOrderNumber">
                    </label>
                    <div class="setting-grid-2">
                        <div class="modal-row" style="margin-bottom:0">
                            <div class="modal-swatch yellow"></div>
                            <div class="modal-label">เวลาแจ้งเตือนสีเหลือง</div>
                            <input type="number" class="modal-input" id="thresholdYellow" min="1" max="999" value="10">
                            <span class="modal-unit">นาที</span>
                        </div>
                        <div class="modal-row" style="margin-bottom:0">
                            <div class="modal-swatch red"></div>
                            <div class="modal-label">เวลาแจ้งเตือนสีแดง</div>
                            <input type="number" class="modal-input" id="thresholdRed" min="1" max="999" value="20">
                            <span class="modal-unit">นาที</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-section">
                <div class="modal-section-title">ฟีเจอร์</div>
                <div class="setting-stack">
                    <label class="setting-check">
                        <div>
                            <div class="setting-check-title">เปิดเสียงแจ้งเตือน</div>
                            <div class="setting-check-sub">ใช้เสียงแจ้งเตือนเมื่อมีออเดอร์ใหม่เข้ามาในจอ Checker</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px"><input type="checkbox" id="soundEnabled"><span id="soundEnabledLabel" style="font-size:13px;color:var(--muted);font-weight:700">ปิดอยู่</span></div>
                    </label>
                    <div class="sound-upload-zone">
                        <div style="font-size:12px;color:var(--muted);font-weight:700;margin-bottom:4px">เสียงออเดอร์ใหม่</div>
                        <div class="sound-upload-row">
                            <span class="sound-name" id="soundFileName">กำลังโหลดเสียงจาก Server…</span>
                            <button type="button" class="btn btn-neutral sound-preview-btn" id="soundPreviewBtn" disabled>▶ ทดสอบ</button>
                        </div>
                        <div class="sound-upload-row" id="soundUploadRow" style="display:none">
                            <label class="btn btn-accent" style="cursor:pointer;min-height:36px;padding:0 14px;display:inline-flex;align-items:center;font-size:13px">
                                📁 เลือกไฟล์เสียง (.mp3 / .wav / .ogg)
                                <input type="file" class="sound-file-input" id="soundFileInput" accept="audio/mp3,audio/wav,audio/ogg,audio/*">
                            </label>
                        </div>
                    </div>
                    <div class="sound-upload-zone" style="margin-top:8px">
                        <div style="font-size:12px;color:var(--muted);font-weight:700;margin-bottom:4px">เสียงยกเลิก / ยกเลิกออเดอร์</div>
                        <div class="sound-upload-row">
                            <span class="sound-name" id="soundCancelFileName">กำลังโหลดเสียงจาก Server…</span>
                            <button type="button" class="btn btn-neutral sound-preview-btn" id="soundCancelPreviewBtn" disabled>▶ ทดสอบ</button>
                        </div>
                        <div class="sound-upload-row" id="soundCancelUploadRow" style="display:none">
                            <label class="btn btn-accent" style="cursor:pointer;min-height:36px;padding:0 14px;display:inline-flex;align-items:center;font-size:13px">
                                📁 เลือกไฟล์เสียง (.mp3 / .wav / .ogg)
                                <input type="file" class="sound-file-input" id="soundCancelFileInput" accept="audio/mp3,audio/wav,audio/ogg,audio/*">
                            </label>
                        </div>
                    </div>
                    <label class="setting-check">
                        <div>
                            <div class="setting-check-title">แสดงช่องสแกนบาร์โค้ด</div>
                            <div class="setting-check-sub">แสดงช่องกรอก / ยิงบาร์โค้ดบนแถบเครื่องมือ — ปิดในสถานีที่ไม่ต้องการสแกน</div>
                        </div>
                        <input type="checkbox" id="barcodeScanVisible">
                    </label>
                    <label class="setting-check">
                        <div>
                            <div class="setting-check-title">เปิดสแกนกล้อง</div>
                            <div class="setting-check-sub">ใช้สำหรับเปิดกล้องบนมือถือเพื่อสแกนบาร์โค้ด และเมื่อสแกนเจอจะปิดกล้องให้อัตโนมัติ</div>
                        </div>
                        <input type="checkbox" id="barcodeCameraEnabled">
                    </label>
                    <label class="setting-check">
                        <div>
                            <div class="setting-check-title">เช็ค 2 ชั้น</div>
                            <div class="setting-check-sub">คลิกครั้งแรก = ยืนยันไปครัว, คลิกครั้งที่สอง = checkout จริง</div>
                        </div>
                        <input type="checkbox" id="kdsTwoStepCheckout">
                    </label>
                    <label class="setting-check">
                        <div>
                            <div class="setting-check-title">แสดงปุ่มสินค้าหมด</div>
                            <div class="setting-check-sub">แสดงปุ่ม "ปิดสินค้าหมด" บนแถบเครื่องมือ — ปิดถ้าไม่ต้องการใช้ฟีเจอร์นี้</div>
                        </div>
                        <input type="checkbox" id="kdsOutOfStockEnabled">
                    </label>
                    <div style="padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:var(--surface-soft)">
                        <div class="setting-check-title" style="margin-bottom:8px">การ Checkout เมื่อจำนวน &gt; 1</div>
                        <div style="display:flex;flex-direction:column;gap:6px">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                                <input type="radio" name="checkoutQtyMode" id="checkoutQtyMode1" value="1"> ทีละ 1 จำนวน <span style="font-size:11px;color:var(--muted)">(ค่าเริ่มต้น)</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                                <input type="radio" name="checkoutQtyMode" id="checkoutQtyMode2" value="2"> จบทั้งหมดในครั้งเดียว
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                                <input type="radio" name="checkoutQtyMode" id="checkoutQtyMode3" value="3"> เลือกจำนวนเอง
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-section">
                <div class="modal-section-title">กรอง SaleMode</div>
                <div class="filter-section">
                    <div class="filter-section-sub">เลือก SaleMode ที่ต้องการแสดงในสถานีนี้ — ไม่เลือก = แสดงทั้งหมด</div>
                    <div class="filter-chips" id="saleModeFilterChips"><span style="font-size:13px;color:var(--muted)">กำลังโหลด...</span></div>
                </div>
            </div>
            <div class="modal-section">
                <div class="modal-section-title">กรองโซน</div>
                <div class="filter-section">
                    <div class="filter-section-sub">เลือกโซนโต๊ะที่ต้องการแสดง — ไม่เลือก = แสดงทั้งหมด | รายการไม่มีโต๊ะ (Takeaway/Delivery) แสดงเสมอ</div>
                    <div class="filter-chips" id="zoneFilterChips"><span style="font-size:13px;color:var(--muted)">กำลังโหลด...</span></div>
                </div>
            </div>
            </div><!-- /tabPanelSystem -->

            <div id="tabPanelAppearance" class="tab-panel">
                <div class="modal-section">
                    <div class="modal-section-title">ขนาด Card</div>
                    <div class="appearance-btn-group">
                        <button class="appearance-btn" data-card="s">S</button>
                        <button class="appearance-btn" data-card="m">M</button>
                        <button class="appearance-btn" data-card="l">L</button>
                        <button class="appearance-btn" data-card="xl">XL</button>
                    </div>
                </div>
                <div class="modal-section">
                    <div class="modal-section-title">ขนาดตัวอักษร</div>
                    <div style="display:flex;align-items:center;gap:10px">
                        <span style="font-size:11px;color:var(--muted)">เล็ก</span>
                        <input type="range" id="fontScaleSlider" min="85" max="130" step="5" value="100" style="flex:1;accent-color:var(--primary)">
                        <span style="font-size:11px;color:var(--muted)">ใหญ่</span>
                        <span id="fontScaleLabel" style="font-size:12px;font-weight:bold;min-width:36px;text-align:right;color:var(--text)">100%</span>
                    </div>
                </div>
                <div style="margin-top:20px;text-align:right">
                    <button type="button" class="btn btn-neutral" id="resetAppearanceBtn">↺ คืนค่า Default</button>
                </div>
            </div><!-- /tabPanelAppearance -->
        </div><!-- /modal-body -->
        <div class="modal-foot">
            <button type="button" class="btn btn-neutral" id="timerSettingsCancelBtn">ยกเลิก</button>
            <button type="button" class="btn btn-success" id="timerSettingsSaveBtn">บันทึกค่าระบบ</button>
        </div>
    </div>

    <div id="notice" class="notice"></div>



    <div class="modal-backdrop" id="soldOutBackdrop"></div>
    <div class="modal" id="soldOutModal" role="dialog" aria-modal="true" aria-labelledby="soldOutTitle">
        <div class="modal-head">
            <h2 class="modal-title" id="soldOutTitle">🥫 ปิดสินค้าหมด</h2>
            <div class="modal-sub">เลือกสินค้าแล้วกดยืนยัน ระบบจะอัปเดตสถานะสินค้าหมดในระบบหลักทันที</div>
        </div>
        <div class="modal-body">
            <div class="modal-section">
                <input type="text" class="soldout-search" id="soldOutSearchInput" placeholder="ค้นหาจากรหัสสินค้า ชื่อสินค้า กลุ่ม หรือหมวดสินค้า">
                <div class="soldout-note">แสดงสินค้าในระบบหลัก สามารถกด <strong>ปิดสินค้าหมด</strong> หรือ <strong>เปิดขาย</strong> ได้จากหน้าจอนี้</div>
            </div>
            <div class="modal-section">
                <div class="soldout-list" id="soldOutList">
                    <div class="empty">ยังไม่ได้โหลดรายการสินค้า</div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-neutral" id="soldOutCloseBtn">ปิด</button>
        </div>
    </div>

    <div class="modal-backdrop" id="barcodeCameraBackdrop"></div>
    <div class="modal camera-modal" id="barcodeCameraModal" role="dialog" aria-modal="true" aria-labelledby="barcodeCameraTitle">
        <div class="modal-head">
            <h2 class="modal-title" id="barcodeCameraTitle">📷 สแกนบาร์โค้ดด้วยกล้อง</h2>
            <div class="modal-sub">ใช้กล้องหน้าเป็นค่าเริ่มต้น และสลับเป็นกล้องหลังได้ตามการใช้งาน</div>
        </div>
        <div class="modal-body">
            <div class="camera-wrap">
                <div class="camera-frame">
                    <video id="barcodeCameraVideo" class="camera-video" playsinline muted autoplay></video>
                    <canvas id="barcodeCameraCanvas" style="display:none"></canvas>
                    <div class="camera-overlay"><div class="camera-guide"></div></div>
                </div>
                <div class="camera-status" id="barcodeCameraStatus">กดเปิดกล้องแล้วหันไปที่บาร์โค้ดบนใบออเดอร์</div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-accent" id="switchBarcodeCameraBtn">🔄 สลับเป็นกล้องหลัง</button>
            <button type="button" class="btn btn-neutral" id="closeBarcodeCameraBtn">ปิด</button>
        </div>
    </div>

    <script>
        const refreshMs = <?php echo (int)APP_REFRESH_MS; ?>;
        const finishedRefreshEvery = <?php echo (int)FINISHED_REFRESH_EVERY; ?>;
        const recentFinishedLimit = <?php echo (int)RECENT_FINISHED_LIMIT; ?>;
        const defaultFinishStaffId = <?php echo (int)DEFAULT_FINISH_STAFF_ID; ?>;
        const currentComputerIdFromConfig = <?php echo defined('CURRENT_COMPUTER_ID') ? (int)CURRENT_COMPUTER_ID : 0; ?>;
        const barcodeCheckoutEnabled = <?php echo defined('ENABLE_BARCODE_CHECKOUT') && ENABLE_BARCODE_CHECKOUT ? 'true' : 'false'; ?>;
        const barcodeAutoSubmitDefault = <?php echo defined('BARCODE_AUTO_SUBMIT_DEFAULT') && BARCODE_AUTO_SUBMIT_DEFAULT ? 'true' : 'false'; ?>;
        const barcodeCameraEnabledDefault = <?php echo defined('BARCODE_CAMERA_ENABLED_DEFAULT') && BARCODE_CAMERA_ENABLED_DEFAULT ? 'true' : 'false'; ?>;
        const soundEnabledDefault = <?php echo defined('SOUND_ALERT_ENABLED_DEFAULT') && SOUND_ALERT_ENABLED_DEFAULT ? 'true' : 'false'; ?>;
        const kdsTwoStepCheckoutDefault = <?php echo defined('KDS_TWO_STEP_CHECKOUT_DEFAULT') && KDS_TWO_STEP_CHECKOUT_DEFAULT ? 'true' : 'false'; ?>;
        const thresholdYellowDefault = <?php echo defined('ALERT_THRESHOLD_YELLOW_DEFAULT') ? (int)ALERT_THRESHOLD_YELLOW_DEFAULT : 10; ?>;
        const thresholdRedDefault = <?php echo defined('ALERT_THRESHOLD_RED_DEFAULT') ? (int)ALERT_THRESHOLD_RED_DEFAULT : 20; ?>;
        const barcodeMediaSupported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        const barcodeCameraSupported = !!(window.BarcodeDetector && barcodeMediaSupported);
        var outOfStockControlEnabled = <?php echo defined('ENABLE_OUT_OF_STOCK_CONTROL') && ENABLE_OUT_OF_STOCK_CONTROL ? 'true' : 'false'; ?>;

        let isSubmitting = false;
        let noticeTimer = null;
        let activeRefreshTick = 0;
        var staffIsLoggedIn = false;   // var เพราะต้องแชร์ข้าม <script> block กับ staff login IIFE
        let _activeRowsLoading   = false;
        let _finishedRowsLoading = false;
        let _pollingErrorCount   = 0;
        var staffLoginHidden = false;  // true = ซ่อน topbar login, ใช้ finish_staff_id จาก settings แทน

        function applyStaffLoginMode(hidden) {
            staffLoginHidden = !!hidden;
            var loginArea  = document.getElementById('staffLoginArea');
            var loggedArea = document.getElementById('staffLoggedArea');
            if (staffLoginHidden) {
                staffIsLoggedIn = true;
                if (loginArea)  loginArea.style.display  = 'none';
                if (loggedArea) loggedArea.style.display = 'none';
            } else {
                // คืนสภาพให้ staff login IIFE จัดการ (ไม่ force show เพราะ IIFE มี state ของตัวเอง)
            }
        }

        function kdsLogActivity(actionType, detail, staffId) {
            const params = new URLSearchParams();
            params.set('action',      'log_activity');
            params.set('action_type', actionType);
            params.set('detail',      detail || '');
            params.set('staff_id',    String(staffId || 0));
            kdsApiFetch(_kdsBase + '/api_checker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString()
            }).catch(function(){});
        }

        const state = {
            stats: { active_rows: 0, active_qty: 0, recent_finished_rows: 0 },
            active_rows: [],
            recent_finished_rows: [],
            filters: { active_today_only: true, finished_today_only: true, current_computer_id: currentComputerIdFromConfig },
            finishedDrawerOpen: false,
            timerSettingsOpen: false,
            soldOutModalOpen: false,
            barcodeCameraOpen: false,
            systemSettingsLoaded: false,
            currentSystemSettings: null,
            saleModeChipsReady: false,
            zoneChipsReady: false,
            soldOutProducts: [],
            soldOutKeyword: '',
            kdsTwoStepCheckout: kdsTwoStepCheckoutDefault,
            checkoutQtyMode: 1,
            showOrderNumber: false
        };

        const barcodeCaptureState = {
            buffer: '',
            lastAt: 0,
            resetTimer: null,
            cameraStream: null,
            cameraDetector: null,
            cameraScanTimer: null,
            lastCameraValue: '',
            lastScanAt: 0,       // throttle — timestamp ของ frame ล่าสุดที่ scan จริง
            awaitingFreshScan: true,
            preferredFacingMode: 'user'
        };

        // ค่าเวลาแจ้งเตือน (โหลดจาก localStorage)
        const timerThresholds = {
            yellow: 10,
            red: 20
        };

        function getBarcodeAutoSubmitStorageKey() {
            return 'checker_barcode_auto_submit_' + String(currentComputerIdFromConfig || 0);
        }

        function getBarcodeCameraEnabledStorageKey() {
            return 'checker_barcode_camera_enabled_' + String(currentComputerIdFromConfig || 0);
        }

        function getBarcodeCameraFacingStorageKey() {
            return 'checker_barcode_camera_facing_' + String(currentComputerIdFromConfig || 0);
        }

        function getPreferredBarcodeFacingMode() {
            const raw = String(localStorage.getItem(getBarcodeCameraFacingStorageKey()) || '').trim();
            return raw === 'environment' ? 'environment' : 'user';
        }

        function savePreferredBarcodeFacingMode(value) {
            const next = value === 'environment' ? 'environment' : 'user';
            localStorage.setItem(getBarcodeCameraFacingStorageKey(), next);
            barcodeCaptureState.preferredFacingMode = next;
        }

        function getBarcodeFacingLabel(value) {
            return value === 'environment' ? 'กล้องหลัง' : 'กล้องหน้า';
        }

        function syncBarcodeCameraSwitchButton() {
            const btn = document.getElementById('switchBarcodeCameraBtn');
            if (!btn) return;
            const current = barcodeCaptureState.preferredFacingMode === 'environment' ? 'environment' : 'user';
            const next = current === 'environment' ? 'user' : 'environment';
            btn.textContent = '🔄 สลับเป็น' + getBarcodeFacingLabel(next);
        }

        function getBarcodeAutoSubmitEnabled() {
            return true;
        }

        function saveBarcodeAutoSubmit(value) {
            return true;
        }

        function getBarcodeCameraEnabled() {
            const raw = localStorage.getItem(getBarcodeCameraEnabledStorageKey());
            if (raw === null) return barcodeCameraEnabledDefault;
            return raw === '1';
        }

        function saveBarcodeCameraEnabled(value) {
            localStorage.setItem(getBarcodeCameraEnabledStorageKey(), value ? '1' : '0');
        }

        function clearBarcodeInput(resetBuffer) {
            const input = document.getElementById('barcodeInput');
            if (input) input.value = '';
            if (resetBuffer !== false) {
                resetGlobalBarcodeBuffer();
            } else {
                barcodeCaptureState.awaitingFreshScan = true;
            }
        }

        function applyBarcodeCameraAvailability() {
            const cameraBtn = document.getElementById('openBarcodeCameraBtn');
            const switchBtn = document.getElementById('switchBarcodeCameraBtn');
            const enabled = barcodeCheckoutEnabled && barcodeCameraSupported && getBarcodeCameraEnabled();
            if (cameraBtn) {
                cameraBtn.style.display = enabled ? '' : 'none';
                cameraBtn.disabled = !enabled;
                cameraBtn.title = barcodeCameraSupported ? '' : 'เบราว์เซอร์นี้ยังไม่รองรับสแกนด้วยกล้อง';
            }
            if (switchBtn) {
                switchBtn.disabled = !enabled;
                syncBarcodeCameraSwitchButton();
            }
        }

        function focusBarcodeInput(selectText) {
            if (state.barcodeCameraOpen) return;
            if (isMobile()) return; // มือถือไม่ focus — กัน keyboard เปิด
            if (!getBarcodeVisible()) return; // ซ่อนอยู่ → ไม่ focus
            const input = document.getElementById('barcodeInput');
            if (!input || !barcodeCheckoutEnabled) return;
            window.requestAnimationFrame(function() {
                // ไม่แย่ง focus ถ้า editable อื่น (เช่น staff login) กำลังใช้งานอยู่
                var active = document.activeElement;
                if (active && active !== input && isEditableElement(active)) return;
                try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
                if (selectText) {
                    try { input.select(); } catch (e) {}
                }
            });
        }

        function isEditableElement(el) {
            if (!el) return false;
            const tag = (el.tagName || '').toUpperCase();
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
            return !!el.isContentEditable;
        }

        function setBarcodeReadyHint(message, variant) {
            return;
        }

        function applyScannedBarcodeValue(value, shouldSubmit, keepFocus) {
            const input = document.getElementById('barcodeInput');
            if (!input) return;
            const clean = String(value || '').trim();
            resetGlobalBarcodeBuffer();
            barcodeCaptureState.buffer = clean;
            barcodeCaptureState.awaitingFreshScan = false;
            input.value = clean;
            if (shouldSubmit) {
                checkoutBarcode();
            } else if (keepFocus === true) {
                focusBarcodeInput(false);
            }
        }

        function resetGlobalBarcodeBuffer() {
            barcodeCaptureState.buffer = '';
            barcodeCaptureState.lastAt = 0;
            barcodeCaptureState.awaitingFreshScan = true;
            if (barcodeCaptureState.resetTimer) {
                clearTimeout(barcodeCaptureState.resetTimer);
                barcodeCaptureState.resetTimer = null;
            }
        }

        function queueGlobalBarcodeDigit(digit) {
            const now = Date.now();
            const isFreshScan = barcodeCaptureState.awaitingFreshScan || (now - barcodeCaptureState.lastAt > 500) || !barcodeCaptureState.buffer;
            if (isFreshScan) {
                const input = document.getElementById('barcodeInput');
                if (input) input.value = '';
                barcodeCaptureState.buffer = '';
                barcodeCaptureState.awaitingFreshScan = false;
            }
            barcodeCaptureState.lastAt = now;
            barcodeCaptureState.buffer += digit;
            const input = document.getElementById('barcodeInput');
            if (input) {
                input.value = barcodeCaptureState.buffer;
            }
            if (barcodeCaptureState.resetTimer) clearTimeout(barcodeCaptureState.resetTimer);
            barcodeCaptureState.resetTimer = setTimeout(function() {
                resetGlobalBarcodeBuffer();
            }, 1200);
        }

        const _barcodeVisibleKey = 'checker_barcode_visible_' + String(currentComputerIdFromConfig || 0);

        function getBarcodeVisible() {
            const v = localStorage.getItem(_barcodeVisibleKey);
            return v === null ? true : v === '1';
        }

        function applyBarcodeVisible(visible) {
            const tools = document.getElementById('barcodeTools');
            if (tools) tools.style.display = visible ? '' : 'none';
            if (visible) focusBarcodeInput();
        }

        // ── Appearance / Theme ──────────────────────────────────────────
        const _cardSizes = {s:'160px', m:'220px', l:'280px', xl:'360px'};
        const _appearanceKey = 'kds_appearance';

        function loadAppearanceSettings() {
            try { return JSON.parse(localStorage.getItem(_appearanceKey) || '{}'); } catch(e) { return {}; }
        }
        function saveAppearanceSettings(patch) {
            const cur = loadAppearanceSettings();
            localStorage.setItem(_appearanceKey, JSON.stringify(Object.assign(cur, patch)));
        }
        function applyAppearanceSettings() {
            const s = loadAppearanceSettings();
            document.documentElement.style.setProperty('--card-min-w', _cardSizes[s.card] || '220px');
            const pageEl = document.querySelector('.page');
            if (pageEl) pageEl.style.zoom = (parseInt(s.font || '100', 10) / 100);
        }
        function resetAppearanceSettings() {
            localStorage.removeItem(_appearanceKey);
            const pageEl = document.querySelector('.page');
            if (pageEl) pageEl.style.zoom = '';
            applyAppearanceSettings();
            syncAppearanceUI();
        }
        function syncAppearanceUI() {
            const s = loadAppearanceSettings();
            const card = s.card || 'm';
            const font = parseInt(s.font || '100', 10);
            document.querySelectorAll('[data-card]').forEach(function(b){
                b.classList.toggle('active', b.dataset.card === card);
            });
            const slider = document.getElementById('fontScaleSlider');
            const label  = document.getElementById('fontScaleLabel');
            if (slider) slider.value = font;
            if (label)  label.textContent = font + '%';
        }
        function initAppearancePanel() {
            document.querySelectorAll('[data-card]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    saveAppearanceSettings({card: btn.dataset.card});
                    applyAppearanceSettings();
                    syncAppearanceUI();
                });
            });
            const slider = document.getElementById('fontScaleSlider');
            if (slider) {
                slider.addEventListener('input', function() {
                    const v = slider.value;
                    const label = document.getElementById('fontScaleLabel');
                    if (label) label.textContent = v + '%';
                    saveAppearanceSettings({font: v});
                    applyAppearanceSettings();
                });
            }
            const resetBtn = document.getElementById('resetAppearanceBtn');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (!confirm('คืนค่าหน้าตาทั้งหมดกลับเป็น Default?')) return;
                    resetAppearanceSettings();
                });
            }
        }
        function initSettingsTabs() {
            const btnSystem     = document.getElementById('tabBtnSystem');
            const btnAppearance = document.getElementById('tabBtnAppearance');
            const panelSystem   = document.getElementById('tabPanelSystem');
            const panelAppearance = document.getElementById('tabPanelAppearance');
            const saveBtn = document.getElementById('timerSettingsSaveBtn');
            function switchTab(tab) {
                const isSystem = tab === 'system';
                btnSystem.classList.toggle('active', isSystem);
                btnAppearance.classList.toggle('active', !isSystem);
                panelSystem.classList.toggle('active', isSystem);
                panelAppearance.classList.toggle('active', !isSystem);
                if (saveBtn) saveBtn.style.display = isSystem ? '' : 'none';
                if (!isSystem) syncAppearanceUI();
            }
            btnSystem.addEventListener('click', function() { switchTab('system'); });
            btnAppearance.addEventListener('click', function() { switchTab('appearance'); });
            window._switchSettingsTab = switchTab;
        }
        // ── End Appearance ──────────────────────────────────────────────

        function initBarcodeSettings() {
            const input = document.getElementById('barcodeInput');
            const tools = document.getElementById('barcodeTools');
            barcodeCaptureState.preferredFacingMode = getPreferredBarcodeFacingMode();
            if (!barcodeCheckoutEnabled) {
                if (tools) tools.style.display = 'none';
                return;
            }
            applyBarcodeCameraAvailability();
            if (input) {
                input.setAttribute('autocapitalize', 'off');
                input.setAttribute('autocorrect', 'off');
                input.setAttribute('spellcheck', 'false');
            }
            applyBarcodeVisible(getBarcodeVisible());
        }

        async function switchBarcodeCameraFacing() {
            const next = barcodeCaptureState.preferredFacingMode === 'environment' ? 'user' : 'environment';
            savePreferredBarcodeFacingMode(next);
            syncBarcodeCameraSwitchButton();
            const status = document.getElementById('barcodeCameraStatus');
            if (status) status.textContent = 'กำลังสลับเป็น' + getBarcodeFacingLabel(next) + '...';
            if (state.barcodeCameraOpen) {
                const shouldRefocus = document.activeElement !== document.getElementById('barcodeInput');
                stopBarcodeCamera();
                await openBarcodeCamera();
                if (shouldRefocus) {
                    focusBarcodeInput();
                }
            }
        }

        function stopBarcodeCamera() {
            if (barcodeCaptureState.cameraScanTimer) {
                cancelAnimationFrame(barcodeCaptureState.cameraScanTimer);
                barcodeCaptureState.cameraScanTimer = null;
            }
            if (barcodeCaptureState.cameraStream) {
                barcodeCaptureState.cameraStream.getTracks().forEach(function(track) { try { track.stop(); } catch (e) {} });
                barcodeCaptureState.cameraStream = null;
            }
            const video = document.getElementById('barcodeCameraVideo');
            if (video) {
                try { video.pause(); } catch (e) {}
                video.srcObject = null;
            }
            const status = document.getElementById('barcodeCameraStatus');
            if (status) status.textContent = 'กดเปิด' + getBarcodeFacingLabel(barcodeCaptureState.preferredFacingMode) + 'แล้วหันไปที่บาร์โค้ดบนใบออเดอร์';
            barcodeCaptureState.lastCameraValue = '';
            syncBarcodeCameraSwitchButton();
            state.barcodeCameraOpen = false;
            const backdrop = document.getElementById('barcodeCameraBackdrop');
            const modal = document.getElementById('barcodeCameraModal');
            if (backdrop) backdrop.classList.remove('open');
            if (modal) modal.classList.remove('open');
            syncOverlayState();
        }

        async function scanBarcodeFrame() {
            if (!state.barcodeCameraOpen) return;

            const now = Date.now();
            // throttle: scan จริงทุก 200ms เท่านั้น ไม่ต้องทุก frame (60fps → ~5fps)
            if (now - barcodeCaptureState.lastScanAt < 200) {
                barcodeCaptureState.cameraScanTimer = requestAnimationFrame(scanBarcodeFrame);
                return;
            }
            barcodeCaptureState.lastScanAt = now;

            const video = document.getElementById('barcodeCameraVideo');
            const status = document.getElementById('barcodeCameraStatus');
            if (!video) return;

            let foundValue = null;

            // BarcodeDetector สำหรับบาร์โค้ดและ QR (บนอุปกรณ์ที่รองรับ)
            if (barcodeCaptureState.cameraDetector) {
                try {
                    const results = await barcodeCaptureState.cameraDetector.detect(video);
                    if (Array.isArray(results) && results.length) {
                        foundValue = String(results[0].rawValue || '').trim();
                    }
                } catch (e) {}
            }

            // jsQR fallback สำหรับ QR code บนอุปกรณ์ที่ BarcodeDetector ไม่รองรับ QR
            if (!foundValue && typeof jsQR === 'function' && video.readyState === video.HAVE_ENOUGH_DATA) {
                try {
                    const canvas = document.getElementById('barcodeCameraCanvas');
                    if (canvas) {
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
                        if (code && code.data) {
                            foundValue = String(code.data).trim();
                        }
                    }
                } catch (e) {}
            }

            if (foundValue && foundValue !== barcodeCaptureState.lastCameraValue) {
                barcodeCaptureState.lastCameraValue = foundValue;
                if (status) status.innerHTML = '<strong>พบโค้ด:</strong> ' + escapeHtml(foundValue);
                applyScannedBarcodeValue(foundValue, true);
                // continuous mode — ไม่ปิดกล้อง รอ 1.5 วิแล้วพร้อมรับโค้ดถัดไป
                setTimeout(function() {
                    barcodeCaptureState.lastCameraValue = '';
                    if (status && state.barcodeCameraOpen) status.textContent = 'พร้อมรับถัดไป หันไปที่บาร์โค้ดถัดไป';
                }, 1500);
            }

            barcodeCaptureState.cameraScanTimer = requestAnimationFrame(scanBarcodeFrame);
        }

        async function openBarcodeCamera() {
            if (state.barcodeCameraOpen) return;
            if (!getBarcodeCameraEnabled()) {
                showNotice('ปิดปุ่มสแกนกล้องอยู่ในตั้งค่า', 'error');
                return;
            }
            if (!barcodeMediaSupported) {
                showNotice('อุปกรณ์นี้ไม่รองรับการเปิดกล้องจากหน้าเว็บ', 'error');
                return;
            }
            if (!barcodeCameraSupported) {
                showNotice('เบราว์เซอร์นี้ยังไม่รองรับการอ่านบาร์โค้ดจากกล้อง', 'error');
                return;
            }
            const backdrop = document.getElementById('barcodeCameraBackdrop');
            const modal = document.getElementById('barcodeCameraModal');
            const video = document.getElementById('barcodeCameraVideo');
            const status = document.getElementById('barcodeCameraStatus');
            try {
                clearBarcodeInput();
                barcodeCaptureState.lastCameraValue = '';
                barcodeCaptureState.cameraDetector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf', 'codabar', 'qr_code'] });
                const preferredFacingMode = barcodeCaptureState.preferredFacingMode === 'environment' ? 'environment' : 'user';
                let stream = null;
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: { ideal: preferredFacingMode },
                            width: { ideal: 1280 },
                            height: { ideal: 720 }
                        },
                        audio: false
                    });
                } catch (primaryError) {
                    const fallbackFacingMode = preferredFacingMode === 'environment' ? 'user' : 'environment';
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: { ideal: fallbackFacingMode },
                            width: { ideal: 1280 },
                            height: { ideal: 720 }
                        },
                        audio: false
                    });
                    savePreferredBarcodeFacingMode(fallbackFacingMode);
                }
                barcodeCaptureState.cameraStream = stream;
                if (video) {
                    video.srcObject = stream;
                    await video.play();
                }
                if (backdrop) backdrop.classList.add('open');
                if (modal) modal.classList.add('open');
                state.barcodeCameraOpen = true;
                syncBarcodeCameraSwitchButton();
                if (status) status.textContent = getBarcodeFacingLabel(barcodeCaptureState.preferredFacingMode) + 'พร้อมแล้ว หันไปที่บาร์โค้ดเพื่อเช็คเอาต์อัตโนมัติ';
                scanBarcodeFrame();
            } catch (error) {
                stopBarcodeCamera();
                const msg = (error && error.name === 'NotAllowedError')
                    ? 'ยังไม่ได้อนุญาตให้ใช้กล้อง'
                    : 'ไม่สามารถเปิดกล้องได้';
                showNotice(msg, 'error');
            }
        }

        // ── เสียงแจ้งเตือน ──
        const soundSettings = {
            enabled: false,
            audioBuffer: null,
            cancelAudioBuffer: null,
            audioCtx: null,
            lastKnownProcessIds: null
        };

        const _serverSoundFiles = [
            _kdsBase + '/assets/sounds/alert.wav?v=<?php echo defined("APP_VERSION") ? h(APP_VERSION) : "1"; ?>'
        ];

        const _serverCancelSoundFiles = [
            _kdsBase + '/assets/sounds/cancel.wav?v=<?php echo defined("APP_VERSION") ? h(APP_VERSION) : "1"; ?>'
        ];

        function getAudioCtx() {
            if (!soundSettings.audioCtx) {
                soundSettings.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            return soundSettings.audioCtx;
        }

        function playAlertSound() {
            if (!soundSettings.enabled || !soundSettings.audioBuffer) return;
            try {
                const ctx = getAudioCtx();
                if (ctx.state === 'suspended') ctx.resume();
                const src = ctx.createBufferSource();
                src.buffer = soundSettings.audioBuffer;
                src.connect(ctx.destination);
                src.start(0);
            } catch(e) { console.warn('playAlertSound error', e); }
        }

        function playCancelSound() {
            if (!soundSettings.enabled || !soundSettings.cancelAudioBuffer) return;
            try {
                const ctx = getAudioCtx();
                if (ctx.state === 'suspended') ctx.resume();
                const src = ctx.createBufferSource();
                src.buffer = soundSettings.cancelAudioBuffer;
                src.connect(ctx.destination);
                src.start(0);
            } catch(e) { console.warn('playCancelSound error', e); }
        }

        function checkForNewOrders(newRows) {
            const newIds = new Set(newRows.map(r => String(r.ProcessID)));
            if (soundSettings.lastKnownProcessIds === null) {
                // ครั้งแรก: บันทึกไว้แต่ไม่ดังเสียง
                soundSettings.lastKnownProcessIds = newIds;
                return;
            }
            let hasNew = false;
            newIds.forEach(id => {
                if (!soundSettings.lastKnownProcessIds.has(id)) hasNew = true;
            });
            soundSettings.lastKnownProcessIds = newIds;
            if (hasNew) playAlertSound();
        }

function initSoundSettings() {
            const rawEnabled = localStorage.getItem('checker_sound_enabled');
            const enabled = rawEnabled === null ? !!soundEnabledDefault : rawEnabled === '1';
            soundSettings.enabled = enabled;
            document.getElementById('soundEnabled').checked = enabled;
            document.getElementById('soundEnabledLabel').textContent = enabled ? 'เปิดอยู่' : 'ปิดอยู่';
            loadServerSound();
        }

        function initSoundUI() {
            const enabledChk = document.getElementById('soundEnabled');
            const fileInput = document.getElementById('soundFileInput');

            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const ab = ev.target.result;
                    _decodeSoundBuffer(ab.slice(0), function(buf) {
                        soundSettings.audioBuffer = buf;
                        _setSoundLoaded('alert', file.name + ' (อัปโหลด)');
                        saveSoundToCache(ab, file.name, 'alert');
                    }, function() { showNotice('ไม่สามารถโหลดไฟล์เสียงนี้ได้', 'error'); });
                };
                reader.readAsArrayBuffer(file);
            });

            document.getElementById('soundPreviewBtn').addEventListener('click', function() {
                if (soundSettings.audioBuffer) {
                    const tmp = soundSettings.enabled;
                    soundSettings.enabled = true;
                    playAlertSound();
                    soundSettings.enabled = tmp;
                }
            });

            const cancelFileInput = document.getElementById('soundCancelFileInput');
            if (cancelFileInput) {
                cancelFileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const ab = ev.target.result;
                        getAudioCtx().decodeAudioData(ab.slice(0), function(buf) {
                            soundSettings.cancelAudioBuffer = buf;
                            _setSoundLoaded('cancel', file.name + ' (อัปโหลด)');
                            saveSoundToCache(ab, file.name, 'cancel');
                        }, function() { showNotice('ไม่สามารถโหลดไฟล์เสียงนี้ได้', 'error'); });
                    };
                    reader.readAsArrayBuffer(file);
                });
            }

            const cancelPreviewBtn = document.getElementById('soundCancelPreviewBtn');
            if (cancelPreviewBtn) {
                cancelPreviewBtn.addEventListener('click', function() {
                    if (soundSettings.cancelAudioBuffer) {
                        const tmp = soundSettings.enabled;
                        soundSettings.enabled = true;
                        playCancelSound();
                        soundSettings.enabled = tmp;
                    }
                });
            }

            enabledChk.addEventListener('change', function() {
                document.getElementById('soundEnabledLabel').textContent = this.checked ? 'เปิดอยู่' : 'ปิดอยู่';
            });
        }

        function _setSoundLoaded(key, label) {
            // key: 'alert' or 'cancel'
            const isCancel = key === 'cancel';
            const fileNameSpan = document.getElementById(isCancel ? 'soundCancelFileName' : 'soundFileName');
            const previewBtn   = document.getElementById(isCancel ? 'soundCancelPreviewBtn' : 'soundPreviewBtn');
            const uploadRow    = document.getElementById(isCancel ? 'soundCancelUploadRow' : 'soundUploadRow');
            if (fileNameSpan) { fileNameSpan.textContent = '✅ ' + label; fileNameSpan.className = 'sound-name loaded'; }
            if (previewBtn) previewBtn.disabled = false;
            if (uploadRow) uploadRow.style.display = 'none';
        }

        function _showUploadUI(key, msg) {
            // key: 'alert' or 'cancel'
            const isCancel = key === 'cancel';
            const fileNameSpan = document.getElementById(isCancel ? 'soundCancelFileName' : 'soundFileName');
            const previewBtn   = document.getElementById(isCancel ? 'soundCancelPreviewBtn' : 'soundPreviewBtn');
            const uploadRow    = document.getElementById(isCancel ? 'soundCancelUploadRow' : 'soundUploadRow');
            if (fileNameSpan) { fileNameSpan.textContent = msg; fileNameSpan.className = 'sound-name'; }
            if (previewBtn) previewBtn.disabled = true;
            if (uploadRow) uploadRow.style.display = '';
        }

        function _decodeSoundBuffer(ab, onSuccess, onError) {
            const ctx = getAudioCtx();
            ctx.decodeAudioData(ab, function(buf) {
                if (soundSettings.lastKnownProcessIds === null) {
                    soundSettings.lastKnownProcessIds = new Set((state.active_rows || []).map(function(r) { return String(r.ProcessID); }));
                }
                onSuccess(buf);
            }, onError || function() {});
        }

        // ── IndexedDB cache สำหรับไฟล์ที่ upload ──
        function _openSoundDB() {
            return new Promise(function(res, rej) {
                const req = indexedDB.open('checker_sound_db', 1);
                req.onupgradeneeded = function(e) { e.target.result.createObjectStore('sounds'); };
                req.onsuccess = function(e) { res(e.target.result); };
                req.onerror = function(e) { rej(e); };
            });
        }

        async function saveSoundToCache(arrayBuffer, name, key) {
            key = key || 'alert';
            try {
                const db = await _openSoundDB();
                const tx = db.transaction('sounds', 'readwrite');
                tx.objectStore('sounds').put({ buffer: arrayBuffer, name: name }, key);
            } catch(e) { console.warn('saveSoundToCache', e); }
        }

        async function loadCachedSound(key, onBuf, onLabel, onNotFound) {
            key = key || 'alert';
            const notFoundMsg = 'ไม่พบไฟล์เสียง — เลือกไฟล์จากเครื่องได้เลย';
            const corruptMsg  = 'ไฟล์เสียงที่บันทึกไว้เสียหาย — เลือกใหม่ได้เลย';
            try {
                const db = await _openSoundDB();
                const tx = db.transaction('sounds', 'readonly');
                const req = tx.objectStore('sounds').get(key);
                req.onsuccess = function() {
                    const rec = req.result;
                    if (!rec) { if (onNotFound) onNotFound(notFoundMsg); else _showUploadUI(key, notFoundMsg); return; }
                    const decodeOk = function(buf) {
                        if (onBuf) onBuf(buf);
                        if (onLabel) onLabel(rec.name + ' (บันทึกไว้)');
                        else _setSoundLoaded(key, rec.name + ' (บันทึกไว้)');
                    };
                    const decodeErr = function() { if (onNotFound) onNotFound(corruptMsg); else _showUploadUI(key, corruptMsg); };
                    if (key === 'alert') {
                        _decodeSoundBuffer(rec.buffer.slice(0), decodeOk, decodeErr);
                    } else {
                        getAudioCtx().decodeAudioData(rec.buffer.slice(0), decodeOk, decodeErr);
                    }
                };
                req.onerror = function() { if (onNotFound) onNotFound(notFoundMsg); else _showUploadUI(key, notFoundMsg); };
            } catch(e) { if (onNotFound) onNotFound(notFoundMsg); else _showUploadUI(key, notFoundMsg); }
        }

        // ── โหลดเสียงจาก Server → IndexedDB → Upload ──
        async function loadServerSound() {
            // --- alert sound ---
            let loaded = false;
            for (const url of _serverSoundFiles) {
                try {
                    const response = await fetch(url, { cache: 'no-cache' });
                    if (!response.ok) continue;
                    const arrayBuffer = await response.arrayBuffer();
                    _decodeSoundBuffer(arrayBuffer, function(buf) {
                        soundSettings.audioBuffer = buf;
                        const fname = url.split('/').pop().split('?')[0];
                        _setSoundLoaded('alert', fname + ' (Server)');
                    }, function(err) { console.warn('decodeAudioData alert failed', err); });
                    loaded = true;
                    break;
                } catch(e) { console.warn('loadServerSound alert failed:', url, e); }
            }
            if (!loaded) {
                loadCachedSound('alert', function(buf) { soundSettings.audioBuffer = buf; }, null, function(msg) { _showUploadUI('alert', msg); });
            }

            // --- cancel sound ---
            let cancelLoaded = false;
            for (const url of _serverCancelSoundFiles) {
                try {
                    const response = await fetch(url, { cache: 'no-cache' });
                    if (!response.ok) continue;
                    const arrayBuffer = await response.arrayBuffer();
                    getAudioCtx().decodeAudioData(arrayBuffer, function(buf) {
                        soundSettings.cancelAudioBuffer = buf;
                        const fname = url.split('/').pop().split('?')[0];
                        _setSoundLoaded('cancel', fname + ' (Server)');
                    }, function(err) { console.warn('decodeAudioData cancel failed', err); });
                    cancelLoaded = true;
                    break;
                } catch(e) { console.warn('loadServerSound cancel failed:', url, e); }
            }
            if (!cancelLoaded) {
                loadCachedSound('cancel', function(buf) { soundSettings.cancelAudioBuffer = buf; }, null, function(msg) { _showUploadUI('cancel', msg); });
            }
        }

        function initTimerThresholds() {
            timerThresholds.yellow = thresholdYellowDefault > 0 ? thresholdYellowDefault : 10;
            timerThresholds.red = thresholdRedDefault > 0 ? thresholdRedDefault : 20;
            const y = Number(localStorage.getItem('checker_threshold_yellow'));
            const r = Number(localStorage.getItem('checker_threshold_red'));
            if (y > 0) timerThresholds.yellow = y;
            if (r > 0) timerThresholds.red = r;
            document.getElementById('thresholdYellow').value = timerThresholds.yellow;
            document.getElementById('thresholdRed').value = timerThresholds.red;
        }

        async function openTimerSettings() {
            document.getElementById('systemSettingsStatusBox').className = 'settings-status';
            document.getElementById('systemSettingsStatusBox').textContent = '';
            if (window._switchSettingsTab) window._switchSettingsTab('system');
            state.timerSettingsOpen = true;
            state.saleModeChipsReady = false;
            state.zoneChipsReady = false;
            syncTimerSettingsState();
            try {
                const [settingsRes, saleModesRes, zonesRes, shopsRes] = await Promise.all([
                    kdsApiFetch(_kdsBase + '/api_checker.php?action=get_system_settings&_=' + Date.now(), { cache: 'no-store' }),
                    kdsApiFetch(_kdsBase + '/api_checker.php?action=list_sale_modes&_=' + Date.now(), { cache: 'no-store' }),
                    kdsApiFetch(_kdsBase + '/api_checker.php?action=list_zones&_=' + Date.now(), { cache: 'no-store' }),
                    kdsApiFetch(_kdsBase + '/api_checker.php?action=list_shops&_=' + Date.now(), { cache: 'no-store' }),
                ]);
                const data = await settingsRes.json();
                if (!settingsRes.ok || !data.success) {
                    throw new Error(data.error || 'โหลดค่าระบบไม่สำเร็จ');
                }
                const saleModesData = saleModesRes.ok ? await saleModesRes.json() : {};
                const zonesData = zonesRes.ok ? await zonesRes.json() : {};
                const shopsData = shopsRes.ok ? await shopsRes.json() : {};
                const saleModesFetched = saleModesRes.ok && saleModesData.success;
                const zonesFetched = zonesRes.ok && zonesData.success;
                const saleModes = (saleModesFetched && Array.isArray(saleModesData.sale_modes)) ? saleModesData.sale_modes : [];
                const zones = (zonesFetched && Array.isArray(zonesData.zones)) ? zonesData.zones : [];
                renderFilterChips('saleModeFilterChips', saleModes, 'sale_mode_id', 'sale_mode_name');
                renderFilterChips('zoneFilterChips', zones, 'zoneid', 'zonename');
                state.saleModeChipsReady = saleModesFetched;
                state.zoneChipsReady = zonesFetched;
                if (shopsData.success && Array.isArray(shopsData.shops)) {
                    populateShopDropdown(shopsData.shops, Number((data.settings || {}).shop_id || 0));
                }
                applySystemSettingsToModal(data.settings || {}, data.staff_name || '', data.connection_message || '');
                state.systemSettingsLoaded = true;
                state.currentSystemSettings = data.settings || {};
            } catch (error) {
                showSystemSettingsStatus(error.message || 'โหลดค่าระบบไม่สำเร็จ', 'error');
            }
        }

        function renderFilterChips(containerId, items, idKey, nameKey) {
            const el = document.getElementById(containerId);
            if (!el) return;
            if (!items || items.length === 0) {
                el.innerHTML = '<span style="font-size:13px;color:var(--muted)">ไม่พบข้อมูล</span>';
                return;
            }
            el.innerHTML = items.map(function(item) {
                const id = item[idKey];
                const name = String(item[nameKey] || '');
                return '<label class="filter-chip" data-id="' + id + '">' +
                    '<input type="checkbox" value="' + id + '">' + name + '</label>';
            }).join('');
            el.querySelectorAll('.filter-chip').forEach(function(chip) {
                chip.addEventListener('click', function(e) {
                    e.preventDefault(); // กัน browser toggle checkbox ซ้ำ (label + synthetic click)
                    const cb = chip.querySelector('input[type=checkbox]');
                    if (!cb) return;
                    cb.checked = !cb.checked;
                    chip.classList.toggle('active', cb.checked);
                });
            });
        }

        function setFilterChips(containerId, selectedIds) {
            const el = document.getElementById(containerId);
            if (!el) return;
            const selected = new Set((selectedIds || []).map(Number));
            el.querySelectorAll('.filter-chip').forEach(function(chip) {
                const id = Number(chip.getAttribute('data-id'));
                const cb = chip.querySelector('input[type=checkbox]');
                const active = selected.has(id);
                if (cb) cb.checked = active;
                chip.classList.toggle('active', active);
            });
        }

        function getFilterChipValues(containerId) {
            const el = document.getElementById(containerId);
            if (!el) return [];
            const ids = [];
            el.querySelectorAll('.filter-chip input[type=checkbox]:checked').forEach(function(cb) {
                const id = parseInt(cb.value, 10);
                if (id > 0) ids.push(id);
            });
            return ids;
        }

        function closeTimerSettings() {
            state.timerSettingsOpen = false;
            syncTimerSettingsState();
        }

        async function saveTimerSettings() {
            const payload = collectSystemSettingsFromModal();
            if (payload.hide_staff_login === 1 && !(payload.finish_staff_id > 0)) {
                showSystemSettingsStatus('กรุณาตั้งค่า Staff Code ก่อนเปิดใช้งาน "ซ่อนช่อง Login พนักงาน"', 'error');
                return;
            }
            const saveBtn = document.getElementById('timerSettingsSaveBtn');
            if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'กำลังบันทึก...'; }
            try {
                const response = await kdsApiFetch(_kdsBase + '/api_checker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: buildSystemSettingsParams('save_system_settings', payload).toString()
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'บันทึกค่าระบบไม่สำเร็จ');
                }
                applySavedSystemSettingsToRuntime(payload, data.staff_name || '');
                showSystemSettingsStatus(data.message || 'บันทึกค่าระบบเรียบร้อยแล้ว', 'success');
                showNotice(data.message || 'บันทึกค่าระบบเรียบร้อยแล้ว', 'success');
                if (typeof data.machine_display_name === 'string') {
                    updateMachineDisplayName(data.machine_display_name);
                }
                setTimeout(function() { window.location.reload(); }, 700);
            } catch (error) {
                showSystemSettingsStatus(error.message || 'บันทึกค่าระบบไม่สำเร็จ', 'error');
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'บันทึกค่าระบบ'; }
            }
        }

        function syncOverlayState() {
            const hasOverlay = !!state.timerSettingsOpen || !!state.finishedDrawerOpen || !!state.barcodeCameraOpen || !!state.soldOutModalOpen;
            document.body.classList.toggle('drawer-open', hasOverlay);
        }

        function syncTimerSettingsState() {
            const modal    = document.getElementById('timerSettingsModal');
            const backdrop = document.getElementById('timerSettingsBackdrop');
            const isOpen   = !!state.timerSettingsOpen;
            modal.classList.toggle('open', isOpen);
            backdrop.classList.toggle('open', isOpen);
            syncOverlayState();
        }

        function openSoldOutModal() {
            state.soldOutModalOpen = true;
            syncSoldOutModalState();
            loadSoldOutProducts(true);
            const input = document.getElementById('soldOutSearchInput');
            if (input) {
                input.value = state.soldOutKeyword || '';
                setTimeout(function(){ try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); } }, 30);
            }
        }

        function closeSoldOutModal() {
            state.soldOutModalOpen = false;
            syncSoldOutModalState();
            focusBarcodeInput();
        }

        function syncSoldOutModalState() {
            const modal = document.getElementById('soldOutModal');
            const backdrop = document.getElementById('soldOutBackdrop');
            const isOpen = !!state.soldOutModalOpen;
            if (modal) modal.classList.toggle('open', isOpen);
            if (backdrop) backdrop.classList.toggle('open', isOpen);
            syncOverlayState();
        }

        async function loadSoldOutProducts(force) {
            if (!outOfStockControlEnabled) return;
            const list = document.getElementById('soldOutList');
            const q = state.soldOutKeyword || '';
            if (list) list.innerHTML = '<div class="empty">กำลังโหลดรายการสินค้า...</div>';
            try {
                const response = await kdsApiFetch(_kdsBase + '/api_checker.php?action=list_out_of_stock_products&q=' + encodeURIComponent(q) + '&_=' + Date.now(), { cache: 'no-store' });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'โหลดรายการสินค้าไม่สำเร็จ');
                }
                state.soldOutProducts = Array.isArray(data.rows) ? data.rows : [];
                if (!state.soldOutModalOpen) return;
                renderSoldOutProducts(state.soldOutProducts);
            } catch (error) {
                if (list) list.innerHTML = '<div class="empty">โหลดรายการสินค้าไม่สำเร็จ</div>';
                showNotice(error.message || 'โหลดรายการสินค้าไม่สำเร็จ', 'error');
            }
        }

        function renderSoldOutProducts(rows) {
            const list = document.getElementById('soldOutList');
            if (!list) return;
            if (!rows || !rows.length) {
                list.innerHTML = '<div class="empty">ไม่พบรายการสินค้า</div>';
                return;
            }
            list.innerHTML = rows.map(function(row){
                const closed = Number(row.IsOutOfStock || 0) === 1;
                const code = row.ProductCode ? escapeHtml(row.ProductCode) : ('#' + escapeHtml(row.ProductID));
                const name = escapeHtml(row.ProductName || row.ProductName1 || ('สินค้า #' + row.ProductID));
                const rawName = String(row.ProductName || row.ProductName1 || ('สินค้า #' + row.ProductID));
                const dept = escapeHtml(row.ProductDeptName || '-');
                const group = escapeHtml(row.ProductGroupName || '-');
                const actionLabel = closed ? 'เปิดขาย' : 'ปิดสินค้าหมด';
                const actionClass = closed ? 'btn btn-neutral btn-small' : 'btn btn-danger btn-small';
                const nextState = closed ? 0 : 1;
                const confirmText = closed ? ('ยืนยันเปิดขาย ' + rawName + ' อีกครั้ง?') : ('ยืนยันปิดสินค้าหมด ' + rawName + ' ?');
                return '<div class="soldout-item ' + (closed ? 'closed' : '') + '">' +
                    '<div>' +
                        '<div class="soldout-meta">' +
                            '<span class="soldout-chip">' + code + '</span>' +
                            '<span class="soldout-chip">' + group + '</span>' +
                            '<span class="soldout-chip">' + dept + '</span>' +
                            '<span class="soldout-chip ' + (closed ? 'closed' : '') + '">' + (closed ? 'ปิดอยู่' : 'เปิดขายอยู่') + '</span>' +
                        '</div>' +
                        '<div class="soldout-name">' + name + '</div>' +
                        '<div class="soldout-sub">Product ID: ' + escapeHtml(row.ProductID) + '</div>' +
                    '</div>' +
                    '<div class="soldout-actions">' +
                        '<button type="button" class="' + actionClass + ' js-toggle-outofstock" data-product-id="' + Number(row.ProductID || 0) + '" data-next-state="' + nextState + '" data-product-name="' + escapeHtml(rawName) + '" data-confirm-text="' + escapeHtml(confirmText) + '">' + actionLabel + '</button>' +
                    '</div>' +
                '</div>';
            }).join('');
        }

        async function toggleOutOfStock(productId, isOutOfStock, productName, confirmText) {
            if (!outOfStockControlEnabled || isSubmitting) return;
            if (!window.confirm(confirmText || 'ยืนยันทำรายการนี้?')) return;
            isSubmitting = true;
            setStatusText(isOutOfStock ? 'กำลังปิดสินค้าหมด...' : 'กำลังเปิดขายสินค้า...');
            try {
                const params = new URLSearchParams();
                params.set('action', 'set_product_out_of_stock');
                params.set('product_id', productId);
                params.set('is_out_of_stock', isOutOfStock ? '1' : '0');
                params.set('update_by', getFinishStaffId());
                const response = await kdsApiFetch(_kdsBase + '/api_checker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'อัปเดตสินค้าหมดไม่สำเร็จ');
                }
                showNotice(data.message || 'อัปเดตสถานะสินค้าแล้ว', 'success');
                closeSoldOutModal();
            } catch (error) {
                showNotice(error.message || 'อัปเดตสินค้าหมดไม่สำเร็จ', 'error');
            } finally {
                isSubmitting = false;
                setStatusText('พร้อมใช้งาน');
                loadSoldOutProducts(true);
            }
        }

        function showSystemSettingsStatus(message, type) {
            const box = document.getElementById('systemSettingsStatusBox');
            if (!box) return;
            const variant = type === 'error' ? 'error' : 'success';
            box.className = 'settings-status show ' + variant;
            box.textContent = String(message || '');
        }

        function updateMachineDisplayName(name) {
            const el = document.getElementById('machineDisplayName');
            if (!el) return;
            const clean = String(name || '').trim();
            el.textContent = clean;
            el.style.display = clean ? 'block' : 'none';
        }

        function setStaffNameBox(name) {
            const box = document.getElementById('settingsStaffNameBox');
            if (!box) return;
            const clean = String(name || '').trim();
            box.textContent = clean ? clean : 'ไม่พบข้อมูลพนักงาน';
            box.className = 'settings-status show ' + (clean ? 'success' : 'error');
        }

        function applySystemSettingsToModal(settings, staffName, connectionMessage) {
            document.getElementById('settingsDbHost').value = String(settings.db_host || '');
            // db_port locked to 3307 — no UI field
            document.getElementById('settingsDbName').value = String(settings.db_name || '');
            document.getElementById('settingsComputerId').value = Number(settings.current_computer_id || 0) || '';
            setComputerNameBox(String(settings.current_computer_name || ''));
            if (!settings.current_computer_name && Number(settings.current_computer_id || 0) > 0) {
                lookupComputerNameForSettings();
            }
            const shopSelEl = document.getElementById('settingsShopId');
            if (shopSelEl) shopSelEl.value = Number(settings.shop_id || 0) || 0;
            // update shop name box
            const shopNameEl = document.getElementById('settingsShopNameBox');
            if (shopSelEl && shopNameEl) {
                const selOpt = shopSelEl.options[shopSelEl.selectedIndex];
                if (selOpt && parseInt(selOpt.value) > 0) {
                    const parts = selOpt.textContent.split(' — ');
                    shopNameEl.textContent = parts.length > 1 ? parts[1] : selOpt.textContent;
                }
            }
            // ใช้ค่าจาก localStorage ก่อน (อาจ login จาก topbar) ถ้าไม่มีค่อยใช้จาก settings
            const localStaffId = Number(localStorage.getItem('checker_finish_staff_id') || 0);
            document.getElementById('settingsFinishStaffId').value = (localStaffId > 0 ? localStaffId : Number(settings.finish_staff_id || 0)) || '';
            document.getElementById('thresholdYellow').value = Number(settings.threshold_yellow || thresholdYellowDefault || 10);
            document.getElementById('thresholdRed').value = Number(settings.threshold_red || thresholdRedDefault || 20);
            document.getElementById('soundEnabled').checked = Number(settings.sound_enabled || 0) === 1;
            document.getElementById('soundEnabledLabel').textContent = document.getElementById('soundEnabled').checked ? 'เปิดอยู่' : 'ปิดอยู่';
            const twoStepInput = document.getElementById('kdsTwoStepCheckout');
            if (twoStepInput) twoStepInput.checked = Number(settings.kds_two_step_checkout || 0) === 1;
            const outOfStockInput = document.getElementById('kdsOutOfStockEnabled');
            if (outOfStockInput) outOfStockInput.checked = settings.out_of_stock_enabled !== 0;
            const qtyModeInput = document.getElementById('checkoutQtyMode' + (Number(settings.checkout_qty_mode || 1)));
            if (qtyModeInput) qtyModeInput.checked = true;
            state.checkoutQtyMode = Number(settings.checkout_qty_mode || 1);
            const hideStaffInput = document.getElementById('hideStaffLogin');
            if (hideStaffInput) hideStaffInput.checked = Number(settings.hide_staff_login || 0) === 1;
            const voidConfirmInput = document.getElementById('voidConfirmMode');
            if (voidConfirmInput) voidConfirmInput.checked = Number(settings.void_confirm_mode || 0) === 1;
            const activeRowsTodayInput = document.getElementById('activeRowsTodayOnly');
            if (activeRowsTodayInput) activeRowsTodayInput.checked = Number(settings.active_rows_today_only ?? 1) === 1;
            const showOrderNumberInput = document.getElementById('showOrderNumber');
            if (showOrderNumberInput) showOrderNumberInput.checked = Number(settings.show_order_number || 0) === 1;
            const barcodeScanVisibleInput = document.getElementById('barcodeScanVisible');
            if (barcodeScanVisibleInput) barcodeScanVisibleInput.checked = getBarcodeVisible();
            const cameraEnabledInput = document.getElementById('barcodeCameraEnabled');
            if (cameraEnabledInput) {
                cameraEnabledInput.checked = Number(settings.barcode_camera_enabled || 0) === 1;
                cameraEnabledInput.disabled = !barcodeCameraSupported;
            }
            const dbUserHint = document.getElementById('dbUserHint');
            if (dbUserHint) {
                dbUserHint.textContent = 'User: ' + String(settings.db_user_hint || '-');
            }
            setStaffNameBox(staffName);
            if (connectionMessage) {
                showSystemSettingsStatus(connectionMessage, connectionMessage.indexOf('เชื่อมต่อ') === 0 ? 'success' : 'error');
            }
            setFilterChips('saleModeFilterChips', settings.allowed_sale_mode_ids || []);
            setFilterChips('zoneFilterChips', settings.allowed_zone_ids || []);
        }

        function collectSystemSettingsFromModal() {
            return {
                db_host: String(document.getElementById('settingsDbHost').value || '').trim(),
                db_port: Number(document.getElementById('settingsDbPort').value || 0),
                db_name: String(document.getElementById('settingsDbName').value || '').trim(),
                current_computer_id: Number(document.getElementById('settingsComputerId').value || 0),
                current_computer_name: String(document.getElementById('settingsComputerName').value || '').trim(),
                shop_id:         Number(document.getElementById('settingsShopId').value || 0),
                finish_staff_id: Number(document.getElementById('settingsFinishStaffId').value || 0),
                threshold_yellow: Number(document.getElementById('thresholdYellow').value || 0),
                threshold_red: Number(document.getElementById('thresholdRed').value || 0),
                sound_enabled: document.getElementById('soundEnabled').checked ? 1 : 0,
                barcode_scan_visible: (document.getElementById('barcodeScanVisible') || {}).checked ? 1 : 0,
                barcode_camera_enabled: document.getElementById('barcodeCameraEnabled').checked ? 1 : 0,
                kds_two_step_checkout: document.getElementById('kdsTwoStepCheckout').checked ? 1 : 0,
                out_of_stock_enabled: (document.getElementById('kdsOutOfStockEnabled') || {}).checked ? 1 : 0,
                hide_staff_login: (document.getElementById('hideStaffLogin') || {}).checked ? 1 : 0,
                void_confirm_mode: (document.getElementById('voidConfirmMode') || {}).checked ? 1 : 0,
                active_rows_today_only: (document.getElementById('activeRowsTodayOnly') || {}).checked ? 1 : 0,
                show_order_number: (document.getElementById('showOrderNumber') || {}).checked ? 1 : 0,
                checkout_qty_mode: Number((document.querySelector('input[name="checkoutQtyMode"]:checked') || {value: 1}).value || 1),
                allowed_sale_mode_ids: state.saleModeChipsReady ? getFilterChipValues('saleModeFilterChips') : ((state.currentSystemSettings && state.currentSystemSettings.allowed_sale_mode_ids) || []),
                allowed_zone_ids: state.zoneChipsReady ? getFilterChipValues('zoneFilterChips') : ((state.currentSystemSettings && state.currentSystemSettings.allowed_zone_ids) || []),
            };
        }

        function buildSystemSettingsParams(action, payload) {
            const params = new URLSearchParams();
            params.set('action', action);
            Object.keys(payload).forEach(function(key) {
                params.set(key, String(payload[key]));
            });
            return params;
        }

        async function testSystemSettingsConnection() {
            const payload = collectSystemSettingsFromModal();
            showSystemSettingsStatus('กำลังทดสอบการเชื่อมต่อ...', 'success');
            try {
                const response = await kdsApiFetch(_kdsBase + '/api_checker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: buildSystemSettingsParams('test_system_settings_connection', payload).toString()
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'ทดสอบการเชื่อมต่อไม่สำเร็จ');
                }
                if (typeof data.staff_name === 'string') {
                    setStaffNameBox(data.staff_name);
                }
                showSystemSettingsStatus(data.message || 'เชื่อมต่อสำเร็จ', 'success');
            } catch (error) {
                showSystemSettingsStatus(error.message || 'ทดสอบการเชื่อมต่อไม่สำเร็จ', 'error');
                setStaffNameBox('');
            }
        }

        function setComputerNameBox(name) {
            const box = document.getElementById('settingsComputerNameBox');
            const hidden = document.getElementById('settingsComputerName');
            const clean = String(name || '').trim();
            if (box) {
                box.textContent = clean || 'ไม่พบข้อมูลเครื่อง';
                box.className = 'settings-status show ' + (clean ? 'success' : 'error');
            }
            if (hidden) hidden.value = clean;
        }

        async function lookupComputerNameForSettings() {
            const computerId = Number(document.getElementById('settingsComputerId').value || 0);
            if (computerId <= 0) {
                setComputerNameBox('');
                return;
            }
            try {
                const response = await kdsApiFetch(_kdsBase + '/api_checker.php?action=lookup_computer_name&computer_id=' + encodeURIComponent(computerId) + '&_=' + Date.now(), { cache: 'no-store' });
                const data = await response.json();
                setComputerNameBox((response.ok && data.success) ? (data.computer_name || '') : '');
            } catch (e) {
                setComputerNameBox('');
            }
        }

        async function lookupStaffNameForSettings() {
            const staffId = Number(document.getElementById('settingsFinishStaffId').value || 0);
            if (staffId <= 0) {
                setStaffNameBox('');
                return;
            }
            try {
                const response = await kdsApiFetch(_kdsBase + '/api_checker.php?action=lookup_staff_name&staff_id=' + encodeURIComponent(staffId) + '&_=' + Date.now(), { cache: 'no-store' });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'ไม่สามารถตรวจสอบชื่อพนักงานได้');
                }
                setStaffNameBox(data.staff_name || '');
            } catch (error) {
                setStaffNameBox('');
            }
        }

        function applySavedSystemSettingsToRuntime(payload, staffName) {
            localStorage.setItem('checker_shop_id', String(payload.shop_id || 0));
            localStorage.setItem('checker_finish_staff_id', String(payload.finish_staff_id || defaultFinishStaffId));
            localStorage.setItem('checker_threshold_yellow', String(payload.threshold_yellow || thresholdYellowDefault));
            localStorage.setItem('checker_threshold_red', String(payload.threshold_red || thresholdRedDefault));
            localStorage.setItem('checker_sound_enabled', payload.sound_enabled ? '1' : '0');
            localStorage.setItem(_barcodeVisibleKey, payload.barcode_scan_visible ? '1' : '0');
            applyBarcodeVisible(!!payload.barcode_scan_visible);
            saveBarcodeCameraEnabled(!!payload.barcode_camera_enabled);
            timerThresholds.yellow = Number(payload.threshold_yellow || thresholdYellowDefault || 10);
            timerThresholds.red = Number(payload.threshold_red || thresholdRedDefault || 20);
            soundSettings.enabled = !!payload.sound_enabled;
            state.kdsTwoStepCheckout = !!payload.kds_two_step_checkout;
            state.checkoutQtyMode = Number(payload.checkout_qty_mode || 1);
            state.showOrderNumber = Number(payload.show_order_number || 0) === 1;
            applyBarcodeCameraAvailability();
            applyOutOfStockEnabled(payload.out_of_stock_enabled !== 0);
            applyStaffLoginMode(payload.hide_staff_login === 1);
            setStaffNameBox(staffName || '');
        }

        function initFinishStaffId() {
            // โหลด shop_id
            const savedShopId = localStorage.getItem('checker_shop_id');
            if (savedShopId !== null) {
                const shopIdEl = document.getElementById('settingsShopId');
                if (shopIdEl) shopIdEl.value = savedShopId;
            }
            const saved = localStorage.getItem('checker_finish_staff_id');
            if (saved && Number(saved) > 0) {
                return;
            }
            localStorage.setItem('checker_finish_staff_id', String(defaultFinishStaffId));
        }

        function saveFinishStaffId() {
            showNotice('กรุณาตั้งค่า Finish Staff ID ผ่านปุ่มตั้งค่าระบบ', 'success');
        }

        function getFinishStaffId() {
            const raw = Number(localStorage.getItem('checker_finish_staff_id') || 0);
            return raw > 0 ? raw : defaultFinishStaffId;
        }
        function applyFilterInfo(filters) {
            state.filters = Object.assign({}, state.filters, filters || {});
        }

        async function loadAll() {
            const results = await Promise.all([
                loadActiveRows(),
                loadFinishedRows({ silent: true })
            ]);

            const activeOk = !!results[0];
            const finishedOk = !!results[1];
            if (activeOk && !finishedOk) {
                setStatusText('พร้อมใช้งาน');
                console.warn('โหลดรายการเสร็จไม่สำเร็จ แต่คิวหลักยังใช้งานได้');
            }
        }

        async function loadActiveRows(options) {
            if (_activeRowsLoading) return;
            _activeRowsLoading = true;
            options = options || {};
            const silent = !!options.silent;
            try {
                setStatusText('กำลังโหลด...');
                const response = await kdsApiFetch(_kdsBase + '/api_checker.php?action=list_active&_=' + Date.now(), { cache: 'no-store' });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'โหลดคิวไม่สำเร็จ');
                }

                state.stats.active_rows = Number((data.stats || {}).active_rows || 0);
                state.stats.active_qty = Number((data.stats || {}).active_qty || 0);
                state.active_rows = Array.isArray(data.active_rows) ? data.active_rows : [];
                applyFilterInfo(data.filters || {});
                checkForNewOrders(state.active_rows);

                updateView();
                setStatusText('พร้อมใช้งาน');
                if (_pollingErrorCount > 0) { _pollingErrorCount = 0; hidePollingOfflineBanner(); }
                return true;
            } catch (error) {
                if (error && error.name === 'AbortError') { setStatusText('หมดเวลา — รอรอบถัดไป'); return false; }
                setStatusText('เกิดข้อผิดพลาด');
                _pollingErrorCount++;
                if (_pollingErrorCount >= 3) { showPollingOfflineBanner(); }
                else if (!silent) { showNotice(error.message || 'โหลดคิวไม่สำเร็จ', 'error'); }
                return false;
            } finally {
                _activeRowsLoading = false;
            }
        }

        async function loadFinishedRows(options) {
            if (_finishedRowsLoading) return;
            _finishedRowsLoading = true;
            options = options || {};
            const silent = !!options.silent;
            try {
                const response = await kdsApiFetch(_kdsBase + '/api_checker.php?action=list_finished&_=' + Date.now(), { cache: 'no-store' });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'โหลดรายการเสร็จไม่สำเร็จ');
                }

                state.recent_finished_rows = Array.isArray(data.recent_finished_rows) ? data.recent_finished_rows : [];
                state.stats.recent_finished_rows = state.recent_finished_rows.length;
                applyFilterInfo(data.filters || {});
                updateView();
                return true;
            } catch (error) {
                if (!silent) {
                    showNotice(error.message || 'โหลดรายการเสร็จไม่สำเร็จ', 'error');
                }
                return false;
            } finally {
                _finishedRowsLoading = false;
            }
        }

        function updateView() {
            renderStats(state.stats || {});
            renderActiveRows(state.active_rows || []);
            renderRecentFinished(state.recent_finished_rows || []);
            syncDrawerState();
            applyZoneFilterSync();
        }
        function renderFilterInfo() {
        }

        function renderStats(stats) {
            document.getElementById('statActiveRows').textContent = Number(stats.active_rows || 0);
            document.getElementById('statActiveQty').textContent = formatQty(stats.active_qty || 0);
            const finishedCount = Number(stats.recent_finished_rows || 0);
            document.getElementById('fabFinishedCount').textContent = finishedCount;
            document.getElementById('topFinishedCount').textContent = finishedCount;
        }
        function renderComments(comments, compact) {
            const list = Array.isArray(comments) ? comments : [];
            if (!list.length) {
                return '';
            }

            const normalItems = [];
            const pricedItems = [];

            list.forEach(function(comment) {
                const type = Number(comment.type || 0);
                const amount = Number(comment.amount || 0);
                const suffix = amount > 1 ? ' x' + formatQty(amount) : '';
                const rawText = String(comment.text || '').trim();
                if (!rawText) {
                    return;
                }
                const text = escapeHtml(rawText) + suffix;
                if (type === 15) {
                    pricedItems.push(text);
                } else {
                    normalItems.push(text);
                }
            });

            if (compact) {
                const blocks = [];
                if (normalItems.length) {
                    blocks.push(`
                        <div class="comment-group">
                            <span class="comment-group-label">คอมเมนต์</span>
                            <span class="comment-group-items">${normalItems.join(', ')}</span>
                        </div>
                    `);
                }
                if (pricedItems.length) {
                    blocks.push(`
                        <div class="comment-group priced">
                            <span class="comment-group-label">คอมเมนต์เพิ่มราคา</span>
                            <span class="comment-group-items">${pricedItems.join(', ')}</span>
                        </div>
                    `);
                }
                return blocks.length ? `<div class="comment-list compact">${blocks.join('')}</div>` : '';
            }

            const lines = [];
            if (normalItems.length) {
                lines.push(`<div class="comment-inline"><span class="label">คอมเมนต์:</span>${normalItems.join(', ')}</div>`);
            }
            if (pricedItems.length) {
                lines.push(`<div class="comment-inline priced"><span class="label">คอมเมนต์เพิ่มราคา:</span>${pricedItems.join(', ')}</div>`);
            }
            return lines.length ? `<div class="product-comments-inline">${lines.join('')}</div>` : '';
        }

        function buildActiveProductTotals(rows) {
            const totals = {};
            (Array.isArray(rows) ? rows : []).forEach(function(row) {
                const key = String(row && row.ProductName ? row.ProductName : '').trim();
                if (!key) {
                    return;
                }
                const qty = Number(row && row.ProductAmount ? row.ProductAmount : 0) || 0;
                totals[key] = (totals[key] || 0) + qty;
            });
            return totals;
        }

        function renderActiveRows(rows) {
            const wrap = document.getElementById('activeCards');
            const productTotals = buildActiveProductTotals(rows);
            document.getElementById('queueSummary').textContent = rows.length ? ('ค้าง ' + rows.length + ' แถว') : 'ไม่มีคิวค้าง';

            if (!rows.length) {
                wrap.innerHTML = '<div class="empty">ไม่มีรายการค้างของวันนี้ในครัว</div>';
                return;
            }

            wrap.innerHTML = rows.map(function(row) {
                const isVoided   = Number(row.ProcessStatus) === 98;

                // voided card — manual confirm mode เท่านั้น
                if (isVoided) {
                    const tableText = row.DisplayTableName || row.TableID || '-';
                    return `
                        <article class="card card-voided" data-table-id="${Number(row.TableID || 0)}">
                            <div class="card-head">
                                <div><div class="table-name">โต๊ะ ${escapeHtml(tableText)}</div></div>
                            </div>
                            <div class="voided-badge">❌ ถูกยกเลิก</div>
                            <div class="product-block">
                                <h3 class="product-name">${formatQty(row.ProductAmount)}x ${escapeHtml(row.ProductName || '-')}</h3>
                            </div>
                            <div class="card-actions">
                                <button class="btn btn-confirm-void js-confirm-void"
                                    data-product-level-id="${Number(row.ProductLevelID || 0)}"
                                    data-process-id="${Number(row.ProcessID || 0)}"
                                    data-sub-process-id="${Number(row.SubProcessID || 0)}"
                                    data-printer-id="${Number(row.PrinterID || 0)}"
                                    ${isSubmitting ? 'disabled' : ''}
                                >ยืนยันยกเลิก</button>
                            </div>
                        </article>
                    `;
                }

                const waitMinutes = getMinutesDiff(row.SubmitOrderDateTime);
                const isMoved    = !!row.is_moved;
                const isCombined = !!row.is_combined;
                const movedTo    = row.moved_to || '';
                const yMin = timerThresholds.yellow;
                const rMin = timerThresholds.red;
                const isConfirmed = Number(row.ProcessStatus || 0) === 2;
                const isTwoStepMode = !!state.kdsTwoStepCheckout;
                const checkoutTone = isTwoStepMode ? (isConfirmed ? 'dark' : 'soft') : 'dark';

                // กำหนด class การ์ด
                let cardClass    = 'card';
                let waitTagClass = 'tag good';
                let qtyBadgeClass = 'qty-badge checkout-' + checkoutTone;
                if (isMoved) {
                    cardClass = 'card moved';
                    waitTagClass = 'tag';
                    qtyBadgeClass = 'qty-badge checkout-dark';
                } else if (isCombined) {
                    cardClass = 'card combined';
                    waitTagClass = 'tag';
                    qtyBadgeClass = 'qty-badge';
                } else if (waitMinutes >= rMin) {
                    cardClass = 'card warn-red';
                    waitTagClass = 'tag wait';
                } else if (waitMinutes >= yMin) {
                    cardClass = 'card warn-yellow';
                    waitTagClass = 'tag wait';
                } else {
                    cardClass = 'card checkout-' + checkoutTone;
                }

                const tableText = row.DisplayTableName || row.TableID || '-';

                // badge สถานะพิเศษ
                let statusBadge = '';
                if (isMoved) {
                    statusBadge = `<div class="status-badge moved">🔀 ย้ายไปโต๊ะ ${escapeHtml(movedTo)}</div>`;
                } else if (isCombined) {
                    statusBadge = `<div class="status-badge combined">🔗 รวมโต๊ะแล้ว</div>`;
                }

                const actionButtons = [];
                if (!isCombined) {
                    actionButtons.push(`
                        <button
                            class="btn btn-checkout-${checkoutTone} js-checkout"
                            data-product-level-id="${Number(row.ProductLevelID || 0)}"
                            data-process-id="${Number(row.ProcessID || 0)}"
                            data-sub-process-id="${Number(row.SubProcessID || 0)}"
                            data-printer-id="${Number(row.PrinterID || 0)}"
                            ${isSubmitting ? 'disabled' : ''}
                        >${state.kdsTwoStepCheckout ? (isConfirmed ? 'Checkout 1 รายการ' : 'ยืนยันไปครัว') : (isMoved ? 'Checkout' : 'Checkout 1 รายการ')}</button>
                    `);
                }

                const actionHtml = actionButtons.length ? `<div class="card-actions">${actionButtons.join('')}</div>` : '';
                const productKey = String(row.ProductName || '').trim();
                const totalQtyForProduct = productKey && Object.prototype.hasOwnProperty.call(productTotals, productKey)
                    ? productTotals[productKey]
                    : Number(row.ProductAmount || 0);
                const totalQtyHint = totalQtyForProduct > Number(row.ProductAmount || 0)
                    ? `<div class="product-total-hint">รวมทั้งคิว ${formatQty(totalQtyForProduct)}</div>`
                    : '';

                const orderNumField = state.showOrderNumber && row.ProcessID
                    ? `<div class="field"><div class="field-label">Order#</div><div class="field-value">${String(Number(row.ProcessID)).padStart(6, '0')}</div></div>`
                    : '';

                return `
                    <article class="${cardClass}" data-table-id="${Number(row.TableID || 0)}">
                        <div class="card-head">
                            <div>
                                <div class="table-name">โต๊ะ ${escapeHtml(tableText)}</div>
                            </div>
                        </div>

                        ${statusBadge}

                        <div class="product-block">
                            ${row.parent_name ? `<div class="parent-name-label">${escapeHtml(row.parent_name)}</div>` : ''}
                            <h3 class="product-name">${formatQty(row.ProductAmount)}x ${escapeHtml(row.ProductName || '-')}</h3>
                            ${Number(row.ProductSetType) === 7 && Number(row.DisplayFlexibleAtChecker) === 1 ? '<div class="set-compact-badge">เซ็ท</div>' : ''}
                            ${totalQtyHint}
                            ${renderComments(row.comments || [], false)}
                        </div>

                        <div class="queue-tags">
                            <span class="${waitTagClass}">⏱️ รอ ${waitMinutes} นาที</span>
                            <span class="tag">${escapeHtml(row.SaleModeName || '-')}</span>
                        </div>

                        <div class="grid">
                            <div class="field">
                                <div class="field-label">ส่งเข้าเมื่อ</div>
                                <div class="field-value">${escapeHtml(formatTime(row.SubmitOrderDateTime))}</div>
                            </div>
                            ${orderNumField}
                        </div>

                        ${actionHtml}
                    </article>
                `;
            }).join('');
        }

        function renderRecentFinished(rows) {
            const listWrap = document.getElementById('recentFinishedList');

            if (!rows.length) {
                listWrap.innerHTML = '<div class="empty">ยังไม่มีรายการเสร็จวันนี้</div>';
                return;
            }

            listWrap.innerHTML = rows.map(function(row) {
                const tableText = row.DisplayTableName || row.TableID || '-';
                return `
                    <div class="finished-item">
                        <div class="finished-top">
                            <div>
                                ${row.parent_name ? `<div class="parent-name-label" style="margin-bottom:6px">${escapeHtml(row.parent_name)}</div>` : ''}
                                <div class="finished-name">${escapeHtml(row.ProductName || '-')}</div>
                                <div class="finished-meta">โต๊ะ ${escapeHtml(tableText)}</div>
                            </div>
                            <div class="finished-qty">x${formatQty(row.ProductAmount)}</div>
                        </div>
                        ${renderComments(row.comments || [], true)}
                        <div class="finished-meta">
                            เสร็จเมื่อ: ${escapeHtml(formatDateTime(row.FinishDateTime))}<br>
                            Finish Staff ID: ${escapeHtml(row.FinishStaffID || '-')}
                        </div>
                        <button
                            class="btn btn-neutral js-undo"
                            data-product-level-id="${Number(row.ProductLevelID || 0)}"
                            data-process-id="${Number(row.ProcessID || 0)}"
                            data-sub-process-id="${Number(row.SubProcessID || 0)}"
                            data-printer-id="${Number(row.PrinterID || 0)}"
                            ${isSubmitting ? 'disabled' : ''}
                        >ย้อนกลับ 1 รายการ</button>
                    </div>
                `;
            }).join('');
        }

        function openFinishedDrawer() {

            state.finishedDrawerOpen = true;
            syncDrawerState();
        }

        function closeFinishedDrawer() {
            state.finishedDrawerOpen = false;
            syncDrawerState();
        }

        function syncDrawerState() {
            const drawer = document.getElementById('finishedDrawer');
            const backdrop = document.getElementById('finishedDrawerBackdrop');
            const isOpen = !!state.finishedDrawerOpen;
            drawer.classList.toggle('open', isOpen);
            backdrop.classList.toggle('open', isOpen);
            drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            document.body.classList.toggle('drawer-open', isOpen);
        }

        function findActiveRow(productLevelId, processId, subProcessId, printerId) {
            return (state.active_rows || []).find(function(row) {
                return Number(row.ProductLevelID) === Number(productLevelId)
                    && Number(row.ProcessID) === Number(processId)
                    && Number(row.SubProcessID) === Number(subProcessId)
                    && Number(row.PrinterID) === Number(printerId);
            }) || null;
        }

        function markRowConfirmedInState(row) {
            if (!row) return;
            state.active_rows = (state.active_rows || []).map(function(item) {
                const isSame = Number(item.ProductLevelID) === Number(row.ProductLevelID)
                    && Number(item.ProcessID) === Number(row.ProcessID)
                    && Number(item.SubProcessID) === Number(row.SubProcessID)
                    && Number(item.PrinterID) === Number(row.PrinterID);
                if (!isSame) return item;
                return Object.assign({}, item, { ProcessStatus: 2 });
            });
        }

        function findFinishedRow(productLevelId, processId, subProcessId, printerId) {
            return (state.recent_finished_rows || []).find(function(row) {
                return Number(row.ProductLevelID) === Number(productLevelId)
                    && Number(row.ProcessID) === Number(processId)
                    && Number(row.SubProcessID) === Number(subProcessId)
                    && Number(row.PrinterID) === Number(printerId);
            }) || null;
        }

        function applyCheckoutToState(row, qtyToFinish) {
            if (!row) return;
            qtyToFinish = Number(qtyToFinish) > 0 ? Number(qtyToFinish) : 1;
            const currentQty = Number(row.ProductAmount || 0);
            const remainingQty = currentQty - qtyToFinish;
            const nextActiveRows = [];
            let removedRow = false;

            (state.active_rows || []).forEach(function(item) {
                const isSame = Number(item.ProductLevelID) === Number(row.ProductLevelID)
                    && Number(item.ProcessID) === Number(row.ProcessID)
                    && Number(item.SubProcessID) === Number(row.SubProcessID)
                    && Number(item.PrinterID) === Number(row.PrinterID);

                if (!isSame) {
                    nextActiveRows.push(item);
                    return;
                }

                if (remainingQty > 0) {
                    nextActiveRows.push(Object.assign({}, item, {
                        ProductAmount: remainingQty
                    }));
                } else {
                    removedRow = true;
                }
            });

            state.active_rows = nextActiveRows;
            state.stats.active_qty = Math.max(0, Number(state.stats.active_qty || 0) - qtyToFinish);
            if (removedRow) {
                state.stats.active_rows = Math.max(0, Number(state.stats.active_rows || 0) - 1);
            }

            const finishedItem = Object.assign({}, row, {
                ProductAmount: qtyToFinish,
                FinishDateTime: new Date().toISOString().slice(0, 19).replace('T', ' '),
                FinishStaffID: getFinishStaffId(),
                ProcessStatus: 1
            });
            state.recent_finished_rows = [finishedItem].concat(state.recent_finished_rows || []);
            if (recentFinishedLimit > 0) {
                state.recent_finished_rows = state.recent_finished_rows.slice(0, recentFinishedLimit);
            }
            state.stats.recent_finished_rows = state.recent_finished_rows.length;
            updateView();
        }

        function applyUndoToState(row) {
            if (!row) return;

            state.recent_finished_rows = (state.recent_finished_rows || []).filter(function(item) {
                return !(Number(item.ProductLevelID) === Number(row.ProductLevelID)
                    && Number(item.ProcessID) === Number(row.ProcessID)
                    && Number(item.SubProcessID) === Number(row.SubProcessID)
                    && Number(item.PrinterID) === Number(row.PrinterID));
            });
            state.stats.recent_finished_rows = state.recent_finished_rows.length;

            let merged = false;
            state.active_rows = (state.active_rows || []).map(function(item) {
                const isSameGroup = Number(item.ProductLevelID) === Number(row.ProductLevelID)
                    && Number(item.ProcessID) === Number(row.ProcessID)
                    && Number(item.PrinterID) === Number(row.PrinterID)
                    && Number(item.ProcessStatus) !== 1;
                if (isSameGroup && !merged) {
                    merged = true;
                    return Object.assign({}, item, {
                        ProductAmount: Number(item.ProductAmount || 0) + Number(row.ProductAmount || 0)
                    });
                }
                return item;
            });

            if (!merged) {
                state.active_rows.push(Object.assign({}, row, {
                    FinishDateTime: null,
                    FinishStaffID: 0,
                    ProcessStatus: 0
                }));
                state.stats.active_rows = Number(state.stats.active_rows || 0) + 1;
            }

            state.stats.active_qty = Number(state.stats.active_qty || 0) + Number(row.ProductAmount || 0);
            updateView();
        }

        // ── Checkout Qty Popup (mode 3) ───────────────────
        let _checkoutQtyResolve = null;
        let _checkoutQtyMax = 1;
        let _checkoutQtyCurrent = 1;

        function openCheckoutQtyPopup(productName, maxQty) {
            return new Promise(function(resolve) {
                _checkoutQtyResolve = resolve;
                _checkoutQtyMax = maxQty;
                _checkoutQtyCurrent = 1;
                document.getElementById('checkoutQtyProductLabel').textContent = productName || '';
                document.getElementById('checkoutQtyDisplay').textContent = '1';
                document.getElementById('checkoutQtyMaxLabel').textContent = 'จำนวนสูงสุด: ' + maxQty;
                const backdrop = document.getElementById('checkoutQtyBackdrop');
                const modal = backdrop.querySelector('.modal');
                backdrop.style.display = 'flex';
                requestAnimationFrame(function() {
                    backdrop.style.opacity = '1';
                    backdrop.style.pointerEvents = 'auto';
                    if (modal) {
                        modal.style.opacity = '1';
                        modal.style.pointerEvents = 'auto';
                        modal.style.transform = 'translate(-50%,-50%)';
                    }
                });
            });
        }

        function closeCheckoutQtyPopup(result) {
            const backdrop = document.getElementById('checkoutQtyBackdrop');
            const modal = backdrop.querySelector('.modal');
            backdrop.style.opacity = '0';
            backdrop.style.pointerEvents = 'none';
            if (modal) {
                modal.style.opacity = '0';
                modal.style.pointerEvents = 'none';
                modal.style.transform = 'translate(-50%,-54%)';
            }
            setTimeout(function() { backdrop.style.display = 'none'; }, 200);
            if (_checkoutQtyResolve) {
                _checkoutQtyResolve(result);
                _checkoutQtyResolve = null;
            }
        }

        // Checkout qty popup buttons — use event delegation so elements don't need to exist yet
        document.addEventListener('click', function(e) {
            var t = e.target;
            if (t.id === 'checkoutQtyMinus' || t.closest('#checkoutQtyMinus')) {
                if (_checkoutQtyCurrent > 1) {
                    _checkoutQtyCurrent--;
                    var d = document.getElementById('checkoutQtyDisplay');
                    if (d) d.textContent = _checkoutQtyCurrent;
                }
            } else if (t.id === 'checkoutQtyPlus' || t.closest('#checkoutQtyPlus')) {
                if (_checkoutQtyCurrent < _checkoutQtyMax) {
                    _checkoutQtyCurrent++;
                    var d = document.getElementById('checkoutQtyDisplay');
                    if (d) d.textContent = _checkoutQtyCurrent;
                }
            } else if (t.id === 'checkoutQtyCancelBtn') {
                closeCheckoutQtyPopup(null);
            } else if (t.id === 'checkoutQtyConfirmBtn') {
                closeCheckoutQtyPopup(_checkoutQtyCurrent);
            }
        });

        async function checkoutOne(productLevelId, processId, subProcessId, printerId) {
            if (isSubmitting) return;
            if (!staffIsLoggedIn) { showNotice('กรุณาเข้าสู่ระบบก่อน', 'error'); return; }
            const clickedRow = findActiveRow(productLevelId, processId, subProcessId, printerId);
            if (!clickedRow) {
                await loadActiveRows();
                return;
            }

            const isTwoStepEnabled = !!state.kdsTwoStepCheckout;
            const currentStatus = Number(clickedRow.ProcessStatus || 0);
            const shouldConfirmFirst = isTwoStepEnabled && currentStatus !== 2;

            // determine qty to finish based on mode (only for actual checkout, not confirm step)
            let qtyToFinish = 1;
            if (!shouldConfirmFirst) {
                const parentQty = Number(clickedRow.ProductAmount || 1);
                const mode = state.checkoutQtyMode || 1;
                if (mode === 2) {
                    qtyToFinish = parentQty;
                } else if (mode === 3 && parentQty > 1) {
                    isSubmitting = true;
                    const chosen = await openCheckoutQtyPopup(
                        String(clickedRow.ProductName || clickedRow.MenuName || ''),
                        parentQty
                    );
                    if (chosen === null) { isSubmitting = false; return; }
                    qtyToFinish = chosen;
                }
            }

            isSubmitting = true;
            setStatusText(shouldConfirmFirst ? 'กำลังยืนยันรายการ...' : 'กำลัง checkout...');

            try {
                const params = new URLSearchParams();
                params.set('action', shouldConfirmFirst ? 'confirm_one' : 'checkout_one');
                params.set('ProductLevelID', productLevelId);
                params.set('ProcessID', processId);
                params.set('SubProcessID', subProcessId);
                params.set('PrinterID', printerId);
                params.set('finish_staff_id', getFinishStaffId());
                if (!shouldConfirmFirst) {
                    params.set('qty_to_finish', qtyToFinish);
                }

                const response = await kdsApiFetch(_kdsBase + '/api_checker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                }, 20000);
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'checkout ไม่สำเร็จ');
                }

                if (shouldConfirmFirst) {
                    markRowConfirmedInState(clickedRow);
                    showNotice(data.message || 'ยืนยันรายการแล้ว', 'success');
                    setStatusText('อัปเดตแล้ว');
                    updateView();
                    setTimeout(loadActiveRows, 120);
                } else {
                    applyCheckoutToState(clickedRow, qtyToFinish);
                    feedbackSuccess();
                    setStatusText('อัปเดตแล้ว');
                    setTimeout(loadActiveRows, 120);
                    if (data.refresh_finished) {
                        setTimeout(loadFinishedRows, 160);
                    }
                }
            } catch (error) {
                setStatusText('เกิดข้อผิดพลาด');
                const msg = String(error.message || '');
                const alreadyDone = msg.indexOf('checkout ไปแล้ว') !== -1 || msg.indexOf('ถูก checkout') !== -1;
                if (!alreadyDone) {
                    showNotice(msg || 'checkout ไม่สำเร็จ', 'error');
                }
                await loadAll();
            } finally {
                isSubmitting = false;
                updateView();
            }
        }


        // ── Mobile detection ──────────────────────────────
        function isMobile() {
            return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
        }

        // ── กัน scan ซ้ำ JS ──────────────────────────────
        let _lastScanCode = '';
        let _lastScanTime = 0;
        function isDuplicateScan(code) {
            const now = Date.now();
            if (code === _lastScanCode && now - _lastScanTime < 1500) return true;
            _lastScanCode = code;
            _lastScanTime = now;
            return false;
        }

        // ── Beep + Vibrate ─────────────────────────────────
        function feedbackSuccess() {
            try { navigator.vibrate && navigator.vibrate(80); } catch(e){}
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.frequency.value = 1200; g.gain.value = 0.3;
                o.start(); o.stop(ctx.currentTime + 0.08);
            } catch(e){}
        }
        function feedbackError() {
            try { navigator.vibrate && navigator.vibrate([100,50,100]); } catch(e){}
        }

        async function checkoutBarcode() {
            if (!barcodeCheckoutEnabled || isSubmitting) return;
            if (!staffIsLoggedIn) { showNotice('กรุณาเข้าสู่ระบบก่อน', 'error'); return; }
            const input = document.getElementById('barcodeInput');
            if (!input) return;
            const barcode = String(input.value || '').trim();
            if (!barcode) {
                showNotice('กรุณาสแกนหรือกรอกรหัสบาร์โค้ด', 'error');
                focusBarcodeInput();
                return;
            }
            // กัน scan ซ้ำ
            if (isDuplicateScan(barcode)) {
                clearBarcodeInput();
                focusBarcodeInput();
                return;
            }

            isSubmitting = true;
            setStatusText('กำลังค้นหา Barcode...');

            try {
                const params = new URLSearchParams();
                params.set('action', 'checkout_barcode');
                params.set('barcode', barcode);
                params.set('finish_staff_id', getFinishStaffId());

                const response = await kdsApiFetch(_kdsBase + '/api_checker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                }, 20000);
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || data.message || 'Barcode not found');
                }

                clearBarcodeInput();
                setStatusText('อัปเดตแล้ว');
                await loadAll();
                focusBarcodeInput();
            } catch (error) {
                setStatusText('เกิดข้อผิดพลาด');
                setBarcodeReadyHint('ไม่พบบาร์โค้ด', 'error');
                clearBarcodeInput();
                feedbackError();
                showNotice(error.message || 'Barcode not found', 'error');
                focusBarcodeInput();
            } finally {
                isSubmitting = false;
                updateView();
            }
        }

        async function confirmVoid(productLevelId, processId, subProcessId, printerId) {
            if (isSubmitting) return;
            isSubmitting = true;
            try {
                const resp = await kdsApiFetch(_kdsBase + '/api_checker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'confirm_void',
                        ProductLevelID: productLevelId,
                        ProcessID: processId,
                        SubProcessID: subProcessId,
                        PrinterID: printerId,
                        finish_staff_id: currentFinishStaffId(),
                    }),
                });
                const data = await resp.json();
                if (!data.success) throw new Error(data.error || 'เกิดข้อผิดพลาด');
                state.active_rows = (state.active_rows || []).filter(function(r) {
                    return !(Number(r.ProcessID) === Number(processId) && Number(r.SubProcessID) === Number(subProcessId));
                });
                renderActiveRows(state.active_rows);
                showNotice('ยืนยันยกเลิกเรียบร้อย', 'success');
            } catch (e) {
                showNotice(e.message || 'เกิดข้อผิดพลาด', 'error');
            } finally {
                isSubmitting = false;
            }
        }

        async function resolveStatus(productLevelId, processId, subProcessId, printerId) {
            if (isSubmitting) return;
            if (!staffIsLoggedIn) { showNotice('กรุณาเข้าสู่ระบบก่อน', 'error'); return; }
            const clickedRow = findActiveRow(productLevelId, processId, subProcessId, printerId);
            if (!clickedRow) {
                await loadActiveRows();
                return;
            }

            isSubmitting = true;
            setStatusText('กำลังจบสถานะ...');

            try {
                const params = new URLSearchParams();
                params.set('action', 'resolve_status');
                params.set('ProductLevelID', productLevelId);
                params.set('ProcessID', processId);
                params.set('SubProcessID', subProcessId);
                params.set('PrinterID', printerId);
                params.set('finish_staff_id', getFinishStaffId());

                const response = await kdsApiFetch(_kdsBase + '/api_checker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                }, 20000);
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'จบสถานะไม่สำเร็จ');
                }

                state.active_rows = (state.active_rows || []).filter(function(item) {
                    return !(Number(item.ProductLevelID) === Number(productLevelId)
                        && Number(item.ProcessID) === Number(processId)
                        && Number(item.SubProcessID) === Number(subProcessId)
                        && Number(item.PrinterID) === Number(printerId));
                });
                state.stats.active_rows = Math.max(0, Number(state.stats.active_rows || 0) - 1);
                state.stats.active_qty = Math.max(0, Number(state.stats.active_qty || 0) - Number(clickedRow.ProductAmount || 0));

                showNotice(data.message || 'จบสถานะสำเร็จ', 'success');
                setStatusText('อัปเดตแล้ว');
                updateView();
                setTimeout(loadActiveRows, 120);
            } catch (error) {
                setStatusText('เกิดข้อผิดพลาด');
                showNotice(error.message || 'จบสถานะไม่สำเร็จ', 'error');
                await loadAll();
            } finally {
                isSubmitting = false;
                updateView();
            }
        }

        async function undoOne(productLevelId, processId, subProcessId, printerId) {
            if (isSubmitting) return;
            const clickedRow = findFinishedRow(productLevelId, processId, subProcessId, printerId);
            isSubmitting = true;
            setStatusText('กำลังย้อนกลับ...');

            try {
                const params = new URLSearchParams();
                params.set('action', 'undo_one');
                params.set('ProductLevelID', productLevelId);
                params.set('ProcessID', processId);
                params.set('SubProcessID', subProcessId);
                params.set('PrinterID', printerId);

                const response = await kdsApiFetch(_kdsBase + '/api_checker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                }, 20000);
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'ย้อนกลับไม่สำเร็จ');
                }

                if (clickedRow) {
                    applyUndoToState(clickedRow);
                }
                showNotice(data.message || 'ย้อนกลับสำเร็จ', 'success');
                setStatusText('อัปเดตแล้ว');
                setTimeout(loadActiveRows, 120);
                if (data.refresh_finished) {
                    setTimeout(loadFinishedRows, 160);
                }
            } catch (error) {
                setStatusText('เกิดข้อผิดพลาด');
                showNotice(error.message || 'ย้อนกลับไม่สำเร็จ', 'error');
                await loadAll();
            } finally {
                isSubmitting = false;
                updateView();
            }
        }

        function setStatusText(text) {
            document.getElementById('statStatusText').textContent = text;
        }

        function showNotice(message, type) {
            const box = document.getElementById('notice');
            box.className = 'notice ' + (type || 'success');
            box.textContent = message;
            clearTimeout(noticeTimer);
            noticeTimer = setTimeout(function() {
                box.className = 'notice';
                box.textContent = '';
            }, 3500);
        }

        function formatQty(value) {
            const num = Number(value || 0);
            return Number.isInteger(num) ? String(num) : num.toFixed(2);
        }

        function formatDateTime(value) {
            if (!value) return '-';
            const safe = String(value).replace(' ', 'T');
            const dt = new Date(safe);
            if (Number.isNaN(dt.getTime())) return String(value);
            return dt.toLocaleString('th-TH', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        function formatTime(value) {
            if (!value) return '-';
            const safe = String(value).replace(' ', 'T');
            const dt = new Date(safe);
            if (Number.isNaN(dt.getTime())) {
                const raw = String(value);
                const parts = raw.split(' ');
                return parts.length > 1 ? parts[1] : raw;
            }
            return dt.toLocaleTimeString('th-TH', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        function formatDate(value) {
            if (!value) return '-';
            const dt = new Date(String(value));
            if (Number.isNaN(dt.getTime())) return String(value);
            return dt.toLocaleDateString('th-TH', {
                year: 'numeric', month: '2-digit', day: '2-digit'
            });
        }

        function getMinutesDiff(value) {
            if (!value) return 0;
            const safe = String(value).replace(' ', 'T');
            const dt = new Date(safe);
            if (Number.isNaN(dt.getTime())) return 0;
            return Math.max(0, Math.floor((Date.now() - dt.getTime()) / 60000));
        }

        function jsEscape(value) {
            return String(value || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function escapeAttribute(value) {
            return escapeHtml(value).replace(/`/g, '&#096;');
        }

        document.getElementById('refreshBtn').addEventListener('click', loadAll);
        const openSystemSettingsBtn = document.getElementById('openSystemSettingsBtn');
        if (openSystemSettingsBtn) {
            openSystemSettingsBtn.addEventListener('click', openTimerSettings);
        }

        // กด logo 3 ครั้งภายใน 1.5 วินาที เพื่อเปิดตั้งค่าระบบ
        (function() {
            const brand = document.querySelector('.brand');
            if (!brand) return;
            let tapCount = 0;
            let tapTimer = null;
            brand.style.cursor = 'default';
            brand.addEventListener('click', function() {
                tapCount++;
                if (tapCount === 1) {
                    tapTimer = setTimeout(function() { tapCount = 0; }, 1500);
                }
                if (tapCount >= 3) {
                    clearTimeout(tapTimer);
                    tapCount = 0;
                    openTimerSettings();
                }
            });
        })();
        const barcodeInput = document.getElementById('barcodeInput');
        if (barcodeInput) {
            // มือถือ = inputmode none กัน keyboard เปิด
            if (isMobile()) {
                barcodeInput.setAttribute('inputmode', 'none');
                barcodeInput.setAttribute('readonly', 'readonly');
            }
            barcodeInput.addEventListener('focus', function() {
                resetGlobalBarcodeBuffer();
            });
            barcodeInput.addEventListener('input', function() {
                barcodeCaptureState.buffer = String(barcodeInput.value || '').trim();
                barcodeCaptureState.lastAt = Date.now();
                barcodeCaptureState.awaitingFreshScan = !barcodeCaptureState.buffer;
            });
            barcodeInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && getBarcodeAutoSubmitEnabled()) {
                    e.preventDefault();
                    e.stopPropagation();
                    checkoutBarcode();
                }
            });
        }
        const openBarcodeCameraBtn = document.getElementById('openBarcodeCameraBtn');
        if (openBarcodeCameraBtn) {
            openBarcodeCameraBtn.addEventListener('click', openBarcodeCamera);
        }
        const closeBarcodeCameraBtn = document.getElementById('closeBarcodeCameraBtn');
        if (closeBarcodeCameraBtn) {
            closeBarcodeCameraBtn.addEventListener('click', function() {
                stopBarcodeCamera();
                focusBarcodeInput();
            });
        }
        const switchBarcodeCameraBtn = document.getElementById('switchBarcodeCameraBtn');
        if (switchBarcodeCameraBtn) {
            switchBarcodeCameraBtn.addEventListener('click', function() {
                switchBarcodeCameraFacing();
            });
        }
        const barcodeCameraBackdrop = document.getElementById('barcodeCameraBackdrop');
        if (barcodeCameraBackdrop) {
            barcodeCameraBackdrop.addEventListener('click', function() {
                stopBarcodeCamera();
                focusBarcodeInput();
            });
        }
        const soldOutBtn = document.getElementById('openSoldOutBtn');
        if (soldOutBtn) soldOutBtn.addEventListener('click', openSoldOutModal);
        const soldOutCloseBtn = document.getElementById('soldOutCloseBtn');
        if (soldOutCloseBtn) soldOutCloseBtn.addEventListener('click', closeSoldOutModal);
        const soldOutBackdrop = document.getElementById('soldOutBackdrop');
        if (soldOutBackdrop) soldOutBackdrop.addEventListener('click', closeSoldOutModal);
        const soldOutSearchInput = document.getElementById('soldOutSearchInput');
        if (soldOutSearchInput) {
            soldOutSearchInput.addEventListener('input', function() {
                state.soldOutKeyword = String(this.value || '').trim();
                if (window.__soldOutSearchTimer) clearTimeout(window.__soldOutSearchTimer);
                window.__soldOutSearchTimer = setTimeout(function() { loadSoldOutProducts(true); }, 220);
            });
        }
        const soldOutListEl = document.getElementById('soldOutList');
        if (soldOutListEl) {
            soldOutListEl.addEventListener('click', function(event) {
                const btn = event.target.closest('.js-toggle-outofstock');
                if (!btn) return;
                toggleOutOfStock(
                    Number(btn.getAttribute('data-product-id') || 0),
                    Number(btn.getAttribute('data-next-state') || 0),
                    btn.getAttribute('data-product-name') || '',
                    btn.getAttribute('data-confirm-text') || ''
                );
            });
        }
        document.getElementById('timerSettingsCancelBtn').addEventListener('click', closeTimerSettings);
        document.getElementById('timerSettingsSaveBtn').addEventListener('click', saveTimerSettings);
        document.getElementById('testDbConnectionBtn').addEventListener('click', testSystemSettingsConnection);
        document.getElementById('settingsFinishStaffId').addEventListener('input', function(){
            if (window.__settingsStaffTimer) clearTimeout(window.__settingsStaffTimer);
            window.__settingsStaffTimer = setTimeout(lookupStaffNameForSettings, 220);
        });
        document.getElementById('settingsComputerId').addEventListener('change', lookupComputerNameForSettings);
        document.getElementById('settingsComputerId').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); lookupComputerNameForSettings(); }
        });
        document.getElementById('timerSettingsBackdrop').addEventListener('click', closeTimerSettings);
        document.getElementById('finishedDrawerBackdrop').addEventListener('click', closeFinishedDrawer);

        document.addEventListener('click', function(event) {
            const checkoutBtn = event.target.closest('.js-checkout');
            if (checkoutBtn) {
                checkoutOne(
                    checkoutBtn.dataset.productLevelId,
                    checkoutBtn.dataset.processId,
                    checkoutBtn.dataset.subProcessId,
                    checkoutBtn.dataset.printerId
                );
                return;
            }

            const confirmVoidBtn = event.target.closest('.js-confirm-void');
            if (confirmVoidBtn) {
                confirmVoid(
                    confirmVoidBtn.dataset.productLevelId,
                    confirmVoidBtn.dataset.processId,
                    confirmVoidBtn.dataset.subProcessId,
                    confirmVoidBtn.dataset.printerId
                );
                return;
            }

            const resolveBtn = event.target.closest('.js-resolve-status');
            if (resolveBtn) {
                resolveStatus(
                    resolveBtn.dataset.productLevelId,
                    resolveBtn.dataset.processId,
                    resolveBtn.dataset.subProcessId,
                    resolveBtn.dataset.printerId
                );
                return;
            }

            const undoBtn = event.target.closest('.js-undo');
            if (undoBtn) {
                undoOne(
                    undoBtn.dataset.productLevelId,
                    undoBtn.dataset.processId,
                    undoBtn.dataset.subProcessId,
                    undoBtn.dataset.printerId
                );
                return;
            }

            if (event.target.closest('.js-open-finished')) {
                openFinishedDrawer();
                if (!(state.recent_finished_rows || []).length) {
                    loadFinishedRows();
                }
                return;
            }

            if (event.target.closest('.js-close-finished')) {
                closeFinishedDrawer();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (state.barcodeCameraOpen) { stopBarcodeCamera(); focusBarcodeInput(); return; }
                if (state.timerSettingsOpen) { closeTimerSettings(); focusBarcodeInput(); return; }
                if (state.finishedDrawerOpen) { closeFinishedDrawer(); focusBarcodeInput(); }
                return;
            }

            if (!barcodeCheckoutEnabled || state.barcodeCameraOpen) return;
            if (event.ctrlKey || event.altKey || event.metaKey) return;
            const target = event.target;
            const targetIsBarcode = target && target.id === 'barcodeInput';
            if (isEditableElement(target) && !targetIsBarcode) return;

            if (/^[0-9A-Za-z\-]$/.test(event.key)) {
                if (targetIsBarcode) {
                    return;
                }
                queueGlobalBarcodeDigit(event.key);
                event.preventDefault();
                return;
            }

            if (event.key === 'Enter') {
                const input = document.getElementById('barcodeInput');
                if (getBarcodeAutoSubmitEnabled() && input && String(input.value || '').trim()) {
                    event.preventDefault();
                    checkoutBarcode();
                }
            }
        });

        document.addEventListener('click', function(event) {
            const target = event.target;
            if (!barcodeCheckoutEnabled || state.barcodeCameraOpen) return;
            if (target.closest('#timerSettingsModal') || target.closest('#barcodeCameraModal')) return;
            if (isEditableElement(target) && target.id !== 'barcodeInput') return;
            if (target.closest('.drawer')) return;
            if (target.id === 'barcodeInput') return;
            focusBarcodeInput(false);
        });

        initFinishStaffId();
        state.kdsTwoStepCheckout = kdsTwoStepCheckoutDefault;
        state.checkoutQtyMode = 1;
        initTimerThresholds();
        initSoundSettings();
        initSoundUI();
        initBarcodeSettings();
        applyAppearanceSettings();
        initAppearancePanel();
        initSettingsTabs();
        kdsLogActivity('APP_START', 'CID:' + _kdsCid, 0);

        function showPollingOfflineBanner() {
            var existing = document.getElementById('kdsOfflineBanner');
            if (existing) return;
            var banner = document.createElement('div');
            banner.id = 'kdsOfflineBanner';
            banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:200;background:#e44c3a;color:#fff;text-align:center;padding:10px 16px;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:12px;box-shadow:0 4px 12px rgba(0,0,0,.2)';
            banner.innerHTML = '⚠️ ระบบ OFFLINE — ไม่สามารถเชื่อมต่อฐานข้อมูลได้ <button onclick="hidePollingOfflineBanner();kdsStartupCheck()" style="padding:4px 14px;background:#fff;color:#e44c3a;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">🔄 ลองใหม่</button>';
            document.body.prepend(banner);
        }
        function hidePollingOfflineBanner() {
            var banner = document.getElementById('kdsOfflineBanner');
            if (banner) banner.remove();
        }

        function showDbConnectingState() {
            var wrap = document.getElementById('activeCards');
            if (wrap) wrap.innerHTML = '<div class="empty" style="display:flex;flex-direction:column;align-items:center;gap:14px;padding:40px 20px">' +
                '<div style="font-size:32px">🔌</div>' +
                '<div style="font-size:16px;font-weight:700;color:var(--primary)">กำลังเชื่อมต่อฐานข้อมูล...</div>' +
                '</div>';
            document.getElementById('queueSummary').textContent = 'กำลังเชื่อมต่อ...';
        }

        function showDbErrorState(msg) {
            var wrap = document.getElementById('activeCards');
            if (wrap) wrap.innerHTML = '<div class="empty" style="display:flex;flex-direction:column;align-items:center;gap:14px;padding:40px 20px">' +
                '<div style="font-size:36px">⚠️</div>' +
                '<div style="font-size:16px;font-weight:700;color:var(--danger)">เชื่อมต่อฐานข้อมูลไม่สำเร็จ</div>' +
                '<div style="font-size:13px;color:var(--muted);max-width:320px;text-align:center;word-break:break-all">' + (msg || '') + '</div>' +
                '<button id="kdsDbRetryBtn" style="margin-top:6px;padding:10px 28px;background:var(--primary);color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit">🔄 ลองเชื่อมต่อใหม่</button>' +
                '</div>';
            document.getElementById('queueSummary').textContent = 'เชื่อมต่อไม่สำเร็จ';
            var retryBtn = document.getElementById('kdsDbRetryBtn');
            if (retryBtn) retryBtn.addEventListener('click', kdsStartupCheck);
        }

        function kdsStartupCheck() {
            showDbConnectingState();
            kdsApiFetch(_kdsBase + '/api_checker.php?action=get_system_settings&_=' + Date.now(), { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (!d.success) { showDbErrorState('การตั้งค่าระบบไม่สมบูรณ์'); return; }
                applyOutOfStockEnabled(d.settings.out_of_stock_enabled !== 0);
                applyStaffLoginMode(Number(d.settings.hide_staff_login || 0) === 1);
                state.checkoutQtyMode = Number(d.settings.checkout_qty_mode || 1);
                state.showOrderNumber = Number(d.settings.show_order_number || 0) === 1;
                var dbOk = (d.connection_message === 'เชื่อมต่อฐานข้อมูลปัจจุบันได้');
                if (!dbOk) { showDbErrorState(d.connection_message); return; }
                // DB OK — restore staff แล้วโหลดข้อมูล
                if (typeof window.kdsDoStaffRestore === 'function') window.kdsDoStaffRestore();
                loadAll().then(function() {
                    focusBarcodeInput();
                    if (getBarcodeCameraEnabled() && barcodeMediaSupported && barcodeCameraSupported) {
                        setTimeout(openBarcodeCamera, 1500);
                    }
                });
            })
            .catch(function() {
                showDbErrorState('เชื่อมต่อระบบไม่สำเร็จ กรุณาตรวจสอบเครือข่าย');
            });
        }

        kdsStartupCheck();

        setInterval(function() {
            if (isSubmitting) return;
            activeRefreshTick += 1;
            try {
                loadActiveRows();
                if (state.finishedDrawerOpen || activeRefreshTick % finishedRefreshEvery === 0) {
                    loadFinishedRows();
                }
                if (window._kdsGetCurrentZoneId && window._kdsGetCurrentZoneId() > 0 && activeRefreshTick % 4 === 0) {
                    window._kdsFilterCardsByZone(window._kdsGetCurrentZoneId());
                }
            } catch (e) {
                console.warn('auto-refresh error', e);
            }
        }, refreshMs);
    </script>

    <script>
    (function(){
        var btn = document.getElementById("fsBtn");
        if(!btn) return;

        function updateIcon(){
            var full = !!document.fullscreenElement;
            btn.querySelector(".fs-ico-enter").style.display = full ? "none" : "inline";
            btn.querySelector(".fs-ico-exit").style.display  = full ? "inline" : "none";
            btn.title = full ? "ออกจากเต็มจอ" : "เต็มจอ";
            document.body.classList.toggle('fs-ui-hidden', full);
        }
        btn.addEventListener("click", function(){
            if(!document.fullscreenElement){
                document.documentElement.requestFullscreen().catch(function(){});
            } else {
                document.exitFullscreen().catch(function(){});
            }
        });
        document.addEventListener("fullscreenchange", updateIcon);
        updateIcon();
    })();
    </script>

    <!-- Zone Modal -->
    <!-- Checkout Qty Modal (mode 3) -->
    <div class="modal-backdrop" id="checkoutQtyBackdrop" style="display:none;opacity:0;pointer-events:none">
        <div class="modal" style="width:300px;max-width:92vw;padding:0;overflow:hidden">
            <div class="modal-head" style="padding:14px 18px">
                <span class="panel-title" style="font-size:15px">เลือกจำนวน Checkout</span>
            </div>
            <div style="padding:16px 18px 20px">
                <div id="checkoutQtyProductLabel" style="font-size:13px;color:var(--muted);margin-bottom:14px;word-break:break-word"></div>
                <div style="display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:20px">
                    <button id="checkoutQtyMinus" class="btn btn-neutral" style="min-width:44px;min-height:44px;font-size:22px;padding:0">−</button>
                    <span id="checkoutQtyDisplay" style="font-size:32px;font-weight:bold;min-width:48px;text-align:center;color:var(--text)">1</span>
                    <button id="checkoutQtyPlus" class="btn btn-neutral" style="min-width:44px;min-height:44px;font-size:22px;padding:0">+</button>
                </div>
                <div id="checkoutQtyMaxLabel" style="font-size:12px;color:var(--muted);text-align:center;margin-bottom:16px"></div>
                <div style="display:flex;gap:8px">
                    <button id="checkoutQtyCancelBtn" class="btn btn-neutral" style="flex:1">ยกเลิก</button>
                    <button id="checkoutQtyConfirmBtn" class="btn btn-checkout-dark" style="flex:2">Checkout</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="zoneModalBackdrop">
        <div class="modal" id="zoneModal" style="width:340px;max-width:95vw;max-height:80vh;display:flex;flex-direction:column">
            <div class="modal-head">
                <h2 class="modal-title">📍 เลือกโซน</h2>
            </div>
            <div class="modal-body" style="padding:12px 16px">
                <div id="zoneList" style="display:flex;flex-direction:column;gap:8px">
                    <button class="btn btn-primary zone-item" data-zoneid="0" style="width:100%;text-align:left;padding:12px 16px;border-radius:12px;font-size:15px">🌐 ทั้งหมด</button>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-neutral" id="zoneModalCloseBtn">ปิด</button>
            </div>
        </div>
    </div>

    <script>
    // ── Shop Dropdown ────────────────────────────────────
    (function(){
        const shopSel  = document.getElementById('settingsShopId');
        const shopName = document.getElementById('settingsShopNameBox');
        if (!shopSel) return;

        function updateShopName() {
            const opt = shopSel.options[shopSel.selectedIndex];
            if (!opt || parseInt(opt.value) === 0) {
                if (shopName) shopName.textContent = '-';
            } else {
                const parts = opt.textContent.split(' — ');
                if (shopName) shopName.textContent = parts.length > 1 ? parts[1] : opt.textContent;
            }
        }

        // เรียกจาก openTimerSettings() หลังได้ข้อมูลจาก server พร้อมกัน
        window.populateShopDropdown = function(shops, selectedId) {
            while (shopSel.options.length > 1) shopSel.remove(1);
            shops.forEach(function(s) {
                const opt = document.createElement('option');
                opt.value = s.shop_id;
                opt.textContent = s.shop_id + ' — ' + s.shop_name;
                if (s.shop_id === selectedId) opt.selected = true;
                shopSel.appendChild(opt);
            });
            updateShopName();
        };

        shopSel.addEventListener('change', updateShopName);
    })();

    // ── Zone Filter ─────────────────────────────────────
        // cache table IDs ของ zone ที่เลือกไว้ ใช้กรองหลัง render โดยไม่ต้องยิง API ซ้ำ
        let cachedZoneTableIds = null; // null = ทั้งหมด, Set = กรองตาม zone

        function applyZoneFilterSync() {
            if (cachedZoneTableIds === null) return;
            document.querySelectorAll('article[data-table-id]').forEach(function(el) {
                const tid = parseInt(el.dataset.tableId || '0');
                el.style.display = cachedZoneTableIds.has(tid) ? '' : 'none';
            });
        }
    window.applyOutOfStockEnabled = function(enabled) {
        outOfStockControlEnabled = !!enabled;
        const btn = document.getElementById('openSoldOutBtn');
        if (!btn) return;
        btn.style.display = enabled ? '' : 'none';
    };

    (function(){
        let currentZoneId = 0;
        let currentZoneName = 'ทั้งหมด';
        let zones = [];

        const openBtn    = document.getElementById('openZoneBtn');
        const backdrop   = document.getElementById('zoneModalBackdrop');
        const modal      = document.getElementById('zoneModal');
        const closeBtn   = document.getElementById('zoneModalCloseBtn');
        const zoneList   = document.getElementById('zoneList');
        const zoneLabel  = document.getElementById('zoneLabel');

        // โหลด zones จาก API
        async function loadZones() {
            try {
                const r = await kdsApiFetch(_kdsBase + '/api_checker.php?action=list_zones&_=' + Date.now(), { cache: 'no-store' });
                const d = await r.json();
                if (d.success && d.zones && d.zones.length > 0) {
                    zones = d.zones;
                    d.zones.forEach(function(z) {
                        const btn = document.createElement('button');
                        btn.className = 'btn btn-neutral zone-item';
                        btn.dataset.zoneid = z.zoneid;
                        btn.style.cssText = 'width:100%;text-align:left;padding:12px 16px;border-radius:12px';
                        btn.textContent = '📍 ' + z.zonename;
                        zoneList.appendChild(btn);
                    });
                    // อัปเดต label ถ้า zone ถูก force-set ก่อน zones โหลดเสร็จ
                    if (currentZoneId > 0 && zoneLabel) {
                        const zf = zones.find(function(zz) { return zz.zoneid === currentZoneId; });
                        if (zf) zoneLabel.textContent = zf.zonename;
                    }
                }
            } catch(e) { console.warn('load zones error', e); }
        }

        // filter cards ตาม zone
        function applyZoneFilter() {
            if (currentZoneId === 0) {
                cachedZoneTableIds = null;
                document.querySelectorAll('article[data-table-id]').forEach(function(el){
                    el.style.display = '';
                });
                return;
            }
            filterCardsByZone(currentZoneId);
        }

        function filterCardsByZone(zoneid) {
            kdsApiFetch(_kdsBase + '/api_checker.php?action=list_tables_in_zone&zoneid=' + zoneid + '&_=' + Date.now(), { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.success || !d.table_ids) return;
                cachedZoneTableIds = new Set(d.table_ids.map(Number));
                applyZoneFilterSync();
            }).catch(function(e){ console.warn('zone filter error', e); });
        }

        // event: เปิด modal
        if (openBtn) {
            openBtn.addEventListener('click', function(){
                backdrop.classList.add('open');
                modal.classList.add('open');
                // update active state
                document.querySelectorAll('.zone-item').forEach(function(b){
                    b.classList.toggle('btn-primary', parseInt(b.dataset.zoneid) === currentZoneId);
                    b.classList.toggle('btn-neutral', parseInt(b.dataset.zoneid) !== currentZoneId);
                });
            });
        }

        function closeZoneModal() {
            backdrop.classList.remove('open');
            modal.classList.remove('open');
        }
        if (closeBtn) closeBtn.addEventListener('click', closeZoneModal);
        if (backdrop) backdrop.addEventListener('click', function(e){ if(e.target === backdrop) closeZoneModal(); });

        // event: เลือก zone
        if (zoneList) {
            zoneList.addEventListener('click', function(e){
                const btn = e.target.closest('.zone-item');
                if (!btn) return;
                currentZoneId   = parseInt(btn.dataset.zoneid);
                currentZoneName = currentZoneId === 0 ? 'ทั้งหมด' : btn.textContent.replace('📍 ', '').trim();
                if (zoneLabel) zoneLabel.textContent = currentZoneName;
                localStorage.setItem('checker_zone_id', String(currentZoneId));
                localStorage.setItem('checker_zone_name', currentZoneName);
                kdsLogActivity('ZONE_CHANGE', 'Zone:' + currentZoneName + '(' + currentZoneId + ')', Number(localStorage.getItem('checker_finish_staff_id') || 0));
                closeZoneModal();
                applyZoneFilter();
            });
        }

        // คืนค่า zone จาก localStorage (จำ zone ข้ามรอบ refresh)
        (function restoreZone(){
            const savedId = parseInt(localStorage.getItem('checker_zone_id') || '0');
            const savedName = localStorage.getItem('checker_zone_name') || 'ทั้งหมด';
            if (savedId > 0) {
                currentZoneId   = savedId;
                currentZoneName = savedName;
                if (zoneLabel) zoneLabel.textContent = currentZoneName;
                filterCardsByZone(currentZoneId);
            }
        })();

        // expose สำหรับ setInterval ใน script block หลัก
        window._kdsGetCurrentZoneId = function() { return currentZoneId; };
        window._kdsFilterCardsByZone = filterCardsByZone;

        // โหลด zones ตอนเริ่ม
        loadZones();
    })();

    // ── Staff Login (topbar) ─────────────────────────────
    (function(){
        const loginArea   = document.getElementById('staffLoginArea');
        const loggedArea  = document.getElementById('staffLoggedArea');
        const codeInput   = document.getElementById('topStaffCodeInput');
        const loginBtn    = document.getElementById('topStaffLoginBtn');
        const logoutBtn   = document.getElementById('topStaffLogoutBtn');
        const nameLabel   = document.getElementById('topStaffNameLabel');
        if (!loginArea) return;

        function showLoggedIn(name) {
            staffIsLoggedIn = true;
            nameLabel.textContent = '👤 ' + name;
            loginArea.style.display = 'none';
            loggedArea.style.display = 'flex';
            focusBarcodeInput(); // หลัง login สำเร็จ → ส่ง focus ไปบาร์โค้ดทันที
        }
        function showLoggedOut() {
            staffIsLoggedIn = false;
            codeInput.value = '';
            loginArea.style.display = 'flex';
            loggedArea.style.display = 'none';
        }

        function doLogin() {
            const code = codeInput.value.trim().toUpperCase();
            if (!code) { showNotice('กรุณากรอก Staff Code', 'error'); return; }
            loginBtn.disabled = true;
            loginBtn.textContent = 'กำลังค้นหา...';
            kdsApiFetch(_kdsBase + '/api_checker.php?action=lookup_staff_by_code&staff_code=' + encodeURIComponent(code) + '&_=' + Date.now(), { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.success) {
                    localStorage.setItem('checker_finish_staff_id', String(d.staff_id));
                    localStorage.setItem('checker_staff_logged_in', '1');
                    showLoggedIn(d.staff_name);
                    kdsLogActivity('LOGIN', 'Staff:' + d.staff_name, d.staff_id);
                    showNotice('เข้าสู่ระบบแล้ว: ' + d.staff_name, 'success');
                } else {
                    showNotice(d.error || 'ไม่พบพนักงาน', 'error');
                }
            })
            .catch(function(){ showNotice('เกิดข้อผิดพลาด', 'error'); })
            .finally(function(){
                loginBtn.disabled = false;
                loginBtn.textContent = 'เข้าสู่ระบบ';
            });
        }

        loginBtn.addEventListener('click', doLogin);
        codeInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') doLogin(); });

        logoutBtn.addEventListener('click', function(){
            const logoutStaffId = Number(localStorage.getItem('checker_finish_staff_id') || 0);
            kdsLogActivity('LOGOUT', 'Staff:' + (nameLabel.textContent || '-'), logoutStaffId);
            localStorage.setItem('checker_finish_staff_id', String(defaultFinishStaffId));
            localStorage.setItem('checker_staff_logged_in', '0');
            showLoggedOut();
            showNotice('ออกจากระบบแล้ว', 'success');
        });

        // restore staff — ถูกเรียกโดย kdsStartupCheck() หลังยืนยัน DB เชื่อมต่อสำเร็จ
        window.kdsDoStaffRestore = function() {
            if (staffLoginHidden) return; // mode ล็อค — ไม่ต้อง restore, staffIsLoggedIn=true แล้ว
            var savedId    = localStorage.getItem('checker_finish_staff_id');
            var wasLoggedIn = localStorage.getItem('checker_staff_logged_in') === '1';
            if (wasLoggedIn && savedId && parseInt(savedId) > 0) {
                kdsApiFetch(_kdsBase + '/api_checker.php?action=lookup_staff_name&staff_id=' + savedId + '&_=' + Date.now(), { cache: 'no-store' })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (codeInput.value.trim() !== '') return;
                    if (d.success && d.staff_name) showLoggedIn(d.staff_name); else showLoggedOut();
                })
                .catch(function(){ if (codeInput.value.trim() === '') showLoggedOut(); });
            } else {
                showLoggedOut();
            }
        };
    })();

    // ── Staff Code Lookup ────────────────────────────────
    (function(){
        const codeInput  = document.getElementById('settingsStaffCode');
        const staffIdHid = document.getElementById('settingsFinishStaffId');
        const nameBox    = document.getElementById('settingsStaffNameBox');
        if (!codeInput) return;

        let debounceTimer;

        codeInput.addEventListener('input', function(){
            const code = this.value.trim().toUpperCase();
            this.value = code;
            clearTimeout(debounceTimer);
            if (!code) {
                nameBox.textContent = 'ยังไม่ได้เลือกพนักงาน';
                if (staffIdHid) staffIdHid.value = '';
                return;
            }
            nameBox.textContent = 'กำลังค้นหา...';
            debounceTimer = setTimeout(function(){
                lookupStaffCode(code);
            }, 500);
        });

        codeInput.addEventListener('blur', function(){
            const code = this.value.trim().toUpperCase();
            if (code) lookupStaffCode(code);
        });

        function lookupStaffCode(code) {
            kdsApiFetch(_kdsBase + '/api_checker.php?action=lookup_staff_by_code&staff_code=' + encodeURIComponent(code) + '&_=' + Date.now(), { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.success) {
                    nameBox.textContent = '✅ ' + d.staff_name;
                    nameBox.style.color = 'var(--success, #16a34a)';
                    if (staffIdHid) staffIdHid.value = d.staff_id;
                } else {
                    nameBox.textContent = '❌ ' + (d.error || 'ไม่พบพนักงาน');
                    nameBox.style.color = 'var(--danger, #dc2626)';
                    if (staffIdHid) staffIdHid.value = '';
                }
            })
            .catch(function(){ nameBox.textContent = 'เกิดข้อผิดพลาด'; });
        }

        // populate staffcode จาก saved settings
        const savedId = localStorage.getItem('checker_finish_staff_id');
        if (savedId && parseInt(savedId) > 0) {
            // โหลดชื่อจาก staff_id เดิม
            kdsApiFetch(_kdsBase + '/api_checker.php?action=lookup_staff_name&staff_id=' + savedId + '&_=' + Date.now(), { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.success && d.staff_name) {
                    nameBox.textContent = d.staff_name;
                    if (staffIdHid) staffIdHid.value = savedId;
                }
            }).catch(function(){});
        }
    })();
    </script>

</body>
</html>
