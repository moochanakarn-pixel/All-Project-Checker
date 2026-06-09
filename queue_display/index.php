<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?php echo h(APP_TITLE); ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: #111;
            overflow: hidden;
        }

        #app {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100%;
            background: #fff;
        }

        /* ── Section (READY / PREPARING) ── */
        .qs {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        .qs-head {
            background: #1a1a2e;
            color: #fff;
            text-align: center;
            padding: clamp(10px, 2vh, 22px) 16px clamp(8px, 1.6vh, 18px);
            flex-shrink: 0;
        }
        .qs-head-title {
            font-size: clamp(22px, 5.5vw, 56px);
            font-weight: 800;
            letter-spacing: 4px;
        }
        .qs-head-sub {
            font-size: clamp(14px, 3vw, 32px);
            font-weight: 400;
            opacity: .85;
            letter-spacing: 2px;
        }

        .qs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow-y: auto;
            flex: 1;
            padding: clamp(6px, 1.2vh, 16px) clamp(8px, 2vw, 24px) clamp(8px, 1.6vh, 20px);
            align-content: start;
            gap: clamp(2px, 0.6vh, 8px) 0;
        }
        .qs-grid::-webkit-scrollbar { width: 4px; }
        .qs-grid::-webkit-scrollbar-thumb { background: #ddd; border-radius: 2px; }

        .q-num {
            font-size: clamp(40px, 10.5vw, 110px);
            font-weight: 700;
            color: #1a1a2e;
            text-align: center;
            padding: clamp(4px, 1vh, 12px) 4px;
            line-height: 1.15;
        }
        .q-empty {
            grid-column: 1 / -1;
            text-align: center;
            color: #bbb;
            font-size: clamp(16px, 3.5vw, 36px);
            padding: clamp(20px, 5vh, 60px);
        }

        /* ── Latest Ready Announce ── */
        .latest-wrap {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: clamp(8px, 2vh, 28px) 0 clamp(10px, 2.4vh, 32px);
            border-top: 2px solid #e0e0e0;
            border-bottom: 2px solid #e0e0e0;
            background: #f5f5f5;
            min-height: clamp(100px, 22vh, 260px);
        }
        .latest-label {
            font-size: clamp(13px, 3vw, 32px);
            font-weight: 700;
            letter-spacing: 3px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: clamp(2px, 0.6vh, 8px);
        }
        #latestNum {
            font-size: clamp(110px, 30vw, 300px);
            font-weight: 900;
            color: #1a1a2e;
            line-height: 1;
            letter-spacing: 4px;
            display: block;
            transition: color .2s;
        }
        #latestNum.flash {
            animation: flash-ready .55s ease-in-out 4;
        }
        @keyframes flash-ready {
            0%,100% { color: #1a1a2e; transform: scale(1);    }
            50%     { color: #2563eb; transform: scale(1.08);  }
        }

        /* ── Setup Error Overlay ── */
        #setupError {
            display: none;
            position: fixed;
            inset: 0;
            background: #1a1a2e;
            color: #fff;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 24px;
            z-index: 999;
            text-align: center;
        }
        .se-icon  { font-size: 56px; margin-bottom: 16px; }
        .se-title { font-size: clamp(20px, 4vw, 36px); font-weight: 800; color: #f59e0b; margin-bottom: 12px; }
        .se-error { font-size: clamp(16px, 3vw, 26px); font-weight: 600; margin-bottom: 8px; }
        .se-detail{ font-size: clamp(13px, 2.2vw, 20px); opacity: .75; margin-bottom: 24px; }
        .se-steps {
            list-style: none;
            text-align: left;
            background: rgba(255,255,255,.08);
            border-radius: 10px;
            padding: 20px 28px;
            max-width: 640px;
            width: 100%;
        }
        .se-steps li {
            font-size: clamp(13px, 2.2vw, 20px);
            padding: 7px 0;
            border-bottom: 1px solid rgba(255,255,255,.1);
            counter-increment: step;
        }
        .se-steps li:last-child { border-bottom: none; }
        .se-steps li::before {
            content: counter(step) ". ";
            font-weight: 700;
            color: #f59e0b;
        }
        .se-steps { counter-reset: step; }
        .se-retry {
            margin-top: 24px;
            font-size: clamp(12px, 2vw, 18px);
            opacity: .55;
        }
    </style>
</head>
<body>
<div id="setupError"></div>
<div id="app">

    <section class="qs">
        <div class="qs-head">
            <div class="qs-head-title">READY</div>
            <div class="qs-head-sub">พร้อมเสิร์ฟ</div>
        </div>
        <div class="qs-grid" id="readyGrid">
            <div class="q-empty">กำลังโหลด...</div>
        </div>
    </section>

    <div class="latest-wrap">
        <div class="latest-label">ล่าสุด</div>
        <span id="latestNum">-</span>
    </div>

    <section class="qs">
        <div class="qs-head">
            <div class="qs-head-title">PREPARING</div>
            <div class="qs-head-sub">กำลังเตรียม</div>
        </div>
        <div class="qs-grid" id="preparingGrid">
            <div class="q-empty">กำลังโหลด...</div>
        </div>
    </section>

</div>
<script>
(function () {
    var REFRESH_MS = <?php echo (int)QUEUE_REFRESH_MS; ?>;
    var prevLatest = null;

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderGrid(el, items) {
        if (!items || !items.length) {
            el.innerHTML = '<div class="q-empty">ไม่มีรายการ</div>';
            return;
        }
        el.innerHTML = items.map(function (q) {
            return '<div class="q-num">' + esc(q) + '</div>';
        }).join('');
    }

    function showSetupError(d) {
        var steps = (d.steps || []).map(function (s) {
            return '<li>' + esc(s) + '</li>';
        }).join('');
        var el = document.getElementById('setupError');
        el.innerHTML =
            '<div class="se-icon">&#9888;</div>' +
            '<div class="se-title">ต้องตั้งค่าระบบก่อนใช้งาน</div>' +
            '<div class="se-error">' + esc(d.error || '') + '</div>' +
            (d.detail ? '<div class="se-detail">' + esc(d.detail) + '</div>' : '') +
            (steps ? '<ol class="se-steps">' + steps + '</ol>' : '') +
            '<div class="se-retry">ระบบจะตรวจสอบซ้ำอัตโนมัติทุก ' + Math.round(REFRESH_MS / 1000) + ' วินาที</div>';
        el.style.display = 'flex';
        document.getElementById('app').style.display = 'none';
    }

    function hideSetupError() {
        document.getElementById('setupError').style.display = 'none';
        document.getElementById('app').style.display = 'flex';
    }

    function fetchQueue() {
        fetch('api_queue.php?_=' + Date.now())
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) {
                    if (d.setup_required) showSetupError(d);
                    return;
                }
                hideSetupError();

                renderGrid(document.getElementById('readyGrid'),     d.ready     || []);
                renderGrid(document.getElementById('preparingGrid'), d.preparing || []);

                var latestEl = document.getElementById('latestNum');
                var latest   = d.latest_ready || '-';
                latestEl.textContent = latest;

                if (latest !== '-' && prevLatest !== null && latest !== prevLatest) {
                    latestEl.classList.remove('flash');
                    void latestEl.offsetWidth;
                    latestEl.classList.add('flash');
                    setTimeout(function () { latestEl.classList.remove('flash'); }, 2400);
                }
                prevLatest = latest;
            })
            .catch(function (e) { console.warn('queue fetch error', e); });
    }

    fetchQueue();
    setInterval(fetchQueue, REFRESH_MS);
}());
</script>
</body>
</html>
