<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';
$machineDisplayName = function_exists('getMachineDisplayName') ? getMachineDisplayName() : '';
$_pageCid    = isset($_GET['cid'])  ? (int)$_GET['cid']  : (int)CURRENT_COMPUTER_ID;
$_isServe    = isset($_GET['mode']) && $_GET['mode'] === 'serve';
$_pageTitle  = $_isServe ? 'Serve Display' : 'Staff Display';
writeUsageLog($_isServe ? 'SERVE_PAGE_LOAD' : 'PAGE_LOAD', ['cid' => $_pageCid]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo h($_pageTitle); ?></title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="apple-touch-icon" href="logo.svg">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="<?php echo $_isServe ? '#0d9488' : '#1683ff'; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Staff Display">
    <meta name="mobile-web-app-capable" content="yes">
    <style>
        :root{
            --bg:#edf5ff;--bg2:#fff7ed;
            --line:#dbe8f7;
            --primary:#1683ff;
            --secondary:#ff8a1f;
            --success:#12a150;
            --danger:#e44c3a;
            --muted:#6b7a90;
            --text:#122033;
            --shadow:0 8px 24px rgba(15,23,42,.10);
        }
        *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
        html,body{margin:0;min-height:100%}
        body{
            font-family:Tahoma,Arial,sans-serif;color:var(--text);
            background:
                radial-gradient(circle at top left,rgba(22,131,255,.12),transparent 28%),
                radial-gradient(circle at top right,rgba(255,138,31,.14),transparent 24%),
                linear-gradient(180deg,var(--bg),var(--bg2));
        }

        /* Serve mode overrides */
        body.serve-mode{
            background:
                radial-gradient(circle at top left,rgba(13,148,136,.12),transparent 28%),
                radial-gradient(circle at top right,rgba(234,88,12,.10),transparent 24%),
                linear-gradient(180deg,#f0fdfa,#fff7ed);
        }
        body.serve-mode .topbar{
            background:linear-gradient(135deg,rgba(4,78,70,.92),rgba(13,148,136,.88),rgba(234,88,12,.80));
            box-shadow:0 6px 18px rgba(4,78,70,.20);
        }
        /* serve card colours */
        body.serve-mode .table-card.s-ready{
            border-color:#86efac;
            background:linear-gradient(180deg,#dcfce7,#fff);
            box-shadow:0 0 0 3px rgba(22,163,74,.20),var(--shadow);
            animation:pulse-ready 2s ease-in-out infinite;
        }
        @keyframes pulse-ready{
            0%,100%{box-shadow:0 0 0 3px rgba(22,163,74,.20),var(--shadow)}
            50%{box-shadow:0 0 0 6px rgba(22,163,74,.35),var(--shadow)}
        }
        body.serve-mode .table-card.s-cooking{
            border-color:#fed7aa;
            background:linear-gradient(180deg,#fff7ed,#fff);
            box-shadow:0 0 0 3px rgba(234,88,12,.12),var(--shadow);
        }
        body.serve-mode .table-card.s-ready .tc-name{color:#15803d}
        body.serve-mode .table-card.s-cooking .tc-name{color:#c2410c}
        body.serve-mode .tc-badge.srv-ready{color:#16a34a}
        body.serve-mode .tc-badge.srv-cooking{color:#ea580c}
        /* serve order rows */
        .order-row.r-srv-ready{background:linear-gradient(90deg,#f0fdf4,#fff)}
        .order-row.r-srv-ready .or-status{color:#16a34a}
        .order-row.r-served{background:#f9fafb;opacity:.6}
        .order-row.r-served .or-name{color:#9ca3af;text-decoration:line-through}
        .order-row.r-served .or-qty{color:#9ca3af}

        /* Topbar */
        .topbar{
            position:sticky;top:0;z-index:30;
            padding:9px 14px 8px;
            backdrop-filter:blur(12px);
            background:linear-gradient(135deg,rgba(8,58,112,.92),rgba(22,131,255,.88),rgba(255,138,31,.84));
            color:#fff;box-shadow:0 6px 18px rgba(8,58,112,.18);
        }
        .topbar-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:10px}
        .brand{display:flex;align-items:center;gap:8px;min-width:0}
        .brand-name{font-size:20px;font-weight:bold;white-space:nowrap}
        .machine-chip{
            display:inline-flex;align-items:center;padding:2px 10px;border-radius:999px;
            background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);
            font-size:11px;font-weight:700;white-space:nowrap;flex-shrink:0
        }
        .topbar-actions{display:flex;align-items:center;gap:7px;flex-shrink:0}
        .status-dot{width:8px;height:8px;border-radius:50%;background:var(--success);flex-shrink:0;transition:background .3s}
        .status-dot.loading{background:#f5a623}
        .status-dot.error{background:var(--danger)}
        .btn-action{
            appearance:none;height:34px;padding:0 13px;border-radius:9px;
            font-size:12px;font-weight:bold;cursor:pointer;white-space:nowrap;
            background:rgba(255,255,255,.16);color:#fff;
            border:1px solid rgba(255,255,255,.2);transition:background .14s
        }
        .btn-action:active{background:rgba(255,255,255,.32)}
        a.btn-action{text-decoration:none;display:inline-flex;align-items:center;line-height:34px}
        .btn-fs{
            appearance:none;width:34px;height:34px;border-radius:9px;border:none;cursor:pointer;
            background:rgba(255,255,255,.16);color:#fff;
            border:1px solid rgba(255,255,255,.2);
            display:inline-flex;align-items:center;justify-content:center;flex-shrink:0
        }
        .btn-fs svg{width:15px;height:15px}

        /* ── Login overlay ── */
        .login-overlay{
            position:fixed;inset:0;z-index:200;
            background:linear-gradient(135deg,#0a3d80 0%,#1683ff 55%,#e8823a 100%);
            display:flex;align-items:center;justify-content:center;
            transition:opacity .25s;
        }
        .login-overlay.hidden{opacity:0;pointer-events:none;}
        .login-card{
            background:#fff;border-radius:22px;padding:40px 36px 36px;
            width:340px;max-width:90vw;
            box-shadow:0 24px 60px rgba(8,40,100,.28);
            display:flex;flex-direction:column;align-items:center;gap:0;
        }
        .login-logo{width:56px;height:56px;border-radius:12px;margin-bottom:14px;}
        .login-title{font-size:22px;font-weight:800;color:#0a2540;margin:0 0 4px;}
        .login-sub{font-size:13px;color:#7a8fa6;margin:0 0 22px;text-align:center;}
        .login-input{
            width:100%;box-sizing:border-box;
            height:46px;border-radius:12px;border:1.5px solid #d8e3ef;
            padding:0 14px;font-size:15px;color:#0a2540;outline:none;
            transition:border-color .15s;margin-bottom:10px;
            text-align:center;letter-spacing:2px;
        }
        .login-input:focus{border-color:#1683ff;}
        .login-error{font-size:12px;color:#e53e3e;min-height:18px;margin-bottom:6px;text-align:center;}
        .login-btn{
            width:100%;height:46px;border-radius:12px;border:none;cursor:pointer;
            background:linear-gradient(135deg,#1260cc,#1683ff);color:#fff;
            font-size:15px;font-weight:700;letter-spacing:.5px;
            transition:opacity .15s;margin-top:4px;
        }
        .login-btn:disabled{opacity:.6;cursor:default;}
        .login-btn:not(:disabled):active{opacity:.85;}
        .staff-chip{
            display:inline-flex;align-items:center;gap:5px;
            padding:2px 10px 2px 7px;border-radius:999px;
            background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.2);
            font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;
            transition:background .14s;max-width:140px;overflow:hidden;
        }
        .staff-chip:hover{background:rgba(255,255,255,.28);}
        .staff-chip svg{width:13px;height:13px;flex-shrink:0;}
        #staffNameChip{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

        /* Zone bar */
        .zone-bar{background:rgba(255,255,255,.95);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
        .zone-bar-inner{max-width:1200px;margin:0 auto;padding:8px 12px;display:flex;gap:7px;overflow-x:auto;scrollbar-width:none}
        .zone-bar-inner::-webkit-scrollbar{display:none}
        .btn-zone{
            appearance:none;height:32px;padding:0 16px;border-radius:999px;
            border:1px solid var(--line);background:#fff;color:var(--muted);
            font-size:13px;font-weight:bold;cursor:pointer;white-space:nowrap;flex-shrink:0;
            transition:background .14s,color .14s,border-color .14s
        }
        .btn-zone.active{background:var(--primary);color:#fff;border-color:var(--primary)}

        /* Table grid */
        .page{max-width:1200px;margin:0 auto;padding:14px 12px 28px}
        .table-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
        .sm-header{
            grid-column:1/-1;
            display:flex;align-items:center;gap:8px;
            font-size:13px;font-weight:700;color:var(--muted);
            padding:6px 4px 2px;
            border-bottom:1.5px solid var(--line);
            margin-bottom:2px;
            letter-spacing:.3px;text-transform:uppercase;
        }
        .table-card{
            background:#fff;border:2px solid var(--line);border-radius:18px;
            padding:14px 10px 12px;text-align:center;cursor:pointer;
            display:flex;flex-direction:column;align-items:center;gap:6px;
            box-shadow:var(--shadow);transition:transform .12s;
            user-select:none
        }
        .table-card:active{transform:scale(.94)}
        .table-card.s-yellow{border-color:#ffe066;background:linear-gradient(180deg,#fffde7,#fff);box-shadow:0 0 0 3px rgba(255,214,0,.2)}
        .table-card.s-red{border-color:#ffb3ab;background:linear-gradient(180deg,#fff2f0,#fff);box-shadow:0 0 0 3px rgba(228,76,58,.15)}
        .table-card.s-done{border-color:#6edda0;background:linear-gradient(180deg,#edfff5,#fff);box-shadow:0 0 0 3px rgba(18,161,80,.18)}
        .table-card.s-empty{border-color:#e5e7eb;background:#f9fafb;box-shadow:none;opacity:.6;cursor:default}
        .tc-name{font-size:22px;font-weight:bold;color:#0f2945;line-height:1.1}
        .tc-open-time{font-size:11px;color:#8fa3bc;font-weight:600;letter-spacing:.3px}
        .table-card.s-done .tc-name{color:#0b7a3e}
        .table-card.s-empty .tc-name{color:#9ca3af}
        .tc-badge{font-size:12px;font-weight:bold;padding:2px 0}
        .tc-badge.kitchen{color:var(--secondary)}
        .tc-badge.done{color:var(--success)}
        .tc-badge.empty{color:#9ca3af}
        .tc-badge.combined{color:#7c3aed}

        /* Modal */
        .modal-overlay{
            display:none;position:fixed;inset:0;z-index:100;
            background:rgba(8,30,60,.55);backdrop-filter:blur(4px);
            align-items:flex-end;justify-content:center
        }
        .modal-overlay.open{display:flex}
        .modal-box{
            background:#fff;border-radius:24px 24px 0 0;width:100%;max-width:600px;
            max-height:88dvh;display:flex;flex-direction:column;
            box-shadow:0 -14px 44px rgba(8,30,60,.22);animation:slideUp .2s ease
        }
        @keyframes slideUp{from{transform:translateY(50px);opacity:0}to{transform:translateY(0);opacity:1}}
        .modal-head{
            display:flex;align-items:center;justify-content:space-between;gap:12px;
            padding:16px 18px 12px;border-bottom:1px solid var(--line);flex-shrink:0
        }
        .modal-title{font-size:22px;font-weight:bold;color:#0f2945}
        .modal-sub{font-size:13px;color:var(--muted);margin-top:3px}
        .modal-close{
            width:36px;height:36px;border-radius:50%;border:none;background:#f0f4f8;
            color:#4a6080;font-size:22px;cursor:pointer;
            display:flex;align-items:center;justify-content:center;flex-shrink:0
        }
        .modal-body{overflow-y:auto;padding:10px 12px 28px;display:flex;flex-direction:column;gap:1px}
        .modal-msg{padding:32px;text-align:center;color:var(--muted);font-weight:bold}

        /* Order rows */
        .order-row{
            display:grid;grid-template-columns:minmax(0,1fr) auto;
            align-items:center;gap:8px;padding:12px 14px
        }
        .order-row:first-child{border-radius:14px 14px 0 0}
        .order-row:last-child{border-radius:0 0 14px 14px}
        .order-row:only-child{border-radius:14px}
        .order-row.r-done{background:#f0fff6}
        .order-row.r-active{background:#fff8f2}
        .order-row.r-voided{background:#f3f4f6;opacity:.6}
        .or-name{font-weight:bold;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .r-done   .or-name{color:#0b7a3e}
        .r-active .or-name{color:#0f2945}
        .r-voided .or-name{color:#9ca3af;text-decoration:line-through}
        .or-time{font-size:11px;color:var(--muted);margin-top:2px}
        .or-elapsed{font-size:11px;font-weight:bold;margin-top:1px}
        .or-elapsed.el-ok{color:var(--success)}
        .or-elapsed.el-warn{color:#d97706}
        .or-elapsed.el-late{color:#dc2626}
        .or-right{text-align:right;flex-shrink:0}
        .or-qty{font-size:18px;font-weight:bold}
        .r-done   .or-qty{color:var(--success)}
        .r-active .or-qty{color:var(--secondary)}
        .r-voided .or-qty{color:#9ca3af}
        .or-status{font-size:11px;font-weight:bold;margin-top:2px;white-space:nowrap}
        .r-done   .or-status{color:var(--success)}
        .r-active .or-status{color:var(--secondary)}
        .r-voided .or-status{color:#9ca3af}

        .hidden{display:none!important}

        @media(min-width:600px){
            .modal-overlay{align-items:center}
            .modal-box{border-radius:24px;max-height:80dvh}
        }
        @media(max-width:480px){
            .table-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
            .table-card{padding:12px 8px 10px}
            .tc-name{font-size:18px}
        }

        /* Settings Panel */
        .sp-overlay{
            display:none;position:fixed;inset:0;z-index:200;
            background:rgba(8,30,60,.55);backdrop-filter:blur(4px);
            align-items:flex-end;justify-content:center
        }
        .sp-overlay.open{display:flex}
        .sp-box{
            background:#fff;border-radius:24px 24px 0 0;width:100%;max-width:520px;
            max-height:90dvh;display:flex;flex-direction:column;
            box-shadow:0 -14px 44px rgba(8,30,60,.22);animation:slideUp .2s ease
        }
        .sp-head{
            display:flex;align-items:center;justify-content:space-between;
            padding:16px 18px 12px;border-bottom:1px solid #e5e7eb;flex-shrink:0
        }
        .sp-title{font-size:18px;font-weight:bold;color:#0f2945}
        .sp-close{
            width:34px;height:34px;border-radius:50%;border:none;background:#f0f4f8;
            color:#4a6080;font-size:20px;cursor:pointer;
            display:flex;align-items:center;justify-content:center
        }
        .sp-body{overflow-y:auto;padding:12px 16px 28px;display:flex;flex-direction:column;gap:16px}
        .sp-section{background:#f8fafc;border-radius:14px;padding:14px 14px 10px}
        .sp-section-title{font-size:12px;font-weight:bold;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
        .sp-row{display:flex;align-items:center;justify-content:space-between;padding:6px 0;gap:12px}
        .sp-row+.sp-row{border-top:1px solid #e5e7eb}
        .sp-label{font-size:14px;font-weight:bold;color:#0f2945;flex:1}
        .sp-sublabel{font-size:11px;color:var(--muted);margin-top:1px}
        .sp-input{
            width:90px;padding:7px 10px;border:1.5px solid #dbe8f7;border-radius:9px;
            font-size:14px;font-weight:bold;text-align:center;color:#0f2945;
            background:#fff;outline:none
        }
        .sp-input:focus{border-color:var(--primary)}
        /* Toggle */
        .sp-toggle{position:relative;width:44px;height:26px;flex-shrink:0}
        .sp-toggle input{opacity:0;width:0;height:0}
        .sp-slider{
            position:absolute;inset:0;border-radius:999px;
            background:#d1d5db;cursor:pointer;transition:background .2s
        }
        .sp-slider:before{
            content:'';position:absolute;width:20px;height:20px;border-radius:50%;
            background:#fff;top:3px;left:3px;transition:transform .2s;
            box-shadow:0 1px 4px rgba(0,0,0,.2)
        }
        .sp-toggle input:checked+.sp-slider{background:var(--primary)}
        .sp-toggle input:checked+.sp-slider:before{transform:translateX(18px)}
        /* Refresh pill buttons */
        .sp-pills{display:flex;gap:6px}
        .sp-pill{
            padding:6px 14px;border-radius:999px;border:1.5px solid #dbe8f7;
            background:#fff;font-size:13px;font-weight:bold;color:var(--muted);cursor:pointer
        }
        .sp-pill.active{background:var(--primary);color:#fff;border-color:var(--primary)}
        /* footer */
        .sp-foot{
            padding:12px 16px 20px;border-top:1px solid #e5e7eb;
            display:flex;gap:10px;flex-shrink:0
        }
        .sp-btn-save{
            flex:1;padding:12px;border-radius:12px;border:none;cursor:pointer;
            background:var(--primary);color:#fff;font-size:15px;font-weight:bold
        }
        .sp-btn-save:active{opacity:.85}
        .sp-btn-cancel{
            padding:12px 18px;border-radius:12px;border:1.5px solid #dbe8f7;
            background:#fff;color:var(--muted);font-size:15px;font-weight:bold;cursor:pointer
        }
        .sp-msg{font-size:13px;text-align:center;padding:4px 0;min-height:20px}
        .sp-msg.ok{color:var(--success)}
        .sp-msg.err{color:var(--danger)}

        /* ── Install banner ── */
        .install-banner{
            position:fixed;bottom:0;left:0;right:0;z-index:190;
            background:#fff;
            border-top:2px solid var(--line);
            box-shadow:0 -4px 24px rgba(15,23,42,.13);
            padding:14px 18px 18px;
            display:flex;align-items:flex-start;gap:14px;
            transform:translateY(100%);
            transition:transform .3s cubic-bezier(.4,0,.2,1);
        }
        .install-banner.show{transform:translateY(0)}
        .install-banner-icon{font-size:32px;line-height:1;flex-shrink:0;margin-top:2px}
        .install-banner-body{flex:1;min-width:0}
        .install-banner-title{font-size:15px;font-weight:700;color:var(--text);margin-bottom:4px}
        .install-banner-sub{font-size:13px;color:var(--muted);line-height:1.5}
        .install-banner-sub ol{margin:6px 0 0 16px;padding:0}
        .install-banner-sub li{margin-bottom:2px}
        .install-banner-actions{display:flex;gap:8px;margin-top:10px}
        .install-btn{
            height:36px;padding:0 16px;border-radius:10px;border:none;cursor:pointer;
            font-size:13px;font-weight:700;
            background:linear-gradient(135deg,#1260cc,#1683ff);color:#fff;
        }
        .install-dismiss{
            height:36px;padding:0 14px;border-radius:10px;cursor:pointer;
            font-size:13px;font-weight:600;color:var(--muted);
            background:none;border:1.5px solid var(--line);
        }
    </style>
</head>
<body<?php echo $_isServe ? ' class="serve-mode"' : ''; ?>>

<!-- Install banner -->
<div id="installBanner" class="install-banner">
    <div class="install-banner-icon">📲</div>
    <div class="install-banner-body">
        <div class="install-banner-title" id="installBannerTitle">ติดตั้งแอปบนมือถือ</div>
        <div class="install-banner-sub" id="installBannerSub">เพิ่มทางลัดไว้บนหน้าจอหลัก เปิดได้เลยโดยไม่ต้องเปิดเบราว์เซอร์</div>
        <div class="install-banner-actions">
            <button class="install-btn" id="installBtn">ติดตั้ง</button>
            <button class="install-dismiss" id="installDismiss">ไม่ใช่ตอนนี้</button>
        </div>
    </div>
</div>

<div id="loginOverlay" class="login-overlay">
    <div class="login-card">
        <img src="logo.svg" alt="" class="login-logo">
        <h1 class="login-title"><?php echo $_isServe ? 'Serve Display' : 'Staff Display'; ?></h1>
        <p class="login-sub">กรอกรหัสพนักงานเพื่อเข้าใช้งาน</p>
        <input type="text" id="loginCode" class="login-input" placeholder="รหัสพนักงาน" autocomplete="off" autocorrect="off" spellcheck="false">
        <div class="login-error" id="loginError"></div>
        <button class="login-btn" id="loginBtn">เข้าสู่ระบบ</button>
    </div>
</div>

<div class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <img src="logo.svg" alt="" style="width:26px;height:26px;border-radius:6px;flex-shrink:0">
            <span class="brand-name"><?php echo $_isServe ? '🍽️ Serve Display' : 'Staff Display'; ?></span>
        </div>
        <div class="topbar-actions">
            <div class="status-dot" id="statusDot"></div>
            <a class="btn-action" href="staff_display.php<?php echo $_isServe ? '' : '?mode=serve'; ?><?php echo $_pageCid > 0 ? ($_isServe ? '?cid='.$_pageCid : '&cid='.$_pageCid) : ''; ?>">
                <?php echo $_isServe ? '🍳 KDS' : '🍽️ เสิร์ฟ'; ?>
            </a>
            <button class="btn-action" id="refreshBtn">รีเฟรช</button>
            <button class="staff-chip" id="logoutBtn" style="display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                <span id="staffNameChip">-</span>
            </button>
            <button class="btn-fs" id="fsBtn" title="เต็มจอ">
                <svg class="fs-enter" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                <svg class="fs-exit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" style="display:none"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/></svg>
            </button>
        </div>
    </div>
</div>

<div id="zoneBar" class="zone-bar<?php echo $_isServe ? ' hidden' : ''; ?>">
    <div class="zone-bar-inner" id="zoneInner"></div>
</div>

<div class="page">
    <div class="table-grid" id="tableGrid">
        <div class="modal-msg" style="grid-column:1/-1">กำลังโหลด...</div>
    </div>
</div>

<div class="modal-overlay" id="tableModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <div class="modal-title" id="modalTitle">โต๊ะ -</div>
                <div class="modal-sub"  id="modalSub"></div>
            </div>
            <button class="modal-close" id="modalClose">×</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>
const REFRESH_MS = <?php echo (int)APP_REFRESH_MS; ?>;
const T_YELLOW   = <?php echo (int)(defined('ALERT_THRESHOLD_YELLOW_DEFAULT') ? ALERT_THRESHOLD_YELLOW_DEFAULT : 10); ?>;
const T_RED      = <?php echo (int)(defined('ALERT_THRESHOLD_RED_DEFAULT')    ? ALERT_THRESHOLD_RED_DEFAULT    : 20); ?>;
const PAGE_CID   = <?php echo (int)(isset($_GET['cid']) && (int)$_GET['cid'] > 0 ? (int)$_GET['cid'] : 0); ?>;
const cidParam   = PAGE_CID > 0 ? '&cid=' + PAGE_CID : '';
const PS_DONE     = 1;
const PS_RESOLVED = 4;
const PS_VOIDED   = 98;
const IS_SERVE    = <?php echo $_isServe ? 'true' : 'false'; ?>;

const state = {
    active:   [],
    finished: [],
    zones:    [],
    zoneId:   null,
    zoneTables:      null,   // Map<string,string> (TableID→TableName) | null
    allowedPrinters: null,   // Set<number> | null (null = no filter)
    serveTables:     [],     // serve mode
};

/* ── Utilities ── */
function safeArray(v){ return Array.isArray(v) ? v : []; }
function esc(s){ return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]); }
function fmtTime(v){
    if(!v) return '-';
    const d = parseServerTime(v);
    return isNaN(d) ? esc(String(v).slice(11,16)||String(v))
        : d.toLocaleTimeString('th-TH',{hour12:false,hour:'2-digit',minute:'2-digit'});
}
function fmtQty(v){
    const n = Number(v||0);
    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.00$/,'');
}
function parseServerTime(v){
    // MySQL datetime 'YYYY-MM-DD HH:MM:SS' — ระบุ +07:00 ป้องกัน Safari/Chrome เก่า parse เป็น UTC
    const s = String(v||'').trim().replace(' ','T');
    if(!s) return new Date(NaN);
    return new Date(s.includes('+') || s.includes('Z') ? s : s + '+07:00');
}
function waitMin(row){
    const d = parseServerTime(row.SubmitOrderDateTime);
    return isNaN(d) ? 0 : Math.max(0,Math.floor((Date.now()-d)/60000));
}
function cookMin(row){
    const start = parseServerTime(row.SubmitOrderDateTime);
    if(isNaN(start)) return -1;
    const end = row.FinishDateTime ? parseServerTime(row.FinishDateTime) : new Date();
    return Math.max(0, Math.floor((end - start) / 60000));
}
function elapsedBadge(row, done, voided){
    if(voided) return '';
    const m = cookMin(row);
    if(m < 0) return '';
    if(done){
        return `<div class="or-elapsed el-ok">⏱ เสร็จใน ${m} นาที</div>`;
    }
    const cls = m >= T_RED ? 'el-late' : m >= T_YELLOW ? 'el-warn' : 'el-ok';
    return `<div class="or-elapsed ${cls}">🕒 ${m} นาที</div>`;
}
function tKey(row){
    // TableID ชนะก่อนเสมอ (รวมถึง moved order ที่ TableID = ปลายทาง)
    const tid = parseInt(row.TableID, 10) || 0;
    if(tid > 0) return String(tid);
    // ไม่มี TableID → delivery/TW: ใช้ DisplayTableName ถ้ามี (เช่น 'LM111', 'GF05')
    const dn = row.DisplayTableName ? String(row.DisplayTableName).trim() : '';
    if(dn) return dn;
    // ใช้ OtfTransactionID (unique ต่อ bill) เพื่อแยกแต่ละ bill ออกจากกัน
    const otfId = parseInt(row.OtfTransactionID, 10) || 0;
    if(otfId > 0) return 'otf' + otfId;
    // fallback: แยกด้วย SaleModeID อย่างน้อย
    return 'sm' + (parseInt(row.SaleModeID, 10) || 0) + '_t0';
}
// สำหรับ order ย้ายโต๊ะ: ถ้า TableID ว่าง ให้ใช้ moved_to (ปลายทาง) แทน DisplayTableName "2->4"
function tKeyEff(row){
    if(!row.TableID && row.is_moved && row.moved_to) return String(row.moved_to);
    return tKey(row);
}
// row.PrinterID ไม่อยู่ใน printerSet → ไม่ใช่ station นี้ → auto-done
// ถ้า set=null (ไม่ได้ config printer) → ไม่กรอง
function nonKds(row, set){
    if(!set || set.size === 0) return false;
    return !row.is_voided && !set.has(parseInt(row.PrinterID, 10));
}
function isNonKds(row){ return nonKds(row, state.allowedPrinters); }
function byZone(rows){
    if(!state.zoneTables) return safeArray(rows);
    return safeArray(rows).filter(r => state.zoneTables.has(tKeyEff(r)));
}

/* ── Group rows by table → card data ── */
function groupTables(active, finished){
    const map = new Map();
    const sessionStarts = new Map(); // key → earliest active non-voided SubmitOrderDateTime
    function get(key, name, smId, smName){
        if(!map.has(key)) map.set(key,{key,name,pending:0,done:0,worst:0,openTime:null,currentTxId:0,hasCombined:false,saleModeId:smId||0,saleModeName:smName||''});
        return map.get(key);
    }
    safeArray(active).forEach(r => {
        if(isHidden(r)) return;
        const key    = tKeyEff(r);
        const name   = r.is_moved && r.moved_to ? String(r.moved_to)
                     : (r.DisplayTableName || (parseInt(r.TableID,10) > 0 ? String(r.TableID) : (r.QueueName || (parseInt(r.OtfTransactionID,10) > 0 ? '#'+parseInt(r.OtfTransactionID,10) : '-'))));
        const smId   = r.SaleModeID   ? parseInt(r.SaleModeID,10)  : 0;
        const smName = r.SaleModeName ? String(r.SaleModeName)      : '';
        const g      = get(key, name, smId, smName);
        if(r.is_combined) g.hasCombined = true;
        if(!r.is_voided && !isNonKds(r)){
            g.pending++;
            g.worst = Math.max(g.worst, waitMin(r));
        } else if(!r.is_voided && isNonKds(r)){
            g.done++;
        }
        if(!r.is_voided && r.SubmitOrderDateTime){
            const t = parseServerTime(r.SubmitOrderDateTime);
            if(!isNaN(t)){
                if(g.openTime === null || t < g.openTime) g.openTime = t;
                const cur = sessionStarts.get(key);
                if(!cur || t < cur) sessionStarts.set(key, t);
            }
        }
    });
    safeArray(finished).forEach(r => {
        if(isHidden(r)) return;
        const key    = tKeyEff(r);
        // skip finished rows that pre-date the current active session
        if(sessionStarts.has(key) && r.SubmitOrderDateTime){
            const t = parseServerTime(r.SubmitOrderDateTime);
            if(!isNaN(t) && t < sessionStarts.get(key)) return;
        }
        const name   = r.is_moved && r.moved_to ? String(r.moved_to)
                     : (r.DisplayTableName || (parseInt(r.TableID,10) > 0 ? String(r.TableID) : (r.QueueName || (parseInt(r.OtfTransactionID,10) > 0 ? '#'+parseInt(r.OtfTransactionID,10) : '-'))));
        const smId   = r.SaleModeID   ? parseInt(r.SaleModeID,10) : 0;
        const smName = r.SaleModeName ? String(r.SaleModeName)    : '';
        const g      = get(key, name, smId, smName);
        if(r.is_combined) g.hasCombined = true;
        g.done++;
    });
    return Array.from(map.values());
}

/* ── Table Card ── */
function cardCls(g){
    if(g.isEmpty)                        return 's-empty';
    if(g.pending === 0 && g.done === 0)  return 's-empty';
    if(g.pending === 0)                  return 's-done';
    if(g.worst >= T_RED)   return 's-red';
    if(g.worst >= T_YELLOW)return 's-yellow';
    return '';
}
function buildCard(g){
    if(g.isEmpty) return `<div class="table-card s-empty">
        <div class="tc-name">${esc(String(g.name))}</div>
        <div class="tc-badge empty">ว่าง</div>
    </div>`;
    const cls = cardCls(g);
    const badges = [];
    if(g.pending > 0) badges.push(`<div class="tc-badge kitchen">🍳 ${g.pending} กำลังทำ</div>`);
    if(g.done    > 0) badges.push(`<div class="tc-badge done">✅ ${g.done} เสร็จแล้ว</div>`);
    if(g.hasCombined)  badges.push(`<div class="tc-badge combined">🔗 รวมโต๊ะแล้ว</div>`);
    const openStr = g.openTime
        ? g.openTime.toLocaleTimeString('th-TH',{hour12:false,hour:'2-digit',minute:'2-digit'})
        : '';
    return `<div class="table-card ${cls}" data-key="${esc(g.key)}" data-name="${esc(String(g.name))}">
        <div class="tc-name">${esc(String(g.name))}</div>
        ${openStr ? `<div class="tc-open-time">⏱ ${openStr}</div>` : ''}
        ${badges.join('')}
    </div>`;
}
function renderGrid(){
    if(IS_SERVE){ renderServeGrid(); return; }

    const wrap = document.getElementById('tableGrid');
    const groups = groupTables(byZone(state.active), byZone(state.finished));
    const seen   = new Set(groups.map(g => g.key));

    if(state.zoneTables){
        // หา SaleMode ของ dine-in จาก groups ที่มีอยู่แล้วใน zone นี้
        const zoneGroup = groups.find(g => !g.isEmpty && state.zoneTables.has(g.key));
        const emptySmId   = zoneGroup ? zoneGroup.saleModeId   : 0;
        const emptySmName = zoneGroup ? zoneGroup.saleModeName : '';
        state.zoneTables.forEach((tname, tid) => {
            if(!seen.has(tid)) groups.push({key:tid,name:tname,pending:0,done:0,worst:0,isEmpty:true,saleModeId:emptySmId,saleModeName:emptySmName});
        });
    }

    groups.sort((a,b) => {
        if(a.isEmpty !== b.isEmpty) return a.isEmpty ? 1 : -1;
        return String(a.name).localeCompare(String(b.name), undefined, {numeric:true, sensitivity:'base'});
    });

    if(!groups.length){
        wrap.innerHTML = '<div class="modal-msg" style="grid-column:1/-1">ไม่มีโต๊ะที่มีออเดอร์</div>';
        return;
    }

    // แยก group ตาม SaleMode
    const modeMap = new Map();
    groups.forEach(g => {
        const mk = g.saleModeId || 0;
        if(!modeMap.has(mk)) modeMap.set(mk, {id:mk, name:g.saleModeName, cards:[]});
        modeMap.get(mk).cards.push(g);
    });

    const multiMode = modeMap.size > 1;

    // เรียง section: SaleModeID น้อย→มาก (dine-in ID ต่ำมักอยู่ก่อน delivery)
    const sortedModes = Array.from(modeMap.values()).sort((a,b) => a.id - b.id);

    function smIcon(id, name){
        const n = (name||'').toLowerCase();
        if(n.includes('grab'))     return '🟢';
        if(n.includes('delivery') || n.includes('เดลิเวอรี')) return '🚚';
        if(n.includes('takeaway') || n.includes('take away') || n.includes('take-away') || n.includes('รับกลับ') || n.includes('กลับบ้าน')) return '🛍️';
        if(n.includes('lineman') || n.includes('line man') || n.includes('ไลน์แมน') || n.includes('ไลน์ แมน')) return '🟡';
        if(n.includes('foodpanda') || n.includes('panda') || n.includes('แพนด้า') || n.includes('ฟู้ดแพนด้า')) return '🐼';
        if(n.includes('dine') || n.includes('ทานที่') || n.includes('นั่งทาน') || n.includes('eat in') || n.includes('eatery')) return '🍽️';
        return '📦';
    }

    let html = '';
    sortedModes.forEach(mode => {
        if(multiMode){
            const label = mode.name && mode.name !== '-' ? mode.name : (mode.id ? 'Mode '+mode.id : 'Dine-in');
            html += `<div class="sm-header">${smIcon(mode.id, mode.name)} ${esc(label)}</div>`;
        }
        html += mode.cards.map(buildCard).join('');
    });
    wrap.innerHTML = html;
}

function renderServeGrid(){
    const wrap   = document.getElementById('tableGrid');
    const readyOnly = (function(){ try{ return JSON.parse(localStorage.getItem('kds_serve_ready_only')||'false'); }catch(e){return false;} })();
    const tables = readyOnly ? state.serveTables.filter(t => t.cooking === 0) : state.serveTables;

    if(!tables.length){
        wrap.innerHTML = '<div class="modal-msg" style="grid-column:1/-1">✅ ไม่มีรายการรออยู่</div>';
        return;
    }

    const sorted = [...tables].sort((a,b) => {
        if((a.cooking===0) !== (b.cooking===0)) return a.cooking===0 ? -1 : 1;
        return String(a.name).localeCompare(String(b.name), undefined, {numeric:true, sensitivity:'base'});
    });

    wrap.innerHTML = sorted.map(t => {
        const cls = t.cooking===0 ? 's-ready' : 's-cooking';
        const badges = [];
        if(t.ready   > 0) badges.push(`<div class="tc-badge srv-ready">✅ ${t.ready} พร้อมเสิร์ฟ</div>`);
        if(t.cooking > 0) badges.push(`<div class="tc-badge srv-cooking">🍳 ${t.cooking} กำลังทำ</div>`);
        return `<div class="table-card ${cls}" data-key="${esc(t.key)}" data-name="${esc(t.name)}" data-txid="${t.transaction_id||0}" data-date="${esc(t.order_date||'')}">
            <div class="tc-name">${esc(t.name)}</div>
            ${badges.join('')}
        </div>`;
    }).join('');
}

/* ── Modal ── */
function isHidden(r){ return r.is_old_session; }
function getTransactionId(key){
    const row = safeArray(state.active).find(r => tKeyEff(r) === key && !isHidden(r))
             || safeArray(state.finished).find(r => tKeyEff(r) === key && !isHidden(r))
             || safeArray(state.finished).find(r => tKeyEff(r) === key);
    return row && row.TransactionID ? parseInt(row.TransactionID, 10) : 0;
}
function getOrderDate(key){
    const row = safeArray(state.active).find(r => tKeyEff(r) === key && !isHidden(r))
             || safeArray(state.finished).find(r => tKeyEff(r) === key && !isHidden(r))
             || safeArray(state.finished).find(r => tKeyEff(r) === key);
    return row && row.OrderDate ? String(row.OrderDate).slice(0,10) : '';
}
// หาเวลาเริ่มออเดอร์แรกสุดของ session ปัจจุบัน (จาก non-hidden rows ใน state)
function getSessionStart(key){
    const rows = [
        ...safeArray(state.active).filter(r => tKeyEff(r) === key && !isHidden(r)),
        ...safeArray(state.finished).filter(r => tKeyEff(r) === key && !isHidden(r)),
    ];
    if(!rows.length) return '';
    return rows.reduce((min, r) => {
        const t = r.SubmitOrderDateTime || '';
        return t && (!min || t < min) ? t : min;
    }, '');
}
function buildRow(row, printerSet){
    const st       = parseInt(row.ProcessStatus, 10);
    const autoDone = nonKds(row, printerSet);
    const done     = st === PS_DONE || st === PS_RESOLVED || autoDone;
    const voided   = !autoDone && st === PS_VOIDED;
    const cls    = done ? 'r-done' : voided ? 'r-voided' : 'r-active';
    const lbl    = autoDone ? '✅ เสร็จแล้ว (ไม่ใช่จอนี้)' : done ? '✅ เสร็จแล้ว' : voided ? '🚫 ยกเลิก' : '🍳 กำลังทำ';
    const name = row.parent_name
        ? `${esc(row.parent_name)} · ${esc(row.ProductName||'-')}`
        : esc(row.ProductName||'-');
    const time = done && !autoDone
        ? `ส่ง ${esc(fmtTime(row.SubmitOrderDateTime))} · เสร็จ ${esc(fmtTime(row.FinishDateTime))}`
        : `ส่ง ${esc(fmtTime(row.SubmitOrderDateTime))}`;
    return `<div class="order-row ${cls}">
        <div>
            <div class="or-name">${name}</div>
            <div class="or-time">${time}</div>
            ${elapsedBadge(row, done, voided)}
        </div>
        <div class="or-right">
            <div class="or-qty">x${fmtQty(row.ProductAmount)}</div>
            <div class="or-status">${lbl}</div>
        </div>
    </div>`;
}
function buildServeRow(row){
    const st     = parseInt(row.ProcessStatus, 10);
    const served = !!row.ServingDateTime;
    const isDone = st === PS_DONE || st === PS_RESOLVED;
    const isCook = !isDone && st !== PS_VOIDED;
    let cls, lbl;
    if(served)       { cls='r-served';    lbl='🚚 เสิร์ฟแล้ว'; }
    else if(isDone)  { cls='r-srv-ready'; lbl='✅ พร้อมเสิร์ฟ'; }
    else if(isCook)  { cls='r-active';   lbl='🍳 กำลังทำ'; }
    else             { cls='r-voided';   lbl='🚫 ยกเลิก'; }
    const name = row.parent_name && String(row.parent_name).trim()
        ? `${esc(row.parent_name)} · ${esc(row.ProductName||'-')}`
        : esc(row.ProductName||'-');
    const finOk = isDone && row.FinishDateTime && row.FinishDateTime !== '0000-00-00 00:00:00';
    const time  = finOk
        ? `ส่ง ${esc(fmtTime(row.SubmitOrderDateTime))} · เสร็จ ${esc(fmtTime(row.FinishDateTime))}`
        : `ส่ง ${esc(fmtTime(row.SubmitOrderDateTime))}`;
    return `<div class="order-row ${cls}">
        <div>
            <div class="or-name">${name}</div>
            <div class="or-time">${time}</div>
        </div>
        <div class="or-right">
            <div class="or-qty">x${fmtQty(row.ProductAmount)}</div>
            <div class="or-status">${lbl}</div>
        </div>
    </div>`;
}
function openModal(key, name){
    if(_modalController) _modalController.abort();
    _modalController = new AbortController();
    const msig = _modalController.signal;

    document.getElementById('modalTitle').textContent = 'โต๊ะ ' + name;
    document.getElementById('modalSub').textContent   = '';
    document.getElementById('modalBody').innerHTML    = '<div class="modal-msg">กำลังโหลด...</div>';
    document.getElementById('tableModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    if(IS_SERVE){
        const t      = state.serveTables.find(t => t.key === key) || {};
        const txId   = t.transaction_id || 0;
        const txParam= txId > 0 ? '&transaction_id='+txId : '';
        const od     = t.order_date || '';
        const odParam= !txParam && od ? '&order_date='+encodeURIComponent(od) : '';
        fetch('api_checker.php?action=list_serve_table_orders&table_id='+encodeURIComponent(key)+txParam+odParam+'&_='+Date.now(),{cache:'no-store',signal:msig})
            .then(r=>r.json())
            .then(json=>{
                if(!json.success) throw new Error(json.error||'error');
                const rows    = safeArray(json.rows).filter(r=>parseInt(r.ProcessStatus,10)!==PS_VOIDED);
                const nReady  = rows.filter(r=>{const s=parseInt(r.ProcessStatus,10);return (s===PS_DONE||s===PS_RESOLVED)&&!r.ServingDateTime;}).length;
                const nCook   = rows.filter(r=>{const s=parseInt(r.ProcessStatus,10);return s!==PS_DONE&&s!==PS_RESOLVED&&s!==PS_VOIDED;}).length;
                const nServed = rows.filter(r=>!!r.ServingDateTime).length;
                document.getElementById('modalSub').textContent=`✅ พร้อมเสิร์ฟ ${nReady}  ·  🍳 ทำอยู่ ${nCook}  ·  🚚 เสิร์ฟแล้ว ${nServed}`;
                document.getElementById('modalBody').innerHTML=rows.length
                    ? rows.map(buildServeRow).join('')
                    : '<div class="modal-msg">ไม่มีรายการ</div>';
            })
            .catch(err=>{if(err.name==='AbortError')return; document.getElementById('modalBody').innerHTML='<div class="modal-msg">โหลดไม่สำเร็จ</div>';});
        return;
    }

    const txId    = getTransactionId(key);
    const txParam = txId > 0 ? '&transaction_id=' + txId : '';
    const od      = getOrderDate(key);
    const odParam = !txParam && od ? '&order_date=' + encodeURIComponent(od) : '';
    const ss      = !txParam ? getSessionStart(key) : '';
    const ssParam = ss ? '&session_start=' + encodeURIComponent(ss) : '';
    fetch('api_checker.php?action=list_table_orders&table_id=' + encodeURIComponent(key) + txParam + odParam + ssParam + cidParam + '&_=' + Date.now(), {cache:'no-store', signal:msig})
        .then(r => r.json())
        .then(json => {
            if(!json.success) throw new Error(json.error||'error');
            const allRows = safeArray(json.rows);
            // ซ่อนเฉพาะ is_old_session (session เก่าหลังจ่ายเงิน)
            // is_combined (รวมโต๊ะ) ยังแสดงปกติ ครัวต้องเห็นทุกรายการ
            const hasNewSession = allRows.some(r => !r.is_old_session && !r.is_voided);
            const rows       = allRows.filter(r => hasNewSession ? !r.is_old_session : true);
            const pids       = Array.isArray(json.allowed_printer_ids) ? json.allowed_printer_ids : [];
            const printerSet = pids.length > 0 ? new Set(pids.map(Number)) : null;
            const nDone   = rows.filter(r => { const s=parseInt(r.ProcessStatus,10); return s===PS_DONE||s===PS_RESOLVED||nonKds(r,printerSet); }).length;
            const nActive = rows.filter(r => { const s=parseInt(r.ProcessStatus,10); return !nonKds(r,printerSet)&&s!==PS_DONE&&s!==PS_RESOLVED&&s!==PS_VOIDED; }).length;
            const nVoid   = rows.filter(r => !nonKds(r,printerSet)&&parseInt(r.ProcessStatus,10)===PS_VOIDED).length;
            document.getElementById('modalSub').textContent = `✅ เสร็จ ${nDone}  ·  🍳 กำลังทำ ${nActive}  ·  🚫 ยกเลิก ${nVoid}`;
            document.getElementById('modalBody').innerHTML  = rows.length
                ? rows.map(r => buildRow(r, printerSet)).join('')
                : '<div class="modal-msg">ไม่มีออเดอร์วันนี้</div>';
        })
        .catch(err => {
            if(err.name === 'AbortError') return;
            document.getElementById('modalBody').innerHTML = '<div class="modal-msg">โหลดไม่สำเร็จ</div>';
            console.error(err);
        });
}
function closeModal(){
    document.getElementById('tableModal').classList.remove('open');
    document.body.style.overflow = '';
}

/* ── Zones ── */
async function loadZones(){
    try {
        const res  = await fetch('api_checker.php?action=list_zones' + cidParam + '&_=' + Date.now(), {cache:'no-store'});
        const json = await res.json();
        if(!json.success || !safeArray(json.zones).length) return;
        state.zones = safeArray(json.zones);
        const inner = document.getElementById('zoneInner');
        inner.innerHTML = `<button class="btn-zone active" data-zid="">ทั้งหมด</button>`
            + state.zones.map(z => `<button class="btn-zone" data-zid="${esc(String(z.ZoneID))}">${esc(z.ZoneName||String(z.ZoneID))}</button>`).join('');
        document.getElementById('zoneBar').classList.remove('hidden');
    } catch(e){ console.error('loadZones',e); }
}
async function setZone(zid){
    state.zoneId = zid||null;
    document.querySelectorAll('.btn-zone').forEach(b => b.classList.toggle('active', b.dataset.zid===(zid||'')));
    if(zid){
        try {
            const res  = await fetch('api_checker.php?action=list_tables_in_zone&zone_id='+encodeURIComponent(zid)+cidParam+'&_='+Date.now(),{cache:'no-store'});
            const json = await res.json();
            state.zoneTables = json.success ? new Map(safeArray(json.tables).map(t=>[String(t.TableID), t.TableName||String(t.TableID)])) : null;
        } catch(e){ state.zoneTables=null; }
    } else {
        state.zoneTables = null;
    }
    renderGrid();
}

/* ── Data ── */
function setDot(s){ document.getElementById('statusDot').className='status-dot'+(s?' '+s:''); }
let _loadController  = null;
let _modalController = null;
let _pollErrorCount  = 0;   // นับ error ต่อเนื่องเพื่อทำ backoff
async function loadAll(){
    if(_loadController) _loadController.abort();
    _loadController = new AbortController();
    const sig = _loadController.signal;
    setDot('loading');
    try {
        if(IS_SERVE){
            const sv = await fetch('api_checker.php?action=list_serve_view&_='+Date.now(),{cache:'no-store',signal:sig}).then(r=>r.json());
            if(sig.aborted) return;
            if(!sv.success) throw new Error(sv.error);
            state.serveTables = safeArray(sv.rows);
            setDot('');
            const nReady = state.serveTables.filter(t=>t.cooking===0).length;
            document.title = nReady > 0 ? `(${nReady}) Serve Display` : 'Serve Display';
        } else {
            const [ar,fr] = await Promise.all([
                fetch('api_checker.php?action=list_active'  +cidParam+'&_='+Date.now(),{cache:'no-store',signal:sig}).then(r=>r.json()),
                fetch('api_checker.php?action=list_finished'+cidParam+'&_='+Date.now(),{cache:'no-store',signal:sig}).then(r=>r.json()),
            ]);
            if(sig.aborted) return;
            if(!ar.success) throw new Error(ar.error);
            if(!fr.success) throw new Error(fr.error);
            state.active   = safeArray(ar.active_rows);
            state.finished = safeArray(fr.recent_finished_rows);
            const pids = Array.isArray(ar.allowed_printer_ids) ? ar.allowed_printer_ids : [];
            state.allowedPrinters = pids.length > 0 ? new Set(pids.map(Number)) : null;
            setDot('');
            const pending = state.active.filter(r=>!r.is_voided&&!r.is_moved&&!isHidden(r)&&!isNonKds(r)).length;
            document.title = pending > 0 ? `(${pending}) Staff Display` : 'Staff Display';
        }
        _pollErrorCount = 0;
        renderGrid();
    } catch(e){
        if(e.name === 'AbortError') return;
        _pollErrorCount++;
        setDot('error'); console.error(e);
        // backoff: 1×→2×→4×→8×... สูงสุด 60 วิ ป้องกัน hammer server ที่กำลัง recover
        const backoff = Math.min(REFRESH_MS * Math.pow(2, _pollErrorCount - 1), 60000);
        if(_pollErrorCount > 1){
            clearInterval(window._pollTimer);
            window._pollTimer = setTimeout(() => {
                window._pollTimer = setInterval(() => { if(!document.hidden) loadAll(); }, REFRESH_MS);
                loadAll();
            }, backoff);
        }
    }
}

/* ── Events ── */
document.getElementById('tableGrid').addEventListener('click', e => {
    const c = e.target.closest('.table-card:not(.s-empty)');
    if(c) openModal(c.dataset.key, c.dataset.name);
});
/* serve mode: re-render grid click (cards built dynamically) */
document.getElementById('modalClose').addEventListener('click', closeModal);
document.getElementById('tableModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });
document.getElementById('refreshBtn').addEventListener('click', () => { if(window._isAuthed) loadAll(); });
document.getElementById('zoneInner').addEventListener('click', e => {
    const b = e.target.closest('.btn-zone');
    if(b) setZone(b.dataset.zid||'');
});
document.addEventListener('visibilitychange', () => { if(!document.hidden && window._isAuthed) loadAll(); });

/* ── Fullscreen ── */
(function(){
    const btn = document.getElementById('fsBtn');
    function upd(){
        const f = !!document.fullscreenElement;
        btn.querySelector('.fs-enter').style.display = f?'none':'';
        btn.querySelector('.fs-exit').style.display  = f?'':'none';
    }
    btn.addEventListener('click', () => {
        document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen();
    });
    document.addEventListener('fullscreenchange', upd);
    upd();
})();

/* ── Auth ── */
(function(){
    const LS_KEY = 'staff_display';
    window._pollTimer   = null;
    window._isAuthed    = false;

    function startPolling(){
        stopPolling();
        _pollErrorCount = 0;
        loadAll();
        loadZones();
        window._pollTimer = setInterval(() => { if(!document.hidden) loadAll(); }, REFRESH_MS);
    }
    function stopPolling(){
        if(window._pollTimer){ clearInterval(window._pollTimer); window._pollTimer = null; }
    }
    function setStaff(id, name){
        window._isAuthed = true;
        document.getElementById('loginOverlay').classList.add('hidden');
        document.getElementById('logoutBtn').style.display = '';
        document.getElementById('logoutBtn').dataset.isGuest = id > 0 ? '0' : '1';
        document.getElementById('staffNameChip').textContent = id > 0 ? name : 'เข้าสู่ระบบ';
        startPolling();
    }
    function showLogin(){
        window._isAuthed = false;
        stopPolling();
        document.getElementById('loginOverlay').classList.remove('hidden');
        document.getElementById('logoutBtn').style.display = 'none';
        document.getElementById('loginError').textContent = '';
        document.getElementById('loginCode').value = '';
        setTimeout(() => document.getElementById('loginCode').focus(), 50);
    }
    function initAuth(){
        const requireLogin = (function(){ try{ return JSON.parse(localStorage.getItem('kds_require_login')??'true'); }catch(e){ return true; } })();
        if(!requireLogin){ setStaff(0, 'Guest'); return; }
        try {
            const saved = JSON.parse(localStorage.getItem(LS_KEY) || 'null');
            if(saved && saved.staff_id > 0){ setStaff(saved.staff_id, saved.staff_name); return; }
        } catch(e){}
        showLogin();
    }
    async function doLogin(){
        const code = document.getElementById('loginCode').value.trim();
        if(!code) return;
        const btn = document.getElementById('loginBtn');
        const err = document.getElementById('loginError');
        btn.disabled = true;
        err.textContent = '';
        try {
            const fd = new FormData();
            fd.append('staff_code', code);
            const res  = await fetch('api_checker.php?action=lookup_staff', {method:'POST', body:fd});
            const json = await res.json();
            if(json.success){
                localStorage.setItem(LS_KEY, JSON.stringify({staff_id: json.staff_id, staff_name: json.staff_name}));
                setStaff(json.staff_id, json.staff_name);
            } else {
                err.textContent = json.error || 'รหัสพนักงานไม่ถูกต้อง';
            }
        } catch(e){
            err.textContent = 'เชื่อมต่อไม่ได้ กรุณาลองใหม่';
        } finally {
            btn.disabled = false;
        }
    }

    document.getElementById('loginBtn').addEventListener('click', doLogin);
    document.getElementById('loginCode').addEventListener('keydown', e => { if(e.key==='Enter') doLogin(); });
    document.getElementById('logoutBtn').addEventListener('click', () => {
        if(document.getElementById('logoutBtn').dataset.isGuest === '1'){
            showLogin();
        } else {
            if(document.fullscreenElement) document.exitFullscreen();
            localStorage.removeItem(LS_KEY);
            showLogin();
        }
    });

    initAuth();
})();

/* ── Settings Panel ── */
(function(){
    /* localStorage helpers */
    const LS = {
        get: (k, def) => { try{ const v=localStorage.getItem(k); return v===null?def:JSON.parse(v); }catch(e){return def;} },
        set: (k, v) => { try{ localStorage.setItem(k, JSON.stringify(v)); }catch(e){} },
    };

    /* Apply localStorage-only settings on load */
    function applyLocalSettings(){
        const hideBtn = LS.get('kds_hide_serve_btn', false);
        const el = document.querySelector('a.btn-action');
        if(el) el.style.display = hideBtn ? 'none' : '';
    }
    applyLocalSettings();

    /* serve_ready_only applied in renderServeGrid already reads LS */
    window._serveReadyOnly  = () => LS.get('kds_serve_ready_only', false);

    /* Build HTML */
    const overlay = document.createElement('div');
    overlay.className = 'sp-overlay';
    overlay.id = 'spOverlay';
    overlay.innerHTML = `
    <div class="sp-box">
        <div class="sp-head">
            <div class="sp-title">⚙️ ตั้งค่า</div>
            <button class="sp-close" id="spClose">×</button>
        </div>
        <div class="sp-body">
            <div class="sp-section">
                <div class="sp-section-title">⏱️ เวลา & รีเฟรช</div>
                <div class="sp-row">
                    <div><div class="sp-label">เตือนสีเหลือง</div><div class="sp-sublabel">นาที</div></div>
                    <input class="sp-input" id="spYellow" type="number" min="1" max="999">
                </div>
                <div class="sp-row">
                    <div><div class="sp-label">เตือนสีแดง</div><div class="sp-sublabel">นาที</div></div>
                    <input class="sp-input" id="spRed" type="number" min="1" max="999">
                </div>
                <div class="sp-row">
                    <div class="sp-label">รีเฟรชทุก</div>
                    <div class="sp-pills">
                        <button class="sp-pill" data-ms="15000">15s</button>
                        <button class="sp-pill" data-ms="30000">30s</button>
                        <button class="sp-pill" data-ms="60000">60s</button>
                    </div>
                </div>
            </div>
            <div class="sp-section">
                <div class="sp-section-title">🔔 การแจ้งเตือน</div>
                <div class="sp-row">
                    <div class="sp-label">เสียงแจ้งเตือน</div>
                    <label class="sp-toggle"><input type="checkbox" id="spSound"><span class="sp-slider"></span></label>
                </div>
            </div>
            <div class="sp-section">
                <div class="sp-section-title">🖥️ จอแสดงผล</div>
                <div class="sp-row">
                    <div class="sp-label">ชื่อจอ</div>
                    <input class="sp-input" id="spMachineName" type="text" style="width:140px;text-align:left">
                </div>
                <div class="sp-row">
                    <div><div class="sp-label">DB Host / IP</div><div class="sp-sublabel">ที่อยู่ฐานข้อมูล</div></div>
                    <input class="sp-input" id="spDbHost" type="text" style="width:140px;text-align:left">
                </div>
                <div class="sp-row">
                    <div><div class="sp-label">Database Name</div><div class="sp-sublabel">ชื่อฐานข้อมูล</div></div>
                    <input class="sp-input" id="spDbName" type="text" style="width:140px;text-align:left">
                </div>
                <div class="sp-row">
                    <div><div class="sp-label">Computer ID</div><div class="sp-sublabel">หมายเลขเครื่อง / Zone</div></div>
                    <input class="sp-input" id="spCid" type="number" min="1">
                </div>
            </div>
            <div class="sp-section">
                <div class="sp-section-title">🔐 การเข้าใช้งาน</div>
                <div class="sp-row">
                    <div><div class="sp-label">บังคับใส่รหัสพนักงาน</div><div class="sp-sublabel">ปิด = เข้าใช้งานได้เลยไม่ต้อง login</div></div>
                    <label class="sp-toggle"><input type="checkbox" id="spRequireLogin"><span class="sp-slider"></span></label>
                </div>
            </div>
            <div class="sp-section">
                <div class="sp-section-title">🍽️ Serve Mode</div>
                <div class="sp-row">
                    <div><div class="sp-label">แสดงเฉพาะโต๊ะพร้อมเสิร์ฟ</div><div class="sp-sublabel">ซ่อนโต๊ะที่ยังทำอยู่</div></div>
                    <label class="sp-toggle"><input type="checkbox" id="spReadyOnly"><span class="sp-slider"></span></label>
                </div>
                <div class="sp-row">
                    <div><div class="sp-label">ซ่อนปุ่มสลับหน้าเสิร์ฟ</div><div class="sp-sublabel">ไม่แสดงปุ่มใน topbar</div></div>
                    <label class="sp-toggle"><input type="checkbox" id="spHideServeBtn"><span class="sp-slider"></span></label>
                </div>
            </div>
            <div class="sp-msg" id="spMsg"></div>
        </div>
        <div class="sp-foot">
            <button class="sp-btn-cancel" id="spCancel">ยกเลิก</button>
            <button class="sp-btn-save" id="spSave">💾 บันทึก</button>
        </div>
    </div>`;
    document.body.appendChild(overlay);

    /* Hidden server-side snapshot */
    let _snap = {};

    /* Load settings into form */
    async function openSettings(){
        document.getElementById('spMsg').textContent = '';
        document.getElementById('spMsg').className = 'sp-msg';
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';

        try {
            const res  = await fetch('api_checker.php?action=get_system_settings&_='+Date.now(),{cache:'no-store'});
            const json = await res.json();
            if(!json.success) throw new Error(json.error||'error');
            _snap = json.settings || {};
        } catch(e){ _snap = {}; }

        /* Fill server fields */
        document.getElementById('spYellow').value      = _snap.threshold_yellow || 10;
        document.getElementById('spRed').value         = _snap.threshold_red    || 20;
        document.getElementById('spSound').checked     = !!_snap.sound_enabled;
        document.getElementById('spMachineName').value = _snap.current_computer_name || '';
        document.getElementById('spDbHost').value      = _snap.db_host || '';
        document.getElementById('spDbName').value      = _snap.db_name || '';
        document.getElementById('spCid').value         = _snap.current_computer_id || '';

        /* Fill localStorage fields */
        const ms = LS.get('kds_refresh_ms', REFRESH_MS);
        document.querySelectorAll('.sp-pill').forEach(p => {
            p.classList.toggle('active', parseInt(p.dataset.ms)===ms);
        });
        document.getElementById('spReadyOnly').checked   = LS.get('kds_serve_ready_only', false);
        document.getElementById('spHideServeBtn').checked= LS.get('kds_hide_serve_btn', false);
        document.getElementById('spRequireLogin').checked = LS.get('kds_require_login', true);
    }

    /* Pill selection */
    overlay.addEventListener('click', e => {
        const p = e.target.closest('.sp-pill');
        if(p){ document.querySelectorAll('.sp-pill').forEach(x=>x.classList.remove('active')); p.classList.add('active'); }
    });

    /* Save */
    document.getElementById('spSave').addEventListener('click', async () => {
        const msg = document.getElementById('spMsg');
        msg.textContent = 'กำลังบันทึก...'; msg.className = 'sp-msg';

        /* localStorage settings */
        const activePill = overlay.querySelector('.sp-pill.active');
        LS.set('kds_refresh_ms',       activePill ? parseInt(activePill.dataset.ms) : REFRESH_MS);
        LS.set('kds_serve_ready_only', document.getElementById('spReadyOnly').checked);
        LS.set('kds_hide_serve_btn',   document.getElementById('spHideServeBtn').checked);
        LS.set('kds_require_login',    document.getElementById('spRequireLogin').checked);

        /* Server settings via existing API */
        const body = new URLSearchParams({
            db_host:              document.getElementById('spDbHost').value.trim(),
            db_port:              _snap.db_port || 3306,
            db_name:              document.getElementById('spDbName').value.trim(),
            current_computer_id:  document.getElementById('spCid').value,
            current_computer_name:document.getElementById('spMachineName').value.trim(),
            finish_staff_id:      _snap.finish_staff_id || 0,
            threshold_yellow:     document.getElementById('spYellow').value,
            threshold_red:        document.getElementById('spRed').value,
            sound_enabled:        document.getElementById('spSound').checked ? 1 : 0,
            barcode_camera_enabled: _snap.barcode_camera_enabled ?? 1,
            kds_two_step_checkout:  _snap.kds_two_step_checkout  ?? 0,
        });

        try {
            const res  = await fetch('api_checker.php?action=save_system_settings',{method:'POST',body});
            const json = await res.json();
            if(!json.success) throw new Error(json.error||'บันทึกไม่สำเร็จ');
            msg.textContent = '✅ บันทึกเรียบร้อย — รีโหลดหน้าเพื่อใช้งาน'; msg.className='sp-msg ok';
            applyLocalSettings();
        } catch(e){
            msg.textContent = '❌ ' + e.message; msg.className='sp-msg err';
        }
    });

    /* Close */
    function closeSettings(){ overlay.classList.remove('open'); document.body.style.overflow=''; }
    document.getElementById('spClose').addEventListener('click', closeSettings);
    document.getElementById('spCancel').addEventListener('click', closeSettings);
    overlay.addEventListener('click', e => { if(e.target===overlay) closeSettings(); });
    document.addEventListener('keydown', e => { if(e.key==='Escape' && overlay.classList.contains('open')) closeSettings(); });

    /* Triple-tap logo */
    let _taps = [], _tapTimer = null;
    const brand = document.querySelector('.brand');
    if(brand){
        brand.addEventListener('click', () => {
            _taps.push(Date.now());
            _taps = _taps.filter(t => Date.now()-t < 1500);
            clearTimeout(_tapTimer);
            if(_taps.length >= 3){ _taps=[]; openSettings(); }
            else { _tapTimer = setTimeout(()=>{ _taps=[]; }, 1500); }
        });
    }
})();

/* ── PWA Install banner ── */
(function(){
    const DISMISS_KEY = 'kds_install_dismissed';
    const banner   = document.getElementById('installBanner');
    const btnInst  = document.getElementById('installBtn');
    const btnDism  = document.getElementById('installDismiss');
    const title    = document.getElementById('installBannerTitle');
    const sub      = document.getElementById('installBannerSub');

    if(!banner) return;

    // ถ้าติดตั้งเป็นแอปแล้ว หรือกด dismiss ไปแล้ว → ไม่แสดง
    const alreadyInstalled = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
    const dismissed = lsGet(DISMISS_KEY, false);
    if(alreadyInstalled || dismissed) return;

    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent)
        && !/crios|fxios/i.test(navigator.userAgent);

    let deferredPrompt = null;

    function showBanner(){
        setTimeout(() => banner.classList.add('show'), 800);
    }

    function hideBanner(){
        banner.classList.remove('show');
    }

    btnDism.addEventListener('click', () => {
        hideBanner();
        lsSet(DISMISS_KEY, true);
    });

    if(isIOS){
        // iOS Safari — แสดง instruction ขั้นตอน
        title.textContent = 'เพิ่มทางลัดบน iPhone/iPad';
        sub.innerHTML = '<ol><li>แตะปุ่ม <strong>Share</strong> (📤) ในแถบเบราว์เซอร์</li><li>เลือก <strong>"Add to Home Screen"</strong></li><li>กด <strong>Add</strong></li></ol>';
        btnInst.textContent = 'ดูวิธีทำ ▾';
        btnInst.addEventListener('click', () => {
            // toggle แสดง/ซ่อน instruction (instruction แสดงใน sub อยู่แล้ว)
            hideBanner();
            lsSet(DISMISS_KEY, true);
        });
        showBanner();
    } else {
        // Android/Chrome — รอ beforeinstallprompt
        window.addEventListener('beforeinstallprompt', e => {
            e.preventDefault();
            deferredPrompt = e;
            showBanner();
        });

        btnInst.addEventListener('click', async () => {
            if(!deferredPrompt) return;
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            deferredPrompt = null;
            hideBanner();
            if(outcome === 'accepted') lsSet(DISMISS_KEY, true);
        });

        // ถ้าติดตั้งสำเร็จผ่านช่องทางอื่น
        window.addEventListener('appinstalled', () => {
            hideBanner();
            lsSet(DISMISS_KEY, true);
        });
    }

    // register service worker
    if('serviceWorker' in navigator){
        navigator.serviceWorker.register('./sw.js').catch(() => {});
    }
})();

</script>
</body>
</html>
