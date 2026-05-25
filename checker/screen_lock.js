(function(){
var cfg = window._kdsLockCfg || {};
var MAX = cfg.maxScreens || 3;
var HB  = cfg.hb || 8000;
var KEY = 'kds_tabs';
var pingInterval = null;

// instanceId unique ต่อ tab นี้ — ใช้ sessionStorage
var myId = sessionStorage.getItem('kds_iid');
if (!myId) {
    myId = Math.random().toString(36).slice(2,10) + Math.random().toString(36).slice(2,10);
    sessionStorage.setItem('kds_iid', myId);
}

function getTabs() {
    try {
        var d = JSON.parse(localStorage.getItem(KEY) || '{}');
        var now = Date.now();
        var clean = {};
        Object.keys(d).forEach(function(k){
            if (now - d[k] < 30000) clean[k] = d[k];
        });
        return clean;
    } catch(e) { return {}; }
}

function saveTabs(d) {
    try { localStorage.setItem(KEY, JSON.stringify(d)); } catch(e){}
}

function countOthers() {
    var tabs = getTabs();
    return Object.keys(tabs).filter(function(k){ return k !== myId; }).length;
}

function register() {
    var tabs = getTabs();
    if (!tabs[myId]) {
        var others = Object.keys(tabs).length;
        if (others >= MAX) return false;
    }
    tabs[myId] = Date.now();
    saveTabs(tabs);
    return true;
}

function ping() {
    var tabs = getTabs();
    if (!tabs[myId] && countOthers() >= MAX) {
        showBlock(); return;
    }
    tabs[myId] = Date.now();
    saveTabs(tabs);
}

function unregister() {
    var tabs = getTabs();
    delete tabs[myId];
    saveTabs(tabs);
}

function init() {
    if (!register()) { showBlock(); return; }
    pingInterval = setInterval(ping, HB);
    window.addEventListener('beforeunload', unregister);
    window.addEventListener('storage', function(e){
        if (e.key !== KEY) return;
        var tabs = getTabs();
        if (!tabs[myId] && countOthers() >= MAX) showBlock();
    });
}

// รันทันที ไม่รอ DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

function showBlock() {
    if (document.getElementById('kds-lo')) return;
    var tabs = getTabs();
    var count = Object.keys(tabs).length;
    var ov = document.createElement('div');
    ov.id = 'kds-lo';
    ov.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;background:rgba(8,40,90,0.94);display:flex;align-items:center;justify-content:center;font-family:Tahoma,Arial,sans-serif;';
    var bx = document.createElement('div');
    bx.style.cssText = 'background:#fff;border-radius:20px;padding:36px 30px;max-width:420px;width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,0.4);';
    bx.innerHTML =
        '<div style="font-size:48px;margin-bottom:12px">\uD83D\uDD12</div>' +
        '<h2 style="margin:0 0 10px;color:#0f3060;font-size:20px">เกินจำนวนหน้าจอที่อนุญาต</h2>' +
        '<p style="margin:0 0 8px;color:#6b7a90;font-size:14px">ระบบอนุญาตสูงสุด <b>' + MAX + '</b> หน้าจอพร้อมกัน</p>' +
        '<p style="margin:0 0 24px;color:#6b7a90;font-size:14px">ขณะนี้มี <b>' + count + '</b> หน้าจอที่ใช้งานอยู่</p>' +
        '<button id="kds-kb" style="background:linear-gradient(135deg,#1683ff,#0f69cf);color:#fff;border:none;border-radius:12px;padding:13px 0;font-size:15px;font-weight:bold;cursor:pointer;width:100%;margin-bottom:10px">' +
        '\uD83D\uDD13 บังคับเข้าใช้งาน (kick หน้าจอเก่าสุด)</button>' +
        '<div style="font-size:12px;color:#bbb">หน้าจอที่เปิดนานที่สุดจะถูกล็อคออกอัตโนมัติ</div>';
    ov.appendChild(bx);

    if (document.body) {
        document.body.appendChild(ov);
    } else {
        document.addEventListener('DOMContentLoaded', function(){ document.body.appendChild(ov); });
    }

    setTimeout(function(){
        var btn = document.getElementById('kds-kb');
        if (!btn) return;
        btn.onclick = function(){
            var tabs = getTabs();
            // kick oldest tab
            var oldest = null, oldestTime = Infinity;
            Object.keys(tabs).forEach(function(k){
                if (k !== myId && tabs[k] < oldestTime) {
                    oldest = k; oldestTime = tabs[k];
                }
            });
            if (oldest) delete tabs[oldest];
            tabs[myId] = Date.now();
            saveTabs(tabs);
            var el = document.getElementById('kds-lo');
            if (el) el.remove();
            if (pingInterval) clearInterval(pingInterval);
            pingInterval = setInterval(ping, HB);
        };
    }, 100);
}
})();
