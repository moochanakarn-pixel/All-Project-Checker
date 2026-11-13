# Checker KDS

ระบบ Kitchen Display System (KDS) สำหรับแสดงคิวอาหารในครัว รองรับการ checkout, สแกนบาร์โค้ด และควบคุมสินค้าหมด

**เวอร์ชันปัจจุบัน:** v1.13.1

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

### Filter SaleMode / Zone (Legacy)

ค่าด้านล่างนี้เป็น **legacy** — ใช้ได้เฉพาะเมื่อไม่มี DB table `checkeraccesssalemode` / `checkeraccesstablezone` บนระบบนั้น เมื่อ table มีอยู่แล้ว ค่าเหล่านี้จะถูก ignore โดยอัตโนมัติ (DB wins)

```php
// legacy — ลบออกหลัง migrate ไปใช้ DB table แล้ว
'allowed_sale_mode_ids' => [1, 2],   // กรอง SaleMode ที่จะแสดงบน KDS นี้
'allowed_zone_ids'      => [1, 3],   // กรอง Table Zone ที่จะแสดงบน KDS นี้
```

> **Migration path:** ตั้งค่าผ่านหน้า "Manage Computer Checker" ใน POS → ลบ key ทั้งสองออกจาก `settings.local.php`

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
| v1.12.2 | Camera barcode duplicate checkout เมื่อ API ช้า (cooldown 1500→3000ms) |
| v1.12.2 | `checkoutBarcode` ไม่มี feedback เสียง/สั่นเมื่อสำเร็จ |
| v1.12.1 | `confirmVoid` เรียก undefined `currentFinishStaffId()` → `getFinishStaffId()` |
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

ดู version history ทั้งหมดได้ที่ [CHANGELOG.md](CHANGELOG.md)
