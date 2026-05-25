# Changelog — Staff Display

## [3.22.0] — 2026-05-21

### ลบ dead code ออก
- **[CLEANUP]** ลบไฟล์ที่ไม่ได้ใช้: `staff_display2.php`, `guide.html`
- **[CLEANUP]** ลบ 22 functions ใน `api_checker.php` ที่ unreachable เพราะ blocklist (write operations) — api_checker.php เหลือ 1,678 บรรทัด จาก 2,589 บรรทัด (-911 บรรทัด)
  - Blocked handlers: `handleTestSystemSettingsConnection`, `handleSaveSystemSettings`, `confirmOne`, `checkoutOne`, `checkoutBarcode`, `undoOne`, `resolveStatus`, `setProductOutOfStock`
  - Orphaned helpers (cascade): `normalizeSystemSettingsPayload`, `validateSystemSettingsPayload`, `writeSystemSettingsFile`, `parseCheckoutBarcode`, `fetchLockedProcessRowByBarcode`, `resolveProcessRow`, `sendCheckoutPrintToPrintServer`, `enqueueCheckoutPrintJob`, `decorateCheckoutPrintRow`, `buildCheckoutPrintServerPayload`, `fetchLockedProcessRow`, `fetchLockedChildRows`, `fetchLockedFinishedChildRowsForUndo`, `calculateChildCheckoutQty`, `applyCheckoutSplit`, `undoFinishedProcessRow`, `findNextSubProcessId`, `findAvailablePrinterById`, `resolvePrintJobTable`, `tableExists`, `findNextCheckoutPrintNo`, `fetchSaleModeName`, `buildCheckoutPrintHeader`, `buildCheckoutPrintComment`, `calculateProcessMinutes`

---

## [3.21.0] — 2026-05-21

### แก้ไข (ออเดอร์เสร็จแล้วของบิลก่อนหน้าสะสมทั้งวัน)
- **[SESSION-FILTER]** แก้ `IsOldSession` SQL ทั้ง 3 จุด (`fetchTableOrders`, `fetchActiveRows`, `fetchFinishedRows`)
  - เดิม: ใช้ `MAX(TransactionID)` subquery — ไม่ทำงานเพราะ `orderprocessdetailfront` มี `TransactionID=0` ตลอดขณะโต๊ะยังเปิด
  - ใหม่: `CASE WHEN opf.TransactionID > 0 THEN 1 ELSE 0 END` — ตรงจุดกว่า เพราะ `TransactionID=0` หมายถึงบิลยังเปิดอยู่ (session ปัจจุบัน), `TransactionID > 0` หมายถึงบิลปิดแล้ว (old session)
  - ลบ subquery ที่ไม่จำเป็นออก — ประสิทธิภาพดีขึ้น
- **[SESSION-FILTER]** `groupTables` JS: เพิ่ม `sessionStarts` map เป็น safety net — กรอง finished rows เก่ากว่า active session timestamp ออกฝั่ง client

---

## [3.20.0] — 2026-05-20

### ฟีเจอร์ใหม่ (แสดงเวลาทำอาหารต่อรายการในจอ modal)
- **[COOK-TIME]** ทุก order row ใน modal แสดงเวลาผ่านไปนับจากส่งออเดอร์ — คำนวณ client-side จาก `SubmitOrderDateTime` ที่มีอยู่แล้ว ไม่ต้อง query เพิ่ม
  - **กำลังทำ**: `🕒 X นาที` — สีเขียว / เหลือง / แดง ตาม threshold เดียวกับการ์ดหน้าหลัก
  - **เสร็จแล้ว**: `⏱ เสร็จใน X นาที` — สีเขียว (เวลาจาก submit ถึง finish)
  - **ยกเลิก**: ไม่แสดงเวลา
  - เพิ่ม helper `cookMin()` และ `elapsedBadge()`, CSS `.or-elapsed` พร้อม 3 state สี

---

## [3.19.0] — 2026-05-20

### แก้ไข (รวมโต๊ะแสดงผลถูกต้อง)
- **[COMBINE-VISIBLE]** โต๊ะที่ถูกรวม (`is_combined=true`) เปลี่ยนจาก "ซ่อน" เป็น "แสดงพร้อม badge 🔗 รวมโต๊ะแล้ว" — ครัวเห็นออเดอร์ทุกรายการจากทุกโต๊ะที่รวมกัน
  - `isHidden()` ตอนนี้คืน `is_old_session` เท่านั้น — `is_combined` ไม่ถูกซ่อนอีกต่อไป
  - `groupTables`: เพิ่ม `hasCombined` flag ใน group object, `is_combined` orders ยังนับใน `g.pending` / `g.done` ปกติ
  - `buildCard`: เพิ่ม badge `🔗 รวมโต๊ะแล้ว` (สีม่วง) เมื่อ `hasCombined=true`
  - Modal: แสดง `is_combined` orders ตามปกติ กรองออกแค่ `is_old_session`
  - เพิ่ม CSS `.tc-badge.combined` (สีม่วง `#7c3aed`)

---

## [3.18.0] — 2026-05-20

### Security: ลบ write endpoints ออกจาก view-only display
- **[SECURITY]** `staff_display` เป็นจอ view-only — ไม่ควรมี write operations ใดๆ
- เพิ่ม blocklist ที่ต้นของ routing ใน `api_checker.php`: `checkout_one`, `confirm_one`, `undo_one`, `resolve_status`, `checkout_barcode`, `set_product_out_of_stock`, `save_system_settings`, `test_system_settings_connection` → return **HTTP 403** ทันที
- ลบ `if` routing blocks ของ write actions ออกให้สะอาด
- Actions ที่เหลือเป็น GET read-only ทั้งหมด + `lookup_staff` (POST สำหรับระบุตัวตน)

---

## [3.17.0] — 2026-05-20

### แก้ไขบัค (is_combined ไม่ทำงานเมื่อ TransactionID = 0)
- **[BUG-TXID-ZERO]** ร้านที่ `orderprocessdetailfront.TransactionID = 0` ทุก row (ไม่ใช้ TransactionID) → เงื่อนไข `opf.TransactionID > 0` ทำให้ `is_combined` ไม่มีวันเป็น true → โต๊ะที่รวมแล้ว (CombineBill) ยังคงแสดงบนจอ
  - **ตรวจยืนยันจาก data จริง**: `ordertransactionfront` โต๊ะ 7 มี `TransactionStatusID = 7` แต่ `orderprocessdetailfront` มี `TransactionID = 0` → match ไม่ได้
  - **แก้**: ขยาย condition ใน 3 CASE statements — ถ้า `TransactionID > 0` → match ผ่าน TransactionID (เดิม), ถ้า `TransactionID = 0` → fallback match ผ่าน `TableID` แทน
  ```sql
  WHERE otf2.TransactionStatusID = 7
    AND (
        (opf.TransactionID > 0 AND otf2.TransactionID = opf.TransactionID)
        OR
        (opf.TransactionID = 0 AND otf2.TableID = opf.TableID)
    )
  ```

---

## [3.16.0] — 2026-05-20

### ปรับปรุง (IsOldSession ใช้ MAX TransactionID แทน ordertransactionfront)
- **[IMPROVE-OLD-SESSION]** เปลี่ยน logic ตรวจ session เก่าจากการ join `ordertransactionfront` มาใช้ `MAX(TransactionID)` จาก `orderprocessdetailfront` ตรงๆ
  - **เดิม**: `EXISTS(status=2 in ordertransactionfront)` → พึ่งพา `ordertransactionfront` ที่อาจ sparse หรือลบทิ้ง (ini76 pattern) ทำให้ false positive
  - **ใหม่**: `opf.TransactionID < MAX(opf2.TransactionID) WHERE TableID = opf.TableID` — TransactionID รันเพิ่มต่อเนื่องต่อบิล ค่าสูงสุดคือ session ล่าสุด ออเดอร์ที่ TransactionID ต่ำกว่า = session เก่า → ซ่อน
  - ไม่ต้องพึ่ง `ordertransactionfront` เลยสำหรับ old session detection → ไม่มี false positive จากข้อมูลขาด

---

## [3.15.0] — 2026-05-20

### แก้ไข (IsOldSession ใช้ status ที่ถูกต้อง)
- **[SIMPLIFY-STATUS]** `IsOldSession` เปลี่ยนจาก `NOT EXISTS(status IN(1,7))` เป็น `EXISTS(status=2)` ตรงๆ
  - Status ที่สำคัญมีแค่ 2 ค่า: **1 = OpenBill** (เปิดอยู่), **2 = CloseBill** (จ่ายเงินแล้ว)
  - ออเดอร์จาก session เก่า = TransactionID มี status=2 (CloseBill) ใน `ordertransactionfront` **และ** โต๊ะนั้นมี TransactionID ใหม่ที่ status=1 (OpenBill) อยู่แล้ว
  - ลบ `NOT EXISTS` ออก ทำให้ logic ชัดเจนขึ้นและไม่เกิด false positive จากกรณีที่ TransactionID ไม่อยู่ใน `ordertransactionfront` เลย (เช่น ini76 ที่ลบ record ทิ้ง)

---

## [3.14.0] — 2026-05-20

### แก้ไขบัค (Modal แสดงเฉพาะออเดอร์ยกเลิก)
- **[BUG-MODAL-VOID]** Modal แสดงเฉพาะออเดอร์ที่ยกเลิก ทั้งที่การ์ดหน้าหลักเห็น "กำลังทำ 2 / เสร็จแล้ว 8"
  - **Root cause**: Timing race — `state.active` (poll-based, stale) และ `fetchTableOrders` (on-demand) เรียก DB ต่างเวลากัน ตอน state refresh ยังไม่มี session ใหม่ → orders ไม่ถูก flag `is_old_session` → การ์ดนับถูก ตอนกดเปิด modal → ordertransactionfront อัพเดทแล้ว → orders เก่าได้ `is_old_session=true` → ถูกกรองออก เหลือแต่ voided orders (voided มี `is_voided=true` ทำให้ `is_old_session` เป็น false เสมอ)
  - **แก้ (`staff_display.php`)**: Modal ตรวจ `hasNewSession` ก่อน apply filter — ถ้าไม่มี non-voided order ที่ผ่าน `isHidden()` เลย แสดงว่าอาจเป็น false positive → fallback แสดงทุก non-combined order (ยกเว้น CombineBill จริงๆ เท่านั้น)

---

## [3.13.0] — 2026-05-20

### แก้ไขบัค (Critical: is_combined ใช้ผิดความหมาย ทำให้ออเดอร์หายจากจอ)
- **[BUG-ISCOMBINED]** `is_combined` ถูกนำไปใช้กับ "ออเดอร์จาก session เก่าหลังจ่ายเงิน" ซึ่งผิด — `is_combined` ควรหมายถึง **CombineBill (รวมโต๊ะ)** เท่านั้น (TransactionStatusID = 7 จาก POS)
  - การใช้ `is_combined` ผิดความหมายนี้ทำให้ออเดอร์ปัจจุบันบางโต๊ะถูกซ่อนหายไป (false positive)
  - **แก้ `api_checker.php`**: แยก CASE statement ออกเป็น 2 คอลัมน์
    - `TransactionStatusID`: ตรวจ `EXISTS(status=7)` → 7 เท่านั้น (CombineBill จริงๆ)
    - `IsOldSession` (ใหม่): `NOT EXISTS(status IN(1,7)) AND EXISTS(status=1 for TableID)` → 1 (ออเดอร์จาก session ก่อนหน้า หลังจ่ายเงินและมีลูกค้าใหม่เปิดโต๊ะแล้ว)
  - **แก้ `attachCommentsToRows`**: เพิ่ม `is_old_session` flag แยกจาก `is_combined`
  - **แก้ `staff_display.php`**: เพิ่ม helper `isHidden(r)` = `r.is_combined || r.is_old_session` ใช้แทนทุกจุดที่เคยใช้ `r.is_combined` เพื่อซ่อนออเดอร์เก่า

---

## [3.12.0] — 2026-05-20

### แก้ไขบัค (Critical: ใช้ TransactionStatusID ผิดความหมาย)
- **[BUG-STATUSID]** `TransactionStatusID = 7` ใน POS หมายถึง **CombineBill (รวมโต๊ะ)** ไม่ใช่ "จ่ายเงินแล้ว" — เราใช้ `<> 7` เป็นเงื่อนไขตรวจ "open transaction" ซึ่งผิดโดยสิ้นเชิง
  - ตอนลูกค้าจ่ายเงิน → `TransactionStatusID = 2` (CloseBill)
  - Transaction ที่ "ยังเปิดอยู่" = `TransactionStatusID = 1` (OpenBill)
  - **แก้**: เปลี่ยน 3 จุดใน `api_checker.php` จาก `TransactionStatusID <> 7` เป็น `TransactionStatusID = 1` ทำให้ detect ได้ถูกต้องว่า transaction ใดยังเปิดอยู่ และ transaction ใดปิดแล้ว (จ่าย/รวม/void ทุกกรณี)

---

## [3.11.0] — 2026-05-20

### แก้ไขบัค
- **Modal ยังเห็นประวัติออเดอร์ผสมเก่า-ใหม่** — `is_combined` กรองได้แค่กรณีที่โต๊ะมีใน `ordertransactionfront` แต่ร้านที่ตารางนั้นไม่ครบ (เช่น sushiseki) จะยังเห็นออเดอร์เก่า
  - **Frontend** (`staff_display.php`): เพิ่ม `getSessionStart(key)` — หาเวลา `SubmitOrderDateTime` แรกสุดจาก non-combined rows ใน `state` สำหรับโต๊ะนั้น แล้วส่งเป็น `session_start` parameter ไปยัง modal API
  - **Backend** (`api_checker.php`): `fetchTableOrders` รับ `session_start` parameter — เพิ่ม `AND opf.SubmitOrderDateTime >= ?` ใน WHERE clause ทำให้ modal แสดงเฉพาะออเดอร์ตั้งแต่ session ปัจจุบันเริ่มต้น (ไม่มีออเดอร์ลูกค้าก่อนหน้า)
  - ทำงานถูกต้องทั้งกรณี `ordertransactionfront` ครบและไม่ครบ

---

## [3.10.0] — 2026-05-20

### แก้ไขบัค (Critical: ออเดอร์ active หายออกจากจอ)
- **[BUG-CRITICAL]** `NOT EXISTS` ที่แก้ไว้ใน v3.9.0 กว้างเกินไป — `ordertransactionfront` ไม่ได้เก็บ transaction ทุกตัว (พบว่า sushiseki มีแค่ 3 records สำหรับ 3 โต๊ะ ขณะที่ KDS มีออเดอร์จาก 18+ TransactionIDs) ทำให้ออเดอร์ active จริงๆ 30+ rows ถูกซ่อนหายไป
  - **Root cause**: `ordertransactionfront` เก็บเฉพาะ session ที่ "เปิดอยู่" บางส่วน ไม่ใช่ทุก transaction
  - **แก้**: เพิ่มเงื่อนไขที่ 2 — ซ่อนออเดอร์เก่าเฉพาะเมื่อ **มีหลักฐานว่าโต๊ะนี้เปิด session ใหม่แล้ว** (`AND EXISTS(otf3 WHERE TableID = opf.TableID AND status≠7)`) ทำให้ซ่อนออเดอร์เก่าเฉพาะตอนที่รู้ชัดว่ามีลูกค้าใหม่มาแทนแล้วเท่านั้น

---

## [3.9.1] — 2026-05-20

### แก้ไขบัค
- **Modal แสดงออเดอร์โต๊ะเก่า** (`staff_display.php`) — หน้าหลักแสดงถูกแล้ว แต่พอกดเข้าไปดูรายละเอียดยังเห็นออเดอร์จาก session เก่าอยู่ เนื่องจาก modal ไม่ได้ filter `is_combined` rows ออกก่อน render แก้: เพิ่ม `.filter(r => !r.is_combined)` ก่อนแสดงผลใน modal

---

## [3.9.0] — 2026-05-20

### แก้ไขบัค (Root Cause: POS ลบ ordertransactionfront เมื่อจ่ายเงิน)
- **[BUG-ROOT]** ออเดอร์เก่าจาก session ที่ปิดบิลแล้วยังแสดงอยู่ในจอ KDS — สาเหตุที่แท้จริง: POS ระบบ**ลบ**แถวออกจาก `ordertransactionfront` เมื่อจ่ายเงิน (ไม่ได้ set status=7) ดังนั้น query เดิมที่ check `EXISTS(TransactionStatusID=7)` ไม่พบแถวใดเลย → `is_combined` ไม่ถูก set → ออเดอร์เก่าแสดงเป็น active
  - **แก้**: เปลี่ยนทั้ง 3 จุด (`fetchActiveRows`, `fetchFinishedRows`, `fetchTableOrders`) จาก `EXISTS(status=7)` เป็น `NOT EXISTS(status≠7)` — ถ้า TransactionID > 0 แต่ไม่มีในตาราง `ordertransactionfront` เลย (หรือมีแต่ status=7) → ถือว่าจ่ายเงินไปแล้ว → `is_combined=true`

---

## [3.8.0] — 2026-05-20

### แก้ไขบัค (จากการตรวจครั้งใหญ่)
- **[BUG-01]** `fetchFinishedRows` ขาด `opf.IsMoveOrder` — `is_moved` เป็น false เสมอสำหรับ finished rows ทำให้ moved order ที่ปิดบิลถูก set `is_combined` แทน `is_moved`
- **[BUG-02]** `fetchTableOrders` (modal) ขาด `TransactionStatusID` subquery — modal fallback (order_date filter) แสดง rows ทุก session รวม session เก่าที่ปิดบิลแล้ว
- **[BUG-04]** Connection leak ใน `handleTestSystemSettingsConnection` / `handleSaveSystemSettings` — ถ้า `lookupStaffDisplayNameByConnection` throw ขณะที่ `$conn` เปิดอยู่ connection จะไม่ถูกปิด แก้ด้วย try/finally
- **[BUG-07]** โต๊ะที่มีแต่ order ยกเลิก (voided) แสดงเป็นสีเขียว s-done — `cardCls()` คืน `s-done` เมื่อ `pending=0` แม้ `done=0` ด้วย แก้: `pending=0 && done=0` → `s-empty`
- **[BUG-11]** `getTransactionId()` / `getOrderDate()` fallback ที่ 2 ไม่กรอง `is_combined` — อาจดึง TransactionID ของ session เก่ามา แก้: ใช้ `state.finished` (กรอง `!is_combined`) แทน fallback ที่ 2

---

## [3.7.0] — 2026-05-20

### แก้ไขบัค
- **Session เก่าหายออกจากการ์ดเมื่อโต๊ะเปิด session ใหม่**
  - `fetchFinishedRows` (`api_checker.php`) — เพิ่ม `TransactionStatusID` subquery เดียวกับ `fetchActiveRows` เพื่อให้ finished items ของ transaction ที่ปิดแล้วได้รับ flag `is_combined=true`
  - `groupTables` (`staff_display.php`) — ข้าม `is_combined` rows ทั้ง active และ finished ทำให้โต๊ะที่ปิดบิลไปแล้วหายออกจากจอ และถ้ามีลูกค้าใหม่นั่งก็แสดงเฉพาะ session ใหม่เท่านั้น

---

## [3.6.0] — 2026-05-20

### แก้ไขบัค
- **การ์ดโต๊ะนับ "✅ เสร็จแล้ว" รวม items ของลูกค้าเก่า** (`staff_display.php`)
  - `groupTables` นับ `state.finished` โดยไม่กรอง TransactionID — items ที่ทำเสร็จแล้วของลูกค้าเก่าไปบวกในตัวนับ done ของลูกค้าใหม่
  - แก้: เก็บ `currentTxId` จาก non-combined active rows แล้วกรอง finished rows ให้นับเฉพาะ TransactionID เดียวกัน (หรือถ้าโต๊ะไม่มี active orders เลยก็นับทั้งหมดตามปกติ)

---

## [3.5.0] — 2026-05-20

### แก้ไขบัค
- **เวลาเปิดโต๊ะ (⏱) แสดงเวลาของลูกค้าเก่า** (`staff_display.php`)
  - `openTime` คำนวณจาก active rows ทุกแถวรวม `is_combined` — ทำให้การ์ดแสดงเวลาของ transaction เก่าแทนที่จะเป็นลูกค้าปัจจุบัน
  - แก้: ข้าม `is_combined` rows ตอนหาค่า openTime

---

## [3.4.0] — 2026-05-20

### แก้ไขบัค
- **Modal เปิดออเดอร์ผิด transaction เมื่อโต๊ะมีลูกค้าใหม่** (`staff_display.php`)
  - `getTransactionId` / `getOrderDate` ใช้ `.find()` คืน row แรกใน `state.active` (เรียง ASC) → ถ้า orders เก่า (is_combined=true) อยู่ก่อน modal จะโหลดข้อมูลลูกค้าเก่าแทน
  - แก้: ให้ค้นหา row ที่ `!is_combined` ก่อนเสมอ เพื่อให้ modal แสดง transaction ปัจจุบัน (ลูกค้าใหม่) เสมอ

---

## [3.3.0] — 2026-05-20

### แก้ไขบัค
- **ลูกค้าใหม่นั่งโต๊ะเดิมวันเดียวกัน — orders หายออกจาก KDS** (`api_checker.php`)
  - **บัคเดิม**: subquery ตรวจ `is_combined` ใช้ `TableID + ComputerID + OrderDate` → พอลูกค้าเก่าชำระเงินแล้ว (status=7) ลูกค้าใหม่ที่โต๊ะเดียวกันวันนั้นก็โดน `is_combined=true` ทำให้ orders ใหม่หายออกจากจอครัวทั้งหมด
  - **แก้ไข**: เปลี่ยนเป็น `CASE WHEN opf.TransactionID > 0 AND EXISTS(... WHERE otf2.TransactionID = opf.TransactionID ...)` ผูก is_combined กับ TransactionID ของ order นั้นโดยตรง ไม่ใช่ระดับโต๊ะ+วัน

---

## [3.2.0] — 2026-05-20

### เพิ่มใหม่
- รายการ non-KDS (PrinterID ไม่ตรงกับจอนี้) แสดงผลเป็น "✅ เสร็จแล้ว (ไม่ใช่จอนี้)" ทั้งบนการ์ดและใน modal
  - `groupTables` — นับ non-KDS items ใน `g.done` แทนที่จะซ่อนทิ้ง: การ์ดโต๊ะจะแสดง badge "✅ N เสร็จแล้ว" รวม items เหล่านี้
  - `buildRow` — label เปลี่ยนเป็น "✅ เสร็จแล้ว (ไม่ใช่จอนี้)" เพื่อบอกที่มา และไม่แสดง "เสร็จ -" เมื่อไม่มี FinishDateTime จริง

---

## [3.1.0] — 2026-05-19

### แก้ไขบัค
- **Login error ครั้งแรก** — แก้ปัญหา login ครั้งแรกขึ้น error แต่ครั้งที่สองด้วยรหัสเดิมกลับเข้าได้
  - `api_checker.php` — เพิ่ม `session_write_close()` ทันทีหลัง `auth_check.php` ปล่อย PHP session file lock ก่อน DB query ป้องกันการบล็อกจาก concurrent request
  - `staff_display.php` — เพิ่ม `window._isAuthed` flag ตั้งค่าใน `setStaff()` (true) / `showLogin()` (false)
  - `refreshBtn` และ `visibilitychange` guard ด้วย `_isAuthed` — ป้องกัน `loadAll()` ถูกเรียกขณะ login overlay ยังแสดงอยู่

---

## [3.0.0] — 2026-05-18

### เพิ่มใหม่ / ปรับปรุง
- `guide.html` — ออกแบบใหม่ทั้งหน้าให้ใช้ design system เดียวกับ `staff_display.php`
  - CSS variables, background gradient, topbar gradient ตรงกัน 100%
  - ใช้ class จริงจากแอป: `table-card`, `s-yellow`, `s-red`, `s-done`, `s-empty`, `tc-badge`, `tc-open-time`, `order-row`, `r-done`, `r-active`, `r-voided`, `btn-zone`, `sp-section`, `sp-toggle-on/off`
  - Mock login card, zone bar, settings panel ตรงกับ UI จริง
  - ปุ่ม "← กลับ" ใน topbar ลิ้งกลับ `staff_display.php`

---


## [2.9.0] — 2026-05-18

### เพิ่มใหม่
- `guide.html` — คู่มือการใช้งานแบบ standalone web page ครอบคลุม: login, topbar, การ์ดโต๊ะ, สีสถานะ, modal, zone filter, หน้าตั้งค่า

---

## [2.8.0] — 2026-05-18

### แก้ไขบัค
- `manifest.json` — เพิ่ม `"scope": "./"` ป้องกัน PWA เปิด 2 แถบเมื่อ URL เปลี่ยน (redirect หรือ query string)

---

## [2.7.0] — 2026-05-18

### แก้ไขบัค
- Guest mode — chip แสดง "เข้าสู่ระบบ" แทนที่จะซ่อน กดได้ทุกเมื่อเพื่อ login เป็นพนักงานจริง

---

## [2.6.0] — 2026-05-18

### เพิ่มใหม่
- โต๊ะว่างแสดง `TableName` จริงแทน `TableID` — `listTablesInZone` คืน `TableName` และ JS build `Map<TableID,TableName>` แทน `Set`
- `listZones` — เพิ่ม `WHERE Deleted=0` ไม่แสดงโซนที่ถูกลบ
- Settings panel — เพิ่ม section "🔐 การเข้าใช้งาน" toggle "บังคับใส่รหัสพนักงาน" (`kds_require_login`) ปิดแล้วข้าม login ได้เลย

---

## [2.5.0] — 2026-05-17

### แก้ไขบัค
- `config.php` default `db_port` จาก 3307 → 3306 (standard MySQL port)
- Logout button — ออก fullscreen ก่อนแสดง login overlay (browser บล็อก `confirm()` ใน fullscreen)
- `.staff-chip` — เพิ่ม `max-width:140px` + `text-overflow:ellipsis` ป้องกันชื่อยาวดัน topbar พัง

---

## [2.4.0] — 2026-05-17

### เพิ่มใหม่
- แสดงเวลาเปิดโต๊ะ (`⏱ HH:MM`) บนการ์ดใต้ชื่อโต๊ะ — ใช้ `SubmitOrderDateTime` ที่เร็วที่สุดของแต่ละโต๊ะ ไม่ต้องเพิ่ม column ใน DB

---

## [2.3.0] — 2026-05-17

### เพิ่มใหม่
- หน้า Login overlay — กรอก StaffCode เพื่อเข้าใช้งาน
  - POST `lookup_staff` → query ตาราง `staffs` WHERE StaffCode = ? AND Deleted = 0
  - เก็บ `{staff_id, staff_name}` ใน `localStorage['staff_display']` — reload ไม่ต้อง login ซ้ำ
  - แสดงชื่อพนักงานเป็น chip ใน topbar — กดเพื่อ logout
  - Polling เริ่มเฉพาะเมื่อ login สำเร็จเท่านั้น

### ล้างโค้ด
- ลบ `staff_display2.php` และอ้างอิงทั้งหมดออกจาก repo (ไฟล์ทดลองที่ไม่ได้ใช้งาน)
- แก้ `manifest.json` `start_url` จาก `staff_display2.php` → `staff_display.php`
- แก้ `web.config` ลบ IIS rewrite rule ที่ต้องการ URL Rewrite Module ออก

---

## [2.2.0] — 2026-05-13

### แก้ไขบัค
- `zone-bar` ternary — ทั้ง 2 branch ให้ผล `' hidden'` เหมือนกัน → zone bar ซ่อนตลอดใน KDS mode แก้เป็น `''` เมื่อไม่ใช่ serve mode
- `$_pageCid` เปลี่ยนจาก `$_REQUEST['cid']` เป็น `$_GET['cid']` ให้ตรงกับ `PAGE_CID` ฝั่ง JS ป้องกัน cid ผิด scope เมื่อ request มาจาก POST
- `finish_staff_id` validation — เปลี่ยนเงื่อนไขจาก `<= 0` เป็น `< 0` เพราะ 0 เป็นค่า default ที่ถูกต้อง (ยังไม่ตั้งค่า)
- `strtotime($finishedAt)` — เพิ่ม guard `!empty()` ก่อน call ป้องกัน false เข้า `date()` เมื่อ finishedAt ว่างเปล่า

---

## [2.1.0] — 2026-05-13

### เพิ่มใหม่
- หน้าตั้งค่า — เปิดด้วยการแตะ logo 3 ครั้งใน 1.5 วินาที
  - **⏱️ เวลา & รีเฟรช**: เตือนสีเหลือง/แดง (นาที), รีเฟรชทุก 15/30/60 วินาที
  - **🔔 การแจ้งเตือน**: เปิด/ปิดเสียง
  - **🖥️ จอแสดงผล**: ชื่อจอ, DB Host, Database Name, Computer ID (port/user/pass ซ่อน)
  - **🍽️ Serve Mode**: แสดงเฉพาะโต๊ะพร้อมเสิร์ฟ, ซ่อนปุ่มสลับหน้าเสิร์ฟ
- ค่า server-side บันทึกผ่าน `save_system_settings` API → `settings.local.php`
- ค่า client-side (refresh, serve options) บันทึกใน localStorage

---

## [2.0.0] — 2026-05-12

### เพิ่มใหม่
- `?mode=serve` — รวม Serve Display เข้า `staff_display.php` ในหน้าเดียว
  - `staff_display.php` → KDS mode (เดิม)
  - `staff_display.php?mode=serve` → Serve mode สำหรับพนักงานเสิร์ฟ
  - topbar เปลี่ยนสีเป็น teal, body class `serve-mode` override card colours
  - card สีเขียว+pulse = พร้อมเสิร์ฟทั้งหมด, สีส้ม = ยังมีรายการทำอยู่
  - เรียงโต๊ะพร้อมเสิร์ฟขึ้นก่อน ตามด้วย natural sort
  - modal แสดง status ต่อรายการ: กำลังทำ / พร้อมเสิร์ฟ / เสิร์ฟแล้ว
  - title bar `(N) Serve Display` นับโต๊ะที่พร้อมเสิร์ฟทั้งหมด
- ลบ `serve_display.php` ออก (รวมเข้า staff_display แล้ว)

---

## [1.9.0] — 2026-05-12

### เพิ่มใหม่
- `serve_display.php` — หน้า read-only สำหรับพนักงานเสิร์ฟ
  - แสดงโต๊ะที่มีอาหารพร้อมเสิร์ฟ (ProcessStatus=1/4, ServingDateTime IS NULL)
  - สีเขียว+pulse = พร้อมเสิร์ฟทั้งหมด, สีส้ม = ยังมีรายการทำอยู่
  - เรียงโต๊ะพร้อมเสิร์ฟขึ้นก่อน ตามด้วย natural sort ชื่อโต๊ะ
  - กด card เปิด modal ดูรายละเอียดแต่ละรายการ (กำลังทำ / พร้อมเสิร์ฟ / เสิร์ฟแล้ว)
  - ไม่มีปุ่มกดทำอะไร — read-only ทั้งหมด
- `api_checker.php` — เพิ่ม 2 action ใหม่
  - `list_serve_view` — คืนรายการโต๊ะที่ยังมีของรอเสิร์ฟ (group by table)
  - `list_serve_table_orders` — คืนรายการอาหารในโต๊ะ รวม ServingDateTime

---

## [1.8.0] — 2026-05-08

### เพิ่มใหม่ / แก้ไข
- `renderGrid` — เรียงโต๊ะด้วย natural sort (`localeCompare {numeric:true}`) แทน lexicographic เดิม: "1","2","10","11" และ "B1","B2","B10" เรียงถูกต้อง
- sort รันทุกครั้งไม่ว่า `state.zoneTables` จะ null หรือไม่ (เดิม sort ข้ามไปถ้าไม่มี zone data)

---

## [1.7.0] — 2026-05-08

### แก้ไขบัค
- `PS_RESOLVED = 4` — เพิ่ม ProcessStatus=4 (Resolved) เป็นสถานะ "เสร็จแล้ว" ใน JS แก้ปัญหา item ที่ถูก resolve ใน POS แสดงเป็น "กำลังทำ" แทน
- `buildRow` — ยกเลิก logic พิเศษสำหรับ `is_moved` ที่ทำให้รายการที่ย้ายโต๊ะแสดงเป็นสีเทา "🔄 ย้ายโต๊ะ" — ตอนนี้แสดงสถานะปกติ (กำลังทำ/เสร็จแล้ว)
- Modal summary counters — ลบ `!r.is_moved` filter ออก และเพิ่ม `PS_RESOLVED` ในการนับ nDone/nActive

---

## [1.6.0] — 2026-05-08

### แก้ไขบัค
- `nonKds(row, set)` — รวม logic ตรวจ non-KDS เป็นฟังก์ชันเดียว แทนที่จะมี `isNonKds` และ `isAutoD` แยกกัน 2 ที่
- ลบเงื่อนไข `pid > 0` ออกจาก non-KDS check — item ที่ `PrinterID = 0` (เช่น set menu parent) ก็ถูก auto-done ด้วยเมื่อไม่อยู่ใน allowed printer set
- Title bar `(X) Staff Display` — แก้ให้นับ pending ผ่าน `isNonKds` filter เหมือน grid card (เดิมตัวเลขสูงกว่าจริง)
- `openModal` — เพิ่ม `AbortController` (`_modalController`) ยกเลิก fetch เก่าทันทีเมื่อกดโต๊ะใหม่ก่อน response กลับมา ป้องกัน modal แสดงข้อมูลผิดโต๊ะ
- `fmtTime()` — fallback datetime ผ่าน `esc()` ป้องกัน raw string แสดงใน HTML

---

## [1.5.0] — 2026-05-08

### เพิ่มใหม่ / แก้ไข
- `list_table_orders` ส่ง `allowed_printer_ids` กลับมาใน response โดยตรง — modal ใช้ค่าจาก call เดียวกันแทนการพึ่ง `state.allowedPrinters` จาก `list_active` ที่อาจยังไม่ถูก populate
- `buildRow(row, printerSet)` รับ printerSet เป็น parameter — การ render modal ไม่ขึ้นกับ global state

### แก้ไขบัค
- Items จาก printer station อื่น แสดงเป็น "กำลังทำ" ใน modal แม้ grid card จะถูกแล้ว — เพราะ `buildRow` เดิมใช้ `state.allowedPrinters` ซึ่งอาจ null ขณะ modal render

---

## [1.4.0] — 2026-05-08

### เพิ่มใหม่
- Usage log รายวัน (`logs/usage-YYYY-MM-DD.log`) เก็บอัตโนมัติ 7 วัน
  - `PAGE_LOAD` — พนักงานเปิดหน้า staff_display (บันทึก cid + IP)
  - `TABLE_OPEN` — กดเข้าดูโต๊ะ (บันทึก table_id + cid + IP)
  - `ERROR` — exception ที่เกิดใน api_checker (บันทึก action + error message)
- `logs/web.config` — บล็อก IIS ไม่ให้เข้าถึงไฟล์ .log โดยตรง
- `.gitignore` — ไม่ commit log file และ settings.local.php

---

## [1.3.0] — 2026-05-08

### แก้ไข
- `web.config`: เพิ่ม `existingResponse="PassThrough"` — ป้องกัน IIS แทนที่ JSON error ของ PHP ด้วย HTML 500 ของตัวเอง
- `api_checker.php` (`fetchTableOrders`): เปลี่ยน fallback filter จาก `SubmitOrderDateTime >= NOW() - 24h` เป็น `OrderDate = CURDATE()` — สอดคล้องกับ `fetchActiveRows` และรองรับระบบ POS ที่ไม่ populate TransactionID

---

## [1.2.0] — 2026-05-02

### เพิ่มใหม่
- รองรับการแสดงผลหลาย Zone ผ่าน URL parameter `?cid=X`
  - `staff_display.php?cid=2` → Zone A
  - `staff_display.php?cid=3` → Zone B
  - ทุก Zone ใช้ฐานข้อมูลและโค้ดชุดเดียวกัน ไม่ต้องแยก folder
- เพิ่มฟังก์ชัน `getEffectiveComputerId()` ใน `api_checker.php`
  - อ่านค่า `?cid` จาก URL ก่อน แล้ว fallback ไปใช้ `CURRENT_COMPUTER_ID` จาก `settings.local.php`
- JS endpoints ใน `staff_display.php` ส่งต่อค่า `PAGE_CID` ให้ API request ถูก scope กับ zone ที่ถูกต้อง

---

## [1.1.0] — 2026-05-02

### เพิ่มใหม่
- `logo.svg` — ไอคอน clipboard + checkmark + ดินสอ ตามโทนสีของแอป
- `manifest.json` — PWA Manifest รองรับการ "Add to Home Screen" / ติดตั้งเป็นแอป
- เพิ่ม favicon, manifest meta tag, `apple-touch-icon`, `theme-color` และโลโก้ใน topbar ของ `staff_display.php`

---

## [1.0.0] — 2026-05-01

### เปิดตัวครั้งแรก
- `staff_display.php` — หน้าแสดงผลพนักงาน (ไฟล์หลัก)
- `api_checker.php` — Backend ตรวจสอบ API / ดึงข้อมูล
- `auth_check.php` — ตรวจสอบสิทธิ์การเข้าถึง
- `config.php` — ตั้งค่าระบบหลัก
- `settings.local.php` — ตั้งค่าเฉพาะเครื่อง (ไม่ commit)
- `web.config` — การตั้งค่า IIS
