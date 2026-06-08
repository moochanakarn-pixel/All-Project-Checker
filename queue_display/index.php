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
    </style>
</head>
<body>
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

    function fetchQueue() {
        fetch('api_queue.php?action=status&_=' + Date.now())
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) return;

                renderGrid(document.getElementById('readyGrid'),     d.ready     || []);
                renderGrid(document.getElementById('preparingGrid'), d.preparing || []);

                var latestEl = document.getElementById('latestNum');
                var latest   = d.latest_ready || '-';
                latestEl.textContent = latest;

                if (latest !== '-' && prevLatest !== null && latest !== prevLatest) {
                    latestEl.classList.remove('flash');
                    void latestEl.offsetWidth; // reflow
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
