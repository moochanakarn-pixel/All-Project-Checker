<?php
session_name('qdisplay_cfg');
session_start();
ob_start();
require_once __DIR__ . '/config.php';

// ── helpers ──────────────────────────────────────────────────────────────────
function sv($local, $key, $default) {
    return array_key_exists($key, $local) ? $local[$key] : $default;
}
function safeColor($v, $default) {
    $v = trim((string)$v);
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) return $v;
    if (preg_match('/^[a-zA-Z]{1,20}$/', $v)) return $v;
    return $default;
}
function settingsFilePath() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'settings.local.php';
}

// ── load current settings ────────────────────────────────────────────────────
$local = getLocalSettings();
$configuredPin = (string)sv($local, 'settings_pin', '1234');

$msg   = '';
$isErr = false;

// ── handle POST ───────────────────────────────────────────────────────────────
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

// List computers where ComputerType=4 (AJAX — returns JSON)
if ($action === 'list_computers') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $h = trim((string)($_POST['db_host'] ?? ''));
        $p = max(1, (int)($_POST['db_port'] ?? 3307));
        $n = trim((string)($_POST['db_name'] ?? ''));
        $u = trim((string)($_POST['db_user'] ?? ''));
        $w = (string)($_POST['db_pass'] ?? '');
        if ($h === '' || $n === '' || $u === '') throw new Exception('กรุณากรอก Host / DB Name / User ก่อน');
        $conn = new mysqli($h, $u, $w, $n, $p);
        if ($conn->connect_error) throw new Exception($conn->connect_error);
        $conn->set_charset('utf8');
        $res = $conn->query(
            "SELECT ComputerID, ComputerName FROM computername WHERE ComputerType = 4 ORDER BY ComputerName"
        );
        $list = array();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $list[] = array('id' => (int)$row['ComputerID'], 'name' => (string)$row['ComputerName']);
            }
        }
        $conn->close();
        echo json_encode(array('success' => true, 'computers' => $list));
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => $e->getMessage()));
    }
    exit;
}

// Test DB connection (AJAX — returns JSON)
if ($action === 'test_db') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $h = trim((string)($_POST['db_host'] ?? ''));
        $p = max(1, (int)($_POST['db_port'] ?? 3307));
        $n = trim((string)($_POST['db_name'] ?? ''));
        $u = trim((string)($_POST['db_user'] ?? ''));
        $w = (string)($_POST['db_pass'] ?? '');
        if ($h === '' || $n === '' || $u === '') throw new Exception('กรุณากรอก Host / DB Name / User');
        $conn = new mysqli($h, $u, $w, $n, $p);
        if ($conn->connect_error) throw new Exception($conn->connect_error);
        $conn->close();
        echo json_encode(['success' => true,  'message' => 'เชื่อมต่อสำเร็จ']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Verify PIN
if ($action === 'pin') {
    $pin = (string)($_POST['pin'] ?? '');
    if ($pin === $configuredPin) {
        $_SESSION['qdisplay_auth'] = true;
    } else {
        $msg = 'PIN ไม่ถูกต้อง';
        $isErr = true;
    }
}

// Logout
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: settings.php');
    exit;
}

// Save settings
if ($action === 'save' && !empty($_SESSION['qdisplay_auth'])) {
    $pin = isset($_POST['pin']) ? (string)$_POST['pin'] : $configuredPin;
    if ($pin !== $configuredPin && $pin !== '') {
        $msg = 'PIN ไม่ถูกต้อง';
        $isErr = true;
    } else {
        $newPin = trim((string)($_POST['settings_pin'] ?? $configuredPin));
        if ($newPin === '') $newPin = $configuredPin;
        $new = [
            'db_host'               => trim((string)($_POST['db_host']               ?? '')),
            'db_port'               => max(1, (int)($_POST['db_port']                ?? 3307)),
            'db_name'               => trim((string)($_POST['db_name']               ?? '')),
            'db_user'               => trim((string)($_POST['db_user']               ?? '')),
            'db_pass'               => (string)($_POST['db_pass']                    ?? ''),
            'settings_pin'          => $newPin,
            'computer_id'           => max(0, (int)($_POST['computer_id']            ?? 0)),
            'queue_refresh_ms'      => max(1000, (int)($_POST['queue_refresh_ms']    ?? 5000)),
            'ready_limit'           => max(1, (int)($_POST['ready_limit']            ?? 30)),
            'preparing_limit'       => max(1, (int)($_POST['preparing_limit']        ?? 30)),
            'ready_display_minutes' => max(0, (int)($_POST['ready_display_minutes']  ?? 40)),
            'grid_columns'          => max(1, min(8, (int)($_POST['grid_columns']    ?? 2))),
            'shop_name'             => trim((string)($_POST['shop_name']              ?? '')),
            'bg_image'              => trim((string)($_POST['bg_image']              ?? '')),
            'color_header_bg'       => safeColor($_POST['color_header_bg']   ?? '#1a1a2e', '#1a1a2e'),
            'color_header_text'     => safeColor($_POST['color_header_text'] ?? '#ffffff', '#ffffff'),
            'color_queue_text'      => safeColor($_POST['color_queue_text']  ?? '#1a1a2e', '#1a1a2e'),
            'color_app_bg'          => safeColor($_POST['color_app_bg']      ?? '#ffffff', '#ffffff'),
        ];
        $content = "<?php return " . var_export($new, true) . ";\n";
        if (file_put_contents(settingsFilePath(), $content) !== false) {
            // reload
            $local = $new;
            $configuredPin = $newPin;
            $msg = 'บันทึกการตั้งค่าเรียบร้อยแล้ว';
        } else {
            $msg = 'บันทึกไม่ได้ — ตรวจสอบสิทธิ์ write ของโฟลเดอร์';
            $isErr = true;
        }
    }
}

$auth = !empty($_SESSION['qdisplay_auth']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Queue Display — ตั้งค่า</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI','Helvetica Neue',Arial,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;padding:0}

/* ── Top bar ── */
.topbar{display:flex;align-items:center;justify-content:space-between;background:#1e293b;padding:14px 20px;border-bottom:1px solid #334155;position:sticky;top:0;z-index:10}
.topbar-title{font-size:18px;font-weight:700;color:#f1f5f9}
.btn-back{display:inline-flex;align-items:center;gap:6px;background:transparent;border:1px solid #475569;color:#94a3b8;padding:7px 14px;border-radius:8px;font-size:14px;cursor:pointer;text-decoration:none;transition:.15s}
.btn-back:hover{background:#1e293b;color:#f1f5f9;border-color:#94a3b8}
.btn-logout{background:transparent;border:1px solid #ef4444;color:#ef4444;padding:7px 14px;border-radius:8px;font-size:14px;cursor:pointer;transition:.15s}
.btn-logout:hover{background:#ef4444;color:#fff}

/* ── Content ── */
.content{max-width:620px;margin:0 auto;padding:24px 16px 60px}

/* ── PIN screen ── */
.pin-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:80vh;gap:24px}
.pin-title{font-size:22px;font-weight:700;color:#f1f5f9}
.pin-sub{font-size:14px;color:#64748b}
.pin-dots{display:flex;gap:14px;margin:8px 0}
.pin-dot{width:18px;height:18px;border-radius:50%;background:#334155;transition:.15s}
.pin-dot.filled{background:#3b82f6}
.pin-input{background:#1e293b;border:2px solid #334155;color:#f1f5f9;font-size:36px;font-weight:700;text-align:center;width:220px;padding:12px;border-radius:14px;letter-spacing:8px;outline:none}
.pin-input:focus{border-color:#3b82f6}
.btn-pin{background:#3b82f6;color:#fff;border:none;padding:14px 40px;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;transition:.15s;width:220px}
.btn-pin:hover{background:#2563eb}

/* ── Sections ── */
.section{background:#1e293b;border-radius:14px;padding:20px;margin-bottom:20px}
.section-title{font-size:13px;font-weight:700;letter-spacing:2px;color:#64748b;text-transform:uppercase;margin-bottom:16px}

/* ── Fields ── */
.field{margin-bottom:14px}
.field label{display:block;font-size:13px;font-weight:600;color:#94a3b8;margin-bottom:5px}
.field input[type=text],
.field input[type=password],
.field input[type=number]{width:100%;background:#0f172a;border:1px solid #334155;color:#f1f5f9;padding:10px 12px;border-radius:8px;font-size:15px;outline:none;transition:.15s}
.field input:focus{border-color:#3b82f6}
.field input[type=color]{width:52px;height:36px;border:1px solid #334155;border-radius:8px;cursor:pointer;background:#0f172a;padding:2px}
.color-row{display:flex;align-items:center;gap:10px}
.color-row input[type=text]{flex:1}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/* ── Alert ── */
.alert{padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:18px;font-weight:600}
.alert-ok {background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#4ade80}
.alert-err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171}

/* ── Action buttons ── */
.actions{display:flex;gap:10px;margin-top:8px}
.btn-save{flex:1;background:#3b82f6;color:#fff;border:none;padding:14px;border-radius:10px;font-size:16px;font-weight:700;cursor:pointer;transition:.15s}
.btn-save:hover{background:#2563eb}
.btn-test{background:#0f172a;color:#94a3b8;border:1px solid #334155;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;transition:.15s}
.btn-test:hover{border-color:#94a3b8;color:#f1f5f9}
.test-result{margin-top:8px;font-size:13px;font-weight:600}
.test-result.ok {color:#4ade80}
.test-result.err{color:#f87171}
</style>
</head>
<body>

<div class="topbar">
    <a href="index.php" class="btn-back">&#8592; กลับหน้าแสดงผล</a>
    <span class="topbar-title">ตั้งค่า Queue Display</span>
    <?php if ($auth): ?>
    <form method="post" style="margin:0">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn-logout">ออก</button>
    </form>
    <?php else: ?>
    <span></span>
    <?php endif; ?>
</div>

<?php if (!$auth): ?>
<!-- ── PIN Screen ── -->
<div class="content">
    <div class="pin-wrap">
        <div class="pin-title">ใส่ PIN เพื่อเข้าตั้งค่า</div>
        <div class="pin-sub">Queue Display Settings</div>
        <div class="pin-dots">
            <div class="pin-dot" id="d0"></div>
            <div class="pin-dot" id="d1"></div>
            <div class="pin-dot" id="d2"></div>
            <div class="pin-dot" id="d3"></div>
        </div>
        <?php if ($msg): ?>
        <div class="alert alert-err"><?= h($msg) ?></div>
        <?php endif; ?>
        <form method="post" id="pinForm">
            <input type="hidden" name="action" value="pin">
            <input type="password" name="pin" id="pinInput" class="pin-input"
                   maxlength="4" inputmode="numeric" pattern="[0-9]*"
                   autocomplete="off" autofocus placeholder="••••">
            <br><br>
            <button type="submit" class="btn-pin">ยืนยัน</button>
        </form>
    </div>
</div>
<script>
(function(){
    var inp = document.getElementById('pinInput');
    inp.addEventListener('input', function(){
        var v = this.value.replace(/\D/g,'').slice(0,4);
        this.value = v;
        for(var i=0;i<4;i++){
            document.getElementById('d'+i).classList.toggle('filled', i < v.length);
        }
        if(v.length === 4){ document.getElementById('pinForm').submit(); }
    });
}());
</script>

<?php else: ?>
<!-- ── Settings Form ── -->
<div class="content">
    <?php if ($msg): ?>
    <div class="alert <?= $isErr ? 'alert-err' : 'alert-ok' ?>"><?= h($msg) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="save">

        <!-- Database -->
        <div class="section">
            <div class="section-title">ฐานข้อมูล</div>
            <div class="row2">
                <div class="field">
                    <label>Host / IP</label>
                    <input type="text" name="db_host" value="<?= h(sv($local,'db_host','127.0.0.1')) ?>" placeholder="127.0.0.1">
                </div>
                <div class="field">
                    <label>Port</label>
                    <input type="number" name="db_port" value="<?= h(sv($local,'db_port',3307)) ?>" min="1" max="65535">
                </div>
            </div>
            <div class="field">
                <label>Database Name</label>
                <input type="text" name="db_name" value="<?= h(sv($local,'db_name','')) ?>">
            </div>
            <div class="row2">
                <div class="field">
                    <label>User</label>
                    <input type="text" name="db_user" value="<?= h(sv($local,'db_user','')) ?>">
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="db_pass" value="<?= h(sv($local,'db_pass','')) ?>" autocomplete="new-password">
                </div>
            </div>
            <button type="button" class="btn-test" id="btnTest">ทดสอบการเชื่อมต่อ</button>
            <div class="test-result" id="testResult"></div>
        </div>

        <!-- Computer -->
        <div class="section">
            <div class="section-title">คอมพิวเตอร์จอแสดงคิว</div>
            <div class="field">
                <label>เลือก Computer (ComputerType = 4)</label>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <select name="computer_id" id="computerSel" style="flex:1;min-width:180px;background:#0f172a;border:1px solid #334155;color:#f1f5f9;padding:10px 12px;border-radius:8px;font-size:15px;outline:none">
                        <option value="0">(ยังไม่เลือก)</option>
                        <?php
                        $curCid = (int)sv($local, 'computer_id', 0);
                        if ($curCid > 0):
                        ?>
                        <option value="<?= h($curCid) ?>" selected>ComputerID <?= h($curCid) ?></option>
                        <?php endif; ?>
                    </select>
                    <button type="button" class="btn-test" id="btnLoadComputers">โหลดรายการ</button>
                </div>
                <div class="test-result" id="computerLoadResult"></div>
            </div>
        </div>

        <!-- Queue Behavior -->
        <div class="section">
            <div class="section-title">การแสดงผล Queue</div>
            <div class="row2">
                <div class="field">
                    <label>Refresh (มิลลิวินาที)</label>
                    <input type="number" name="queue_refresh_ms" value="<?= h(sv($local,'queue_refresh_ms',5000)) ?>" min="1000" step="500">
                </div>
                <div class="field">
                    <label>แสดง READY กี่นาที (0=ทั้งวัน)</label>
                    <input type="number" name="ready_display_minutes" value="<?= h(sv($local,'ready_display_minutes',40)) ?>" min="0">
                </div>
            </div>
            <div class="row2">
                <div class="field">
                    <label>จำนวนคอลัมน์ Grid</label>
                    <input type="number" name="grid_columns" value="<?= h(sv($local,'grid_columns',2)) ?>" min="1" max="8">
                </div>
                <div class="field">
                    <label>READY แสดงสูงสุด (รายการ)</label>
                    <input type="number" name="ready_limit" value="<?= h(sv($local,'ready_limit',30)) ?>" min="1">
                </div>
            </div>
            <div class="field">
                <label>PREPARING แสดงสูงสุด (รายการ)</label>
                <input type="number" name="preparing_limit" value="<?= h(sv($local,'preparing_limit',30)) ?>" min="1">
            </div>
        </div>

        <!-- Appearance -->
        <div class="section">
            <div class="section-title">หน้าตา</div>
            <div class="field">
                <label>สีพื้นหลังหัว READY / PREPARING</label>
                <div class="color-row">
                    <input type="color" id="cp_hbg" value="<?= h(sv($local,'color_header_bg','#1a1a2e')) ?>" oninput="document.getElementById('t_hbg').value=this.value">
                    <input type="text" name="color_header_bg" id="t_hbg" value="<?= h(sv($local,'color_header_bg','#1a1a2e')) ?>" oninput="syncColor(this,'cp_hbg')">
                </div>
            </div>
            <div class="field">
                <label>สีตัวอักษรหัว (READY / PREPARING)</label>
                <div class="color-row">
                    <input type="color" id="cp_htx" value="<?= h(sv($local,'color_header_text','#ffffff')) ?>" oninput="document.getElementById('t_htx').value=this.value">
                    <input type="text" name="color_header_text" id="t_htx" value="<?= h(sv($local,'color_header_text','#ffffff')) ?>" oninput="syncColor(this,'cp_htx')">
                </div>
            </div>
            <div class="field">
                <label>สีตัวเลข Queue</label>
                <div class="color-row">
                    <input type="color" id="cp_qtx" value="<?= h(sv($local,'color_queue_text','#1a1a2e')) ?>" oninput="document.getElementById('t_qtx').value=this.value">
                    <input type="text" name="color_queue_text" id="t_qtx" value="<?= h(sv($local,'color_queue_text','#1a1a2e')) ?>" oninput="syncColor(this,'cp_qtx')">
                </div>
            </div>
            <div class="field">
                <label>สีพื้นหลังหน้าจอ</label>
                <div class="color-row">
                    <input type="color" id="cp_abg" value="<?= h(sv($local,'color_app_bg','#ffffff')) ?>" oninput="document.getElementById('t_abg').value=this.value">
                    <input type="text" name="color_app_bg" id="t_abg" value="<?= h(sv($local,'color_app_bg','#ffffff')) ?>" oninput="syncColor(this,'cp_abg')">
                </div>
            </div>
            <div class="field">
                <label>ชื่อร้าน (แสดงด้านล่างหน้าจอ)</label>
                <input type="text" name="shop_name" value="<?= h(sv($local,'shop_name','')) ?>" placeholder="ONE TO TWO COFFEE COMPANY">
            </div>
            <div class="field">
                <label>ภาพพื้นหลัง (ชื่อไฟล์ในโฟลเดอร์ queue_display)</label>
                <input type="text" name="bg_image" value="<?= h(sv($local,'bg_image','')) ?>" placeholder="background.jpg">
            </div>
        </div>

        <!-- Security -->
        <div class="section">
            <div class="section-title">ความปลอดภัย</div>
            <div class="field">
                <label>PIN สำหรับเข้าหน้าตั้งค่า</label>
                <input type="text" name="settings_pin" value="<?= h(sv($local,'settings_pin','1234')) ?>" maxlength="16" inputmode="numeric" style="width:200px">
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn-save">บันทึกการตั้งค่า</button>
        </div>
    </form>
</div>

<script>
function syncColor(txtEl, colorId) {
    var v = txtEl.value.trim();
    if (/^#[0-9a-fA-F]{3,8}$/.test(v)) {
        document.getElementById(colorId).value = v;
    }
}

document.getElementById('btnLoadComputers').addEventListener('click', function() {
    var form = this.closest('form');
    var data = new FormData();
    data.append('action',  'list_computers');
    data.append('db_host', form.querySelector('[name=db_host]').value);
    data.append('db_port', form.querySelector('[name=db_port]').value);
    data.append('db_name', form.querySelector('[name=db_name]').value);
    data.append('db_user', form.querySelector('[name=db_user]').value);
    data.append('db_pass', form.querySelector('[name=db_pass]').value);
    var el  = document.getElementById('computerLoadResult');
    var sel = document.getElementById('computerSel');
    el.textContent = 'กำลังโหลด...';
    el.className = 'test-result';
    fetch('settings.php', { method: 'POST', body: data })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) {
                el.textContent = d.message || 'โหลดไม่ได้';
                el.className = 'test-result err';
                return;
            }
            var curVal = sel.value;
            sel.innerHTML = '<option value="0">(ยังไม่เลือก)</option>';
            d.computers.forEach(function(c) {
                var opt = document.createElement('option');
                opt.value = String(c.id);
                opt.textContent = c.name + ' (ID: ' + c.id + ')';
                if (String(c.id) === curVal) opt.selected = true;
                sel.appendChild(opt);
            });
            if (d.computers.length === 0) {
                el.textContent = 'ไม่พบ Computer ที่มี ComputerType = 4';
                el.className = 'test-result err';
            } else {
                el.textContent = 'พบ ' + d.computers.length + ' เครื่อง';
                el.className = 'test-result ok';
            }
        })
        .catch(function() { el.textContent = 'เกิดข้อผิดพลาด'; el.className = 'test-result err'; });
});

document.getElementById('btnTest').addEventListener('click', function() {
    var form = this.closest('form');
    var data = new FormData();
    data.append('action',  'test_db');
    data.append('db_host', form.querySelector('[name=db_host]').value);
    data.append('db_port', form.querySelector('[name=db_port]').value);
    data.append('db_name', form.querySelector('[name=db_name]').value);
    data.append('db_user', form.querySelector('[name=db_user]').value);
    data.append('db_pass', form.querySelector('[name=db_pass]').value);
    var el = document.getElementById('testResult');
    el.textContent = 'กำลังทดสอบ...';
    el.className = 'test-result';
    fetch('settings.php', { method: 'POST', body: data })
        .then(function(r){ return r.json(); })
        .then(function(d){
            el.textContent = d.message;
            el.className = 'test-result ' + (d.success ? 'ok' : 'err');
        })
        .catch(function(){ el.textContent = 'เกิดข้อผิดพลาด'; el.className = 'test-result err'; });
});
</script>
<?php endif; ?>

</body>
</html>
