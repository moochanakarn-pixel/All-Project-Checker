# Checker KDS

ระบบ Kitchen Display System (KDS) สำหรับแสดงคิวอาหารในครัว รองรับการ checkout, สแกนบาร์โค้ด และควบคุมสินค้าหมด

**เวอร์ชันปัจจุบัน:** v1.11.0

---

## โครงสร้างไฟล์

| ไฟล์ | หน้าที่ |
|---|---|
| `checker.php` | หน้าหลัก UI ของระบบ KDS |
| `api_checker.php` | API backend สำหรับดึงและอัปเดตข้อมูล |
| `config.php` | ค่า configuration ของระบบ |
| `auth_check.php` | ตรวจสอบสิทธิ์การเข้าถึง |
| `screen_lock.php` | หน้า screen lock |
| `screen_lock.js` | Logic การ lock/unlock หน้าจอ |
| `settings.local.php` | ค่า local เฉพาะเครื่อง (DB credentials ฯลฯ) — ไม่ track ใน git |
| `KDS_TEMPLATE/` | Template สำหรับติดตั้ง KDS หลายจุด (subfolder) |

---

## ความต้องการของระบบ

- PHP รองรับ `mysqli`
- MySQL 5.1 ขึ้นไป
- Browser รองรับ BarcodeDetector API (Chrome/Edge) หรือใช้ jsQR fallback

---

## การตั้งค่า

แก้ไขค่าใน `settings.local.php`:

```php
return [
    'db_host'     => '127.0.0.1',
    'db_port'     => 3307,   // ล็อคไว้ที่ 3307 — ไม่ต้องแก้
    'db_name'     => 'ชื่อฐานข้อมูล',
    'db_user'     => 'username',
    'db_pass'     => 'password',
    'shop_id'     => 1,
    'current_computer_id' => 1,
];
```

> **หมายเหตุ:** ค่า `db_port` ถูกล็อคที่ 3307 และไม่แสดงในหน้า Settings UI — หากต้องการเปลี่ยน ให้แก้ `settings.local.php` โดยตรง

ค่าสำคัญใน `config.php`:

| ค่า | Default | ความหมาย |
|---|---|---|
| `APP_REFRESH_MS` | `15000` | รีเฟรชคิวทุก 15 วินาที |
| `FINISHED_REFRESH_EVERY` | `3` | โหลดรายการเสร็จทุก 3 รอบ (45 วิ) |
| `FINISHED_ROWS_TODAY_ONLY` | `true` | แสดงรายการเสร็จเฉพาะวันนี้ |
| `RECENT_FINISHED_LIMIT` | `0` | จำนวนรายการเสร็จสูงสุด (0 = ไม่จำกัด) |
| `ENABLE_BARCODE_CHECKOUT` | `true` | เปิดใช้สแกนบาร์โค้ด |

---

## ฟีเจอร์หลัก

- **แสดงคิวอาหาร** — แสดง order ที่รอทำและกำลังทำอยู่แบบ real-time
- **Checkout** — กดยืนยันรายการเสร็จแล้ว พร้อม 2-step checkout option
- **Undo** — ย้อนกลับรายการที่ checkout ผิด
- **Barcode scan** — สแกนบาร์โค้ดจากคีย์บอร์ดหรือกล้อง รองรับ numeric และ alphanumeric (QR code)
- **กล้องสแกนต่อเนื่อง** — เปิดกล้องค้างและสแกนอัตโนมัติทุก 800ms
- **SET menu** — รองรับการแสดงเมนูชุด (parent/child cards)
- **สินค้าหมด** — ควบคุมสถานะสินค้าหมดได้จากหน้าจอ
- **Sound alert** — แจ้งเตือนเสียงเมื่อมี order ใหม่
- **Screen lock** — ล็อคหน้าจอเมื่อไม่ใช้งาน รองรับหลายเครื่องพร้อมกัน
- **Multi-KDS** — ติดตั้งหลายจุดในโฟลเดอร์ย่อย (`KDS_TEMPLATE/`) บน IIS
- **Staff login บน topbar** — กรอกรหัสพนักงานได้จากหน้าหลัก บังคับ login ก่อน checkout ทุกช่องทาง
- **Zone lock** — ล็อคโซนไม่ให้เปลี่ยนโดยไม่ตั้งใจ ตั้งค่าผ่าน settings modal; จำโซนที่เลือกข้ามรอบ refresh
- **Appearance panel** — ปรับขนาด Card (S/M/L/XL) และ Font Scale (85–130%) ผ่านแท็บ "หน้าตา" ใน Settings; บันทึกใน `localStorage`
- **DB Offline Banner** — แสดง banner แจ้งเตือนเมื่อ polling ล้มเหลว 3 ครั้งติดกัน; หายอัตโนมัติเมื่อกลับมา online
- **ชื่อเครื่อง auto-fill** — Settings จะ query ชื่อเครื่องจาก `computername` table อัตโนมัติเมื่อกรอก Computer ID

---

## สถานะบัคปัจจุบัน

### ✅ แก้แล้ว

| เวอร์ชัน | รายการ |
|---|---|
| v1.9.0 | รายการยกเลิก (voided) ไม่แสดงบนจอ KDS |
| v1.9.0 | Notice "checkout สำเร็จ" บังปุ่มกด |
| v1.9.0 | Zone table cache stale — โต๊ะย้ายโซนแล้วการ์ดไม่โผล่ |
| v1.8.0 | Save settings double-submit |
| v1.8.0 | Barcode double Enter / alphanumeric / buffer timeout |
| v1.7.0 | Filter chip double-toggle |
| v1.7.0 | Zone รีเซ็ตเมื่อ localStorage หาย (zone_lock=ON) |
| v1.6.0 | `finish_staff_id = 0` validation ผิด |
| v1.6.0 | CURDATE() timezone mismatch (เปลี่ยนเป็น PHP `date()`) |
| v1.5.0 | บังคับ login ก่อน checkout |
| v1.5.0 | Zone lock — ซ่อนปุ่มเปลี่ยนโซนเมื่อ lock |
| v1.5.0 | จำโซนข้ามรอบ refresh ด้วย localStorage |
| v1.4.2 | SQL injection ใน `tableExists()` |
| v1.4.2 | `strtotime()` บนค่า null |
| v1.4.2 | Missing `isset()` ใน insert row |
| v1.4.1 | รายการเสร็จสะสมไม่หาย (เปิด `FINISHED_ROWS_TODAY_ONLY`) |
| v1.4.x | Refresh loop หยุดเมื่อ API error |
| v1.4.x | กล้อง barcode เปิดซ้อนกัน |
| v1.4.x | Render sold out modal หลังปิดไปแล้ว |
| v1.4.x | Notice หายเร็วเกินไป |

### ⚠️ ค้างอยู่ (กำหนดแก้รอบถัดไป)

| ความเสี่ยง | รายการ |
|---|---|
| 🟡 ต่ำ | Screen lock kick ช้า ~8 วินาที |
| 🟡 ต่ำ | Undo state อาจไม่ sync กับ DB ในบางกรณี |

---

## หมายเหตุ

- ไฟล์ `settings.local.php` ไม่ถูก track ใน git (มี DB credentials)
- รองรับ IIS และ Apache

---

# Patch Notes

## v1.11.0 — 2026-05-21

### 🆕 Features
- **[Config] `ACTIVE_ROWS_TODAY_ONLY` configurable** — ตั้งค่าได้ใน `settings.local.php` ด้วย key `active_rows_today_only` (default: `true`) — แก้ปัญหา order ไม่โชว์หลังเที่ยงคืนเมื่อ POS ใช้ business date
- **[Config] `FINISHED_ROWS_TODAY_ONLY` configurable** — ตั้งค่าได้ใน `settings.local.php` ด้วย key `finished_rows_today_only` (default: `true`)
- **[SET menu] DisplayFlexibleProductAtChecker** — เมื่อ `products.DisplayFlexibleProductAtChecker = 1` และ `ProductSetType = 7` จะแสดงเฉพาะ parent row บน KDS ซ่อน child ทั้งหมด (checkout parent แล้ว checkout child อัตโนมัติผ่าน `fetchLockedChildRows`) — ใช้ `columnExists()` guard รองรับ DB ที่ไม่มี column นี้
- **[Void] Void Confirm Mode** — เพิ่มตั้งค่า `void_confirm_mode` ใน `settings.local.php`:
  - **Auto (default):** เมื่อ KDS poll รายการ ProcessStatus=98 จะถูกอัปเดตเป็น 99 ทันทีโดยอัตโนมัติ
  - **Manual:** รายการที่ถูกยกเลิกโชว์เป็นการ์ดสีแดงพร้อมปุ่ม "ยืนยันยกเลิก" ให้ครัวกดยืนยันเอง

### ♻️ Refactoring
- **ลบ dead code 437 บรรทัด** — `guardDuplicateScan()`, `fetchCommentsByRowKeys()`, กลุ่ม checkout print infra ที่ไม่ได้ใช้งาน (11 ฟังก์ชัน), และ `FINISHED_PREVIEW_LIMIT` constant

---

## v1.10.0 — 2026-05-18

### 🆕 Features
- **[Settings] Checkout จำนวน Mode** — เพิ่มตั้งค่า "การ Checkout เมื่อจำนวน > 1" มี 3 โหมด:
  - **Mode 1 (ค่าเริ่มต้น):** ทีละ 1 จำนวน — ส่ง `qty_to_finish=1` ทุกครั้ง เหมือนเดิม
  - **Mode 2:** จบทั้งหมดในครั้งเดียว — ส่ง `qty_to_finish=ProductAmount` ทุกครั้ง
  - **Mode 3:** เลือกจำนวนเอง — เปิด popup stepper ให้เชฟเลือก 1–N ก่อนส่ง (เฉพาะเมื่อ qty > 1)
- **[API] `checkoutOne` รับ `qty_to_finish`** — รองรับการ checkout บางส่วน, split SubProcessID row, คำนวณ child qty ตามสัดส่วน

### 🐛 Bug Fixes
- **[UI] Red error bar เมื่อกดรัวตอนหมดจอ** — suppress "ถูก checkout ไปแล้ว" error — ไม่แสดง banner เมื่อรายการนั้น checkout สำเร็จแล้ว (race condition ระหว่าง tap กับ state refresh)

---

## v1.9.0 — 2026-05-18

### 🐛 Bug Fixes
- **[UI] ซ่อนรายการยกเลิก** — ไม่ดึง ProcessStatus 98 (voided) มาแสดงบนจอ KDS อีกต่อไป — ลบ CSS, rendering, และ sound tracking ที่เกี่ยวข้องออก
- **[UI] ลบ notice "checkout สำเร็จ"** — เอา popup แจ้งเตือนสำเร็จออกทั้ง 2 จุด (กดการ์ด + barcode) เพราะบังปุ่มกด — คง notice error และ 2-step "ยืนยันรายการแล้ว" ไว้
- **[Zone] Zone table cache stale** — `cachedZoneTableIds` refresh ทุก 4 poll cycle (~60 วิ) แทนการ refresh เฉพาะตอนเปลี่ยนโซน — แก้ปัญหาโต๊ะย้ายโซนแล้วการ์ดไม่โผล่จนกว่าจะ refresh มือ
- **[UI] SET menu label สีน้ำเงินซ้อน moved badge** — เปลี่ยน `.parent-name-label` จาก `--primary` (น้ำเงิน) เป็นเขียว (`#16a34a`) แยกออกจาก moved card indicator

---

## v1.8.0 — 2026-05-17

### 🆕 Features
- **[Settings] Appearance panel** — เพิ่มแท็บ "หน้าตา" ในหน้าตั้งค่า: เลือกขนาด Card (S/M/L/XL) และปรับ Font Scale (85–130%) พร้อมปุ่ม "↺ คืนค่า Default" — บันทึกลง `localStorage` (`kds_appearance`) ไม่หาย refresh
- **[Settings] ชื่อเครื่อง auto-fill** — เมื่อกรอก Computer ID ระบบ query `computername` table จาก DB โดยอัตโนมัติและแสดงชื่อเครื่องเป็น read-only พร้อม success/error state
- **[Offline] DB Offline Banner** — แสดง banner แดงบนสุดหน้าจอ "ระบบ OFFLINE" เมื่อ polling ล้มเหลวติดต่อกัน 3 ครั้ง พร้อมปุ่ม "🔄 ลองใหม่"

### 🐛 Bug Fixes
- **[Settings] Save double-submit** — เพิ่ม guard ปุ่ม "บันทึกค่าระบบ" (disable + "กำลังบันทึก...") ระหว่างส่ง request
- **[Barcode] Double Enter** — เพิ่ม `e.stopPropagation()` ป้องกัน Enter กระตุ้น handler ซ้ำ
- **[Barcode] QR Code alphanumeric** — regex `/^[0-9]$/` → `/^[0-9A-Za-z\-]$/`
- **[Barcode] Buffer timeout สั้นเกิน** — ขยาย threshold 280ms → 500ms

### ♻️ Refactoring
- **[Settings] Port field ลบออก** — ล็อคค่า DB Port เป็น 3307 ด้วย hidden input
- **[Settings] ปุ่มทดสอบการเชื่อมต่อ** — ย้ายมาอยู่ inline กับ Database Name
- **[CSS] Theme variables** — แทนที่สีแบบ hardcode 14+ จุด ด้วย CSS variables

### ⚙️ API
- เพิ่ม action `lookup_computer_name` — query `computername` WHERE `Deleted = 0` → คืน `computer_name`

---

## v1.7.0 — 2026-05-14

### 🆕 Features
- **[Filter] กรอง SaleMode ต่อสถานี** — แต่ละ KDS เลือก SaleMode ได้ผ่าน Settings → บันทึกลง `settings.local.php` → กรองฝั่ง server
- **[Filter] กรองโซนต่อสถานี (server-side)** — เพิ่ม zone filter (`allowed_zone_ids`) แยกจาก zone selector บน topbar
- **[Filter] Smart SaleMode + Zone logic** — `TableID > 0` ผ่านตาม zone, `TableID = 0` กรองตาม SaleMode

### 🐛 Bug Fixes
- **[UI] Filter chip double-toggle** — เพิ่ม `e.preventDefault()` แก้ toggle ซ้ำ
- **[Zone Lock] localStorage หาย → zone รีเซ็ต** — auto-select zone แรกจาก `allowed_zone_ids` เมื่อ localStorage ถูกล้าง

### ⚙️ API
- เพิ่ม `list_sale_modes`, `parseIdList()`, `getStationFilter()`
- `fetchActiveRows` / `fetchFinishedRows` เพิ่ม `LEFT JOIN tableno` เฉพาะเมื่อ zone filter ใช้งาน

---

## v1.6.0 — 2026-05-13

### 🆕 Features
- **[Settings] toggle ปุ่มสินค้าหมด** — ซ่อน/แสดงปุ่ม "ปิดสินค้าหมด" บนแถบเครื่องมือได้

### 🐛 Bug Fixes
- **[Startup] ตรวจสอบ DB ก่อน restore staff** — ป้องกัน error loop เมื่อ DB ไม่พร้อม
- **[API] `finish_staff_id` validation ผิด** — แก้ `<= 0` เป็น `< 0`
- **[API] CURDATE() timezone mismatch** — เปลี่ยนเป็น PHP `date('Y-m-d')`

### ⚙️ Config
- `ACTIVE_ROWS_TODAY_ONLY`: เปลี่ยนค่าเริ่มต้นเป็น `true`
- `RECENT_FINISHED_LIMIT`: เปลี่ยนจาก 0 เป็น 50

---

## v1.5.0 — 2026-05-06

### 🆕 Features
- **[Staff] บังคับ Login ก่อน Checkout** — ทั้ง 3 ช่องทาง (กดการ์ด, barcode, กล้อง)
- **[Zone] ล็อคโซน** — ซ่อนปุ่มเปลี่ยนโซนบนหน้าหลัก
- **[Zone] จำโซนข้ามรอบ refresh** — บันทึกลง localStorage

---

## v1.4.2 — 2026-05-06

### 🐛 Bug Fixes
- **[Security] SQL Injection ใน `tableExists()`** — เปลี่ยนเป็น `information_schema.TABLES` + prepared statement
- **[API] `strtotime()` บนค่าว่าง** — เพิ่ม guard เมื่อ `finishedAt` เป็น null
- **[API] Missing `isset()`** — ใน insert row

---

## v1.4.1 — 2026-05-06

### 🐛 Bug Fixes
- **[Finished] รายการเสร็จแล้วสะสมไม่หาย** — เปิด `FINISHED_ROWS_TODAY_ONLY = true`

---

## v1.4.0 — 2026-05-03

### 🆕 Features
- **SET menu display mode** — `displayflexibleproductatchecker = 1` แสดงหัวเมนู, `= 0` แสดงลูก

### 🐛 Bug Fixes
- **[SET menu] หัวเมนูโผล่กลับหลัง checkout ลูกครบ** — auto-checkout หัวเมื่อ checkout ลูกตัวสุดท้าย

---

## v1.3.1 — 2026-05-02

### 🐛 Bug Fixes
- **[screen_lock] PHP 500 error** — แก้ IIFE syntax เป็น named function `_slComputeBase()`

---

## v1.3.0 — 2026-05-02

### 🆕 Features
- **กล้องสแกนต่อเนื่อง** — สแกน barcode อัตโนมัติทุก 800ms
- **Multi-KDS subfolder** — รองรับ `KDS_TEMPLATE/` บน IIS
- **jsQR library** — fallback สำหรับ browser ที่ไม่รองรับ BarcodeDetector API

### 🐛 Bug Fixes
- **[multi-KDS]** settings บันทึกผิดที่, session ปนกัน, port validation
- **[IIS]** Base URL และ API fetch URL ผิดสำหรับ subfolder
- **[MySQL]** charset utf8mb4 → revert เป็น `utf8`

---

## v1.2.0 — 2026-05-01

### 🆕 Features
- **QR code scanning** — เพิ่ม format `qr_code` ใน BarcodeDetector API

---

## v1.1.0 — 2026-04-23

### 🆕 Features
- **Waiter Display** — หน้าจอแสดงคิวสำหรับพนักงาน (`waiter_display.php` + `api_waiter.php`)

---

## v1.0.1 — 2026-04-22

### 🐛 Bug Fixes
- **[API] PHP warnings ใน JSON response** — เพิ่ม output buffering
- **[API] Error suppression** — ย้าย `@` ไปก่อน `require`

---

## v1.0.0 — 2026-04-22

### 🆕 Initial Setup
- ย้าย DB credentials ออกจาก code ไปไว้ใน `settings.local.php`
- เพิ่ม `.gitignore` — ไม่ track `settings.local.php`
- ระบบ KDS พร้อมใช้งาน: `checker.php`, `api_checker.php`, `config.php`, `auth_check.php`, `screen_lock.php`
