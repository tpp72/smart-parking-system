# REQUIREMENT AUDIT — project-plan.md เทียบกับโค้ดจริง

> ⚠️ **SUPERSEDED (2026-09-16)** — เอกสารนี้เป็นผลตรวจก่อน Refactor Phase 1–16 ข้อมูลไม่ตรงกับโค้ดปัจจุบันแล้ว
> เก็บไว้เพื่อดูประวัติเท่านั้น · สถานะปัจจุบันดูที่ `docs/project-plan.md` (Source of Truth) และ `docs/TEST_COVERAGE.md`

> **วันที่ตรวจสอบ:** 2026-09-13
> **Source of Truth:** `docs/project-plan.md` (แก้ไขล่าสุด 2026-09-13 05:28 — 1,335 บรรทัด / 68 KB)
> **Branch:** `main` · **Commit:** `f2e78e5`
> **ผู้ตรวจ:** System Auditor (read-only)
> **วิธีตรวจ:** อ่าน source code ทั้ง repository + รันคำสั่ง read-only กับฐานข้อมูล PostgreSQL ที่ใช้งานจริง
> **หมายเหตุ:** การตรวจสอบนี้ **ไม่มีการแก้ไข code / database / config / migration / test ใดๆ** ทั้งสิ้น

---

## หมายเหตุก่อนอ่าน

1. **`/docs` อยู่ใน `.gitignore`** — ไฟล์นี้จะไม่ถูก track โดย git หากต้องการเก็บเข้า repo ต้องแก้ `.gitignore` หรือใช้ `git add -f`
2. เอกสารนี้**ต่างจาก** `docs/SYSTEM_AUDIT.md` — ฉบับนั้นตรวจ "ระบบปัจจุบันทำอะไรได้บ้าง" ส่วนฉบับนี้ตรวจ "ระบบปัจจุบันตรงกับ Requirement ใหม่หรือไม่"
3. ข้อสรุปแบ่งเป็น 2 ระดับ: **ยืนยันแล้ว** (รันจริงแล้วเห็นผล) และ **อ่านจาก code** (สรุปจาก source)
4. `project-plan.md` ฉบับนี้**ไม่ใช่การขยายความ requirement เดิม** แต่เป็นการ**เปลี่ยนกฎธุรกิจหลัก** โดย §27 ระบุรายการที่ถูกยกเลิกจากระบบเดิมไว้ 11 ข้อ

---

## สารบัญ

- [A. Executive Summary](#a-executive-summary)
- [B. Requirement Coverage Matrix](#b-requirement-coverage-matrix)
- [C. Core Flow Audit](#c-core-flow-audit)
- [D. Legacy / Conflict Report](#d-legacy--conflict-report)
- [E. Missing Requirements](#e-missing-requirements)
- [F. Database Audit](#f-database-audit)
- [G. Security / Permission Audit](#g-security--permission-audit)
- [H. Test Coverage Audit](#h-test-coverage-audit)
- [I. Final Assessment](#i-final-assessment)
- [ภาคผนวก: คำสั่งที่ใช้ตรวจสอบ](#ภาคผนวก-คำสั่งที่ใช้ตรวจสอบ)

---

# A. Executive Summary

## A.1 สัดส่วนความสอดคล้อง

| สถานะ | จำนวนกลุ่ม | สัดส่วน |
| ----- | ---------: | ------: |
| ✅ IMPLEMENTED | 6 | 21% |
| 🟡 PARTIAL | 7 | 25% |
| 🔴 CONFLICT | 9 | 32% |
| ❌ MISSING | 4 | 14% |
| ⚪ LEGACY / OUT OF SCOPE | 2 | 7% |
| **รวม** | **28 กลุ่ม** | **100%** |

## A.2 "ระบบปัจจุบันตรงกับ project-plan.md แค่ไหน?"

### ประมาณ 45%

และสิ่งที่ไม่ตรง**ไม่ได้กระจัดกระจาย** แต่**กระจุกอยู่ที่ Core Flow ทั้งหมด**

ข้อสรุปที่ตรงไปตรงมาคือ:

> **ชั้นรอบนอกใช้ต่อได้เกือบหมด แต่แกนกลางต้องรื้อ**

- **ใช้ต่อได้เลย:** Parking Lot/Slot CRUD · Blacklist · Notification infrastructure · Owner Application · Authentication · Audit Log · Scheduler · AI Vision integration
- **ต้องรื้อ:** Deposit · Walk-in · AI Accuracy · Slot Assignment · กฎเวลา — **ทั้ง 5 อย่างนี้คือ Core Flow**

## A.3 ประเด็นที่ต้องเน้นเป็นพิเศษ

`project-plan.md` §27 ระบุ "สิ่งที่ถูกยกเลิกจากระบบเดิม" ไว้ 11 ข้อ

ผลตรวจ: **8 จาก 11 ข้อยังมีอยู่ในโค้ดและยังทำงานอยู่จริง** ไม่ใช่แค่ค้างเป็น dead code
(อีก 3 ข้อคือ Promotion / Coupon / Discount Code ซึ่งไม่พบในระบบอยู่แล้ว)

## A.4 ประเด็นที่รุนแรงที่สุด 5 อันดับ

| อันดับ | ประเด็น | ทำไมถึงรุนแรง | สถานะ |
| :----: | ------- | ------------- | ----- |
| 1 | **Deposit ไม่มีอยู่จริง และ schema เก็บไม่ได้** | `payments.parking_log_id` เป็น NOT NULL + UNIQUE → Deposit ที่เกิดก่อน check-in ใส่ตารางไม่ได้เลย ไม่ใช่แค่ logic ขาด แต่ schema บล็อกอยู่ | ยืนยันแล้ว |
| 2 | **Walk-in ไม่สร้าง Reservation** | §11.1 กำหนดให้ Walk-in ใช้ Flow เดียวกับ Reservation แต่โค้ดสร้างแค่ ParkingLog — ตรงกับสิ่งที่ §27 ข้อ 9 สั่งยกเลิก | ยืนยันแล้ว |
| 3 | **AI Accuracy gate ไม่มีอยู่** | §10.3 กำหนด `> 85%` แต่ไม่มีเลข 85 อยู่ในโค้ดทั้งระบบ · `confidence` ถูกเก็บลง DB แล้วไม่เคยถูกอ่าน | ยืนยันแล้ว |
| 4 | **ทะเบียนไม่ตรง → บล็อก แทนที่จะเป็น Walk-in** | §10.7 สั่งให้ถือว่า "ไม่พบ Reservation" แล้วไป Walk-in แต่โค้ดถือเป็น error แล้วหยุด — ตรงข้ามกับ requirement | ยืนยันแล้ว |
| 5 | **Vehicle Entity ยังเป็นแกนของ query หลัก** | §27 ข้อ 1-2 สั่งยกเลิก แต่ dashboard/log ยังใช้ `INNER JOIN vehicles` 11 จุด | ยืนยันแล้ว |

---

# B. Requirement Coverage Matrix

> คอลัมน์ Evidence อ้างอิงไฟล์และบรรทัดจริงในโค้ด

| # | Requirement (อ้างอิง §) | Status | Implementation ที่พบ | Evidence | Problem |
| -: | ---------------------- | :----: | ------------------- | -------- | ------- |
| 1 | Authentication + Force Password Reset (§19.1) | ✅ | Breeze + custom middleware | `app/Http/Middleware/ForcePasswordReset.php`, `app/Http/Controllers/Auth/*` | ไม่ติดบน route group ของ admin |
| 2 | 3 Roles: Admin/Owner/User (§5) | ✅ | `users.role` + CHECK constraint | `database/migrations/0001_01_01_000000_create_users_table.php:20` | — |
| 3 | Parking Lot CRUD + `hourly_rate` + เปิด/ปิด (§6.1) | ✅ | Admin/Owner ParkingLotController | `app/Http/Controllers/Owner/ParkingLotController.php` | ไม่มี GPS (§3.2 future scope) |
| 4 | Parking Slot CRUD + Bulk Create (§6.2) | ✅ | `bulkCreate()` / `bulkStore()` 2 โหมด | `app/Http/Controllers/Admin/ParkingSlotController.php:141-216` | — |
| 5 | Slot 3 สถานะ `available`/`reserved`/`occupied` (§6.2) | ✅ | DB CHECK constraint | `database/migrations/2026_02_18_140455_create_parking_slots_table.php:29` | — |
| 6 | Owner Application + approve/reject + เหตุผล (§16) | ✅ | Owner/Admin ApplicationController | `app/Http/Controllers/Admin/OwnerApplicationController.php:47-120` | ข้อมูลที่กรอกไม่ถูกใช้สร้าง Lot |
| 7 | Reservation: กรอกข้อมูลรถตอนจอง (§7.1) | 🟡 | `User\ReservationController::store` | `app/Http/Controllers/User/ReservationController.php:44-56` | ครบ 4 ฟิลด์ แต่ยังรับ `parking_slot_id` |
| 8 | **จองล่วงหน้าไม่จำกัดวัน (§7.2)** | 🔴 | จำกัด 24 ชม. | `app/Http/Controllers/User/ReservationController.php:50` — `'before:' . now()->addDay()` | §27 ข้อ 7 สั่งยกเลิกแล้ว |
| 9 | ห้ามเลือกเวลาในอดีต (§7.2) | ✅ | `'after:now'` | `app/Http/Controllers/User/ReservationController.php:50` | — |
| 10 | **Check-in ภายใน 1 ชม. (§7.2, §23)** | 🔴 | grace period = **30 นาที** | `config/parking.php:9`, `.env:59` `RESERVATION_GRACE_PERIOD=30` | ค่าผิดครึ่งหนึ่ง |
| 11 | **ไม่มี Early Check-in 5 นาที (§27 ข้อ 8)** | 🔴 | ยังมี `addMinutes(5)` | `app/Models/Reservation.php:106` | §27 ข้อ 8 สั่งยกเลิกแล้ว |
| 12 | **Slot จัดอัตโนมัติ / User ห้ามเลือก (§6.3 ข้อ 1-2)** | 🔴 | มี dropdown ให้ผู้ใช้เลือก | `resources/views/user/reservations/create.blade.php:126-137` | §27 ข้อ 6 สั่งยกเลิกแล้ว |
| 13 | Slot Lock + Race Condition (§6.3 ข้อ 9, §24) | ✅ | `lockForUpdate()` ใน transaction | `app/Services/CheckInService.php:70-79` | ใช้เฉพาะตอน check-in |
| 14 | Cancel/Expire → คืน Slot (§6.3 ข้อ 5) | ✅ | คืนเฉพาะ slot ที่เป็น `reserved` | `app/Console/Commands/ExpireReservations.php:62-67` | — |
| 15 | **ห้ามลบ Slot ที่มีรถจอด (§6.3 ข้อ 8)** | ❌ | ไม่พบการตรวจสอบ | `app/Http/Controllers/Admin/ParkingSlotController.php:113` | ลบได้ทันที |
| 16 | **Deposit = `hourly_rate × 1` (§8.1)** | 🔴 | `reservation_fee = hourly_rate` | `app/Http/Controllers/User/ReservationController.php:127` | ใช้ฟิลด์ผิดตัว (ดู #18) |
| 17 | **Deposit เป็น Payment จริง + ใช้ยืนยันการจอง (§8.2, §13.1)** | ❌ | **ไม่มี** | `Payment::create` มีที่เดียวคือ `app/Services/CheckOutService.php:50` | ไม่มี payment ตอนจองเลย |
| 18 | **`reservation_fee` = ส่วนลด แยกจาก Deposit (§9)** | 🔴 | ถูกใช้เป็น Deposit | `app/Services/CheckOutService.php:38` — `min($reservation_fee, $parkingFee)` | สองแนวคิดถูกรวมเป็นฟิลด์เดียว |
| 19 | Cancel ไม่คืน Deposit (§7.4, §8.3) | 🟡 | ยกเลิกได้ + คืน slot | `app/Http/Controllers/User/ReservationController.php:228-262` | ไม่มี Deposit ให้คืนอยู่แล้ว |
| 20 | Auto-Expire Scheduler (§23) | ✅ | `reservations:expire` ทุกนาที | `routes/console.php:7`, `app/Console/Commands/ExpireReservations.php` | ใช้ค่า 30 นาที (ดู #10) |
| 21 | AI อ่าน ทะเบียน/จังหวัด/ยี่ห้อ/สี/Accuracy (§10.2) | ✅ | Claude Vision prompt | `app/Services/CarScanService.php:50-88` | อ่านครบทั้ง 5 ค่า |
| 22 | **Accuracy > 85% จึงผ่าน (§10.3)** | ❌ | เก็บค่าแต่**ไม่เคยเทียบ** | `app/Services/CarScanService.php:160, 189` เท่านั้น | ไม่มีเลข 85 ในโค้ดทั้งระบบ |
| 23 | **AI อ่านทะเบียนไม่ได้ → แจ้ง Owner+Admin (§10.4)** | ❌ | `if ($scan->license_plate)` แล้วจบ | `app/Http/Controllers/CarScanController.php:84` | เงียบสนิท ไม่แจ้ง ไม่บันทึก |
| 24 | AI Matching: ทะเบียน+จังหวัด AND (ยี่ห้อ OR สี) (§10.6) | ✅ | `matchScanAgainstReservation()` | `app/Services/CarScanService.php:254-280` | Logic ตรงตาม requirement เป๊ะ |
| 25 | **ทะเบียนไม่ตรง → Walk-in (§10.7)** | 🔴 | บล็อก + แสดง error | `app/Http/Controllers/CarScanController.php:106-113` | หยุดกระบวนการ ไม่ fall through |
| 26 | **ตรวจว่า Reservation อยู่ใน Lot ที่ check-in (§10.8)** | 🔴 | ไม่ตรวจ | `app/Services/CarScanService.php:203` — `findMatchingReservation(string $licensePlate)` ไม่รับ lot | ข้ามลานได้ |
| 27 | **Walk-in → สร้าง Reservation (§11.1)** | 🔴 | สร้างแค่ ParkingLog | `app/Http/Controllers/CarScanController.php:178` `attemptWalkInCheckIn()` | §27 ข้อ 9 สั่งยกเลิกแล้ว |
| 28 | **`Walkin User` (§3.1, §11.1)** | ❌ | **ไม่มีทั้งใน code และ DB** | grep ทั้ง repo + query `users` table | — |
| 29 | Blacklist: ไม่ Block + แจ้ง Admin+Owner (§10.9, §14.1) | ✅ | `notifySuspiciousVehicle()` | `app/Http/Controllers/CarScanController.php:211-231` | ตรงตาม requirement |
| 30 | **Blacklist เก็บจังหวัด (§14)** | ❌ | ไม่มีคอลัมน์ | `information_schema` — `suspicious_vehicles` มี 8 คอลัมน์ ไม่มี `province` | — |
| 31 | Checkout คำนวณค่าจอด + snapshot rate (§12.1-12.2) | ✅ | `CheckOutService` | `app/Services/CheckOutService.php:32-36` | — |
| 32 | **หัก Deposit แล้วหัก `reservation_fee` ต่อ (§12.3)** | 🔴 | หักตัวเดียว | `app/Services/CheckOutService.php:38-39` | ต้องเป็น 2 ตัวแยกกัน |
| 33 | ยอดสุทธิไม่ติดลบ (§12.3) | ✅ | `min()` guard | `app/Services/CheckOutService.php:38` | — |
| 34 | Walk-in Checkout ไม่หัก Deposit (§11.3) | 🟡 | ไม่หัก (เพราะไม่มี reservation ผูก) | `app/Services/CheckOutService.php:36` | ผลลัพธ์ถูกโดยบังเอิญ |
| 35 | Payment: `unpaid`/`paid` (§20) | ✅ | `payments.payment_status` | `database/migrations/2026_02_18_140459_create_payments_table.php:24` | ไม่มี CHECK constraint |
| 36 | Notification `unread`/`read` (§15) | ✅ | `notifications.is_read` | `app/Http/Controllers/NotificationController.php` | — |
| 37 | **Notification: Owner รับแจ้ง Reservation ใหม่ (§15.2)** | ❌ | ไม่มี | `app/Http/Controllers/User/ReservationController.php:128-140` ไม่เรียก `notify_user` | — |
| 38 | **Notification: AI accuracy ต่ำ / อ่านไม่ได้ (§15.2-15.3)** | ❌ | ไม่มี | ไม่พบใน 21 จุดที่เรียก `notify_user` ทั้งระบบ | — |
| 39 | Audit Log (§18) | 🟡 | `admin_actions` + index ครบ | `app/Support/admin_audit.php` | Owner ไม่ถูกบันทึกเลย |
| 40 | Dashboard 3 ระดับ (§17) | 🟡 | มีครบ 3 + Chart.js | `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/Owner/DashboardController.php` | `INNER JOIN vehicles` ทำข้อมูลหาย |
| 41 | Reports + CSV Export (§17.4) | 🟡 | 2 export endpoints | `app/Http/Controllers/Admin/ReservationLogController.php:64` | ซ่อนข้อมูล 62% |
| 42 | **Marketplace + GPS (§3.2 — Future)** | ⚪ | มีหน้า ไม่มี GPS | `app/Http/Controllers/MarketplaceController.php` | Future scope — ไม่นับเป็นข้อบกพร่องรอบนี้ |
| 43 | **Vehicle Entity (§27 ข้อ 1-2 — ยกเลิก)** | ⚪ | ยังอยู่ครบ + ยังใช้งานอยู่ | `app/Models/Vehicle.php`, `admin.vehicles.*` (6 routes) | ต้องเอาออก |

---

# C. Core Flow Audit

## C.1 Reservation

```text
Requirement (§7.1, §28.1)
  เลือก Lot → เลือก reserve_start → กรอกทะเบียน/จังหวัด/ยี่ห้อ/สี
  → ระบบคำนวณ Deposit → ชำระ Deposit → Reservation Confirmed → Slot ถูก Lock
        ↓
Current Code (User\ReservationController::store)
  เลือก Lot ✅
  → เลือก reserve_start แต่ถูกจำกัดไว้ 24 ชม. ❌
  → กรอกครบ 4 ฟิลด์ ✅
  → ผู้ใช้เลือก Slot เองได้ ❌
  → สร้าง status='pending' + set reservation_fee = hourly_rate
  → รอ Admin/Owner กดยืนยันด้วยมือ ❌ (ไม่เกี่ยวกับการชำระเงิน)
        ↓
🔴 ไม่ตรง — 3 จุด
        ↓
จุดที่พบ
  • [#8]  User/ReservationController.php:50 — จำกัด 24 ชม.
  • [#12] create.blade.php:126-137 — dropdown เลือก slot
  • [#17] Admin/ReservationController.php:286 — confirm ด้วยมือ ไม่ผูกกับ payment
```

## C.2 Deposit — ไม่ตรงรุนแรงที่สุด

```text
Requirement (§8, §9, §13.1)
  Deposit = hourly_rate × 1  → "เงินที่ชำระเพื่อยืนยันการจอง"
  reservation_fee            → "ส่วนลด"  ← คนละตัวกัน
  Checkout: ค่าจอดจริง − Deposit − reservation_fee = ยอดสุทธิ
        ↓
Current Code
  User/ReservationController.php:127 — reservation_fee = hourly_rate
  CheckOutService.php:38             — $deposit = min($reservation->reservation_fee, $parkingFee)
  → ใช้ reservation_fee "เป็น" Deposit แล้วหักออกครั้งเดียว
  → ไม่มี Payment record ตอนจองเลย
        ↓
🔴 ไม่ตรง — ผิดทั้งเชิงแนวคิดและเชิง schema
        ↓
จุดที่พบ (หลักฐานเชิงโครงสร้าง)
  payments.parking_log_id : NOT NULL + UNIQUE
  → Deposit เกิดขึ้น "ก่อน" มี parking_log
  → ตารางนี้เก็บ Deposit payment ไม่ได้เลยในทางกายภาพ
  → ไม่ใช่แค่ logic ที่ยังไม่ได้เขียน แต่ schema บล็อกอยู่
```

**ผลกระทบเชิงตัวเลข (ยืนยันจาก DB):**

```text
sum(parking_fee)          = ฿18,790.00
sum(reservation_discount) = ฿   680.00   ← ถูกหักออกจากบิลโดยไม่เคยมีการรับเงิน
sum(total_amount)         = ฿18,110.00
payment rows ที่ไม่มี parking_log = 0     ← ไม่มี deposit payment เลย

reservations ที่ expired/cancelled และมี reservation_fee > 0
  = 46 รายการ รวม ฿1,095.00
  → payment record ที่เกิดจากรายการเหล่านี้ = 0
```

§8.3 ระบุว่า "ยกเลิกแล้วไม่คืน Deposit" — แต่ต้อง**เก็บเงินก่อน**ถึงจะ "ไม่คืน" ได้

## C.3 Reservation Expire

```text
Requirement (§7.2, §23): reserve_start + 1 ชั่วโมง
        ↓
Current Code: config('parking.grace_period') = 30 นาที
  Reservation.php:107         → now()->subMinutes(30)
  ExpireReservations.php:20   → subMinutes(30)
        ↓
🟡 กลไกถูกต้อง แต่ค่าผิด
        ↓
จุดที่พบ
  • โครงสร้างครบถ้วน: scheduler ทุกนาที · transaction · คืน slot เฉพาะ 'reserved'
    · notify user · เขียน ReservationLog · มี --dry-run  ✅
  • ต้องแก้ค่าเดียวคือ .env:59  RESERVATION_GRACE_PERIOD=30 → 60
  • §23 ข้อ 3 "ป้องกัน Reservation หมดอายุถูก Check-in" → scopeCheckable() กันไว้แล้ว ✅
```

## C.4 Cancel Reservation

```text
Requirement (§7.4): ยกเลิกได้ก่อน Check-in / ไม่คืน Deposit / คืน Slot
        ↓
Current Code (User/ReservationController::cancel:228)
  อนุญาตเฉพาะ pending|confirmed        ✅
  คืน slot ที่สถานะ 'reserved'          ✅
  เขียน ReservationLog + notify        ✅
  ownership guard abort_unless()       ✅
        ↓
🟡 ตรงเท่าที่ทำได้ในสภาพปัจจุบัน
        ↓
จุดที่พบ
  "ไม่คืน Deposit" ยังพิสูจน์ไม่ได้ เพราะไม่มี Deposit payment ให้คืนตั้งแต่แรก
  เมื่อสร้างระบบ Deposit จริงแล้ว ต้องกลับมาตรวจข้อนี้ใหม่
```

## C.5 AI Scan

```text
Requirement (§10.2-10.4)
  อ่าน 5 ค่า → ตรวจ Accuracy > 85% → ถ้าไม่ผ่านแจ้ง Owner+Admin
  ถ้าอ่านทะเบียนไม่ได้ → แจ้ง Owner+Admin + บันทึกเหตุการณ์
        ↓
Current Code (CarScanService::detect + scanAndSave)
  prompt ขอครบทั้ง 5 ค่า                     ✅
  เก็บ confidence ลง DB                      ✅
  แต่ confidence ปรากฏแค่ 2 บรรทัด:
    บรรทัด 160 — cast เป็น float
    บรรทัด 189 — insert ลง DB
  ไม่มีเลข 85 อยู่ในโค้ดทั้งระบบ
        ↓
🔴 ไม่ตรง — gate ที่ requirement กำหนดไม่มีอยู่จริง
        ↓
จุดที่พบ
  • [#22] ไม่มีการเทียบ threshold
          → รถที่ AI มั่นใจ 20% ผ่านเท่ากับรถที่มั่นใจ 99%
  • [#23] CarScanController.php:84 — `if ($scan->license_plate)`
          ถ้า AI อ่านทะเบียนไม่ได้ จะข้ามทั้งบล็อกแบบเงียบๆ
          ไม่แจ้งใคร ไม่บันทึกเหตุการณ์ ไม่เข้า walk-in
```

## C.6 Auto Check-in

```text
Requirement (§10.2, §10.7, §10.8)
  หา Reservation → ตรวจว่าอยู่ใน Lot ที่กำลังรับรถ
  → ตรงก็ Auto Check-in
  → ไม่ตรง / ไม่พบ → Walk-in
        ↓
Current Code (CarScanController::store:84-160)
  findMatchingReservation($plate)   ← ไม่รับพารามิเตอร์ lot เลย
  → ถ้า match ไม่ผ่าน: แสดง error "กรุณาให้เจ้าหน้าที่ตรวจสอบก่อนเช็คอิน" แล้วจบ
  → checkIn(..., $reservation->parking_lot_id, null, ...)   ← allowedLotIds = null
        ↓
🔴 ไม่ตรง — 2 จุดใหญ่
        ↓
จุดที่พบ
  • [#25] §10.7 สั่งว่า "ทะเบียนไม่ตรง = ไม่พบ Reservation → Walk-in"
          แต่โค้ดถือเป็น error แล้วหยุด — ตรงข้ามกับ requirement
  • [#26] §10.8 สั่งให้ตรวจ Lot scope
          แต่โค้ดใช้ lot ของ reservation แทน lot ที่สแกน
          → สแกนที่ลาน A ทำให้รถ check-in เข้าลาน B ได้
```

## C.7 Walk-in — ไม่ตรงเชิงโครงสร้าง

```text
Requirement (§11.1, §28.2, §27 ข้อ 9)
  ไม่พบ Reservation → สร้าง Reservation ใหม่
  → user = Walkin User
  → reserve_start = เวลาปัจจุบัน
  → Deposit = 0
  → ระบบจัด Slot อัตโนมัติ
  → Auto Check-in
  → ใช้ Flow เดียวกับ Reservation ปกติ
        ↓
Current Code (CarScanController::attemptWalkInCheckIn:178)
  เรียก CheckInService::checkIn() ตรงๆ
  → สร้างเฉพาะ ParkingLog ที่ reservation_id = NULL
  → ไม่มี Reservation · ไม่มี Walkin User · ไม่มีสถานะ
        ↓
🔴 ไม่ตรง
   นี่คือสิ่งที่ §27 ข้อ 9 เรียกว่า
   "Walk-in แบบแยก Parking Log โดยไม่มี Reservation" ซึ่งถูกสั่งยกเลิกโดยตรง
        ↓
จุดที่พบ
  • ไม่มี Reservation::create ใน walk-in flow เลย (grep ยืนยัน)
  • ไม่มี user ชื่อ Walkin ทั้งใน code และ DB (query ยืนยัน)
  • ผลลัพธ์ต่อเนื่อง:
      Walk-in ไม่ปรากฏในหน้า "การจอง"
      ไม่มี ReservationLog
      ไม่มีสถานะ completed
      → ต้องใช้หน้า "ประวัติการจอด" แยกต่างหากเพื่อเช็คเอาท์
      → เกิด dual-flow ซึ่งเป็นสิ่งที่ requirement ใหม่ต้องการกำจัด
```

## C.8 Slot Allocation

```text
Requirement (§6.3): User เลือกได้เฉพาะ Lot / ระบบเป็นผู้จัดสรร Slot
        ↓
Current Code
  ตอนจอง      : user เลือก slot เองได้ (UI dropdown + validate + conflict check)  ❌
  ตอน check-in : CheckInService.php:70-88 หยิบ slot อัตโนมัติ                     ✅
                 (ลอง slot ที่จองไว้ก่อน → ถ้าไม่ได้ หยิบตัวว่างใดก็ได้ในลาน)
        ↓
🔴 ไม่ตรงครึ่งหนึ่ง
        ↓
จุดที่พบ
  • [#12] ขั้นตอนจองยังให้ผู้ใช้เลือกเอง — §27 ข้อ 6 สั่งยกเลิก
  • [#15] ลบ slot ที่ occupied ได้ — §6.3 ข้อ 8 ห้ามไว้
  • ข้อดี: กลไก lockForUpdate + transaction พร้อมใช้อยู่แล้ว (§24 ✅)
```

## C.9 Blacklist — ตรงตาม Requirement

```text
Requirement (§10.9, §14.1): ไม่ Block + แจ้ง Admin+Owner + บันทึก Log
        ↓
Current Code (CarScanController::notifySuspiciousVehicle:211)
  ตรวจทั้งกิ่ง reservation และกิ่ง walk-in
  แจ้ง owner ของลาน + admin ทุกคน พร้อมรายละเอียดรถ/ลาน/เวลา
  ไม่บล็อกการ check-in
        ↓
✅ ตรง
        ↓
ขาดเพียงข้อเดียว: [#30] คอลัมน์ province ตาม §14
```

## C.10 Checkout

```text
Requirement (§12.3): ค่าจอดจริง − Deposit − reservation_fee = ยอดสุทธิ (ไม่ติดลบ)
        ↓
Current Code (CheckOutService::checkOut:32-45)
  $parkingFee = ceil(นาที / 60) × hourly_rate     (ขั้นต่ำ 1 ชม.)  ✅
  $deposit    = min($reservation->reservation_fee, $parkingFee)    ← ตัวเดียว
  $total      = $parkingFee − $deposit                              ← หักครั้งเดียว
        ↓
🔴 ไม่ตรง — requirement ต้องการหัก 2 ตัวแยกกัน
        ↓
จุดที่พบ
  • ส่วนที่ตรงแล้ว:
      snapshot hourly_rate ✅ / ยอดไม่ติดลบ ✅ / คืน slot ✅
      / reservation → completed ✅ / notify ✅ / อยู่ใน transaction ✅
  • ส่วนที่ต้องแก้:
      แยก Deposit ออกจาก reservation_fee เป็น 2 ฟิลด์ แล้วหักตามลำดับ
```

## C.11 Payment

```text
Requirement (§13): บันทึก 2 อย่าง — Deposit ตอนจอง + ยอดคงเหลือหลัง Checkout
        ↓
Current Code
  Payment::create เกิดที่เดียว: CheckOutService.php:50
  payments.parking_log_id : NOT NULL + UNIQUE
        ↓
🔴 ไม่ตรง — มีแค่ครึ่งเดียว และอีกครึ่งใส่ไม่ได้
        ↓
จุดที่พบ
  • Deposit payment ไม่มี และ schema ปัจจุบันรองรับไม่ได้
  • ต้องแก้ schema ก่อน จึงจะทำ requirement §13.1 ได้
```

---

# D. Legacy / Conflict Report

## D.1 ตรวจ §27 "สิ่งที่ถูกยกเลิกจากระบบเดิม" ทีละข้อ

| §27 | รายการที่สั่งยกเลิก | ยังอยู่? | Location | สถานะ |
| :-: | ------------------ | :------: | -------- | ----- |
| 1 | Vehicle Registration / Vehicle Entity | **ยังอยู่** | `app/Models/Vehicle.php`, `vehicles` table (40 rows), `admin.vehicles.*` (6 routes + controller + 3 views) | 🔴 Conflict |
| 2 | พึ่งพา `vehicle_id` เป็นข้อมูลหลัก | **ยังอยู่** | `reservations.vehicle_id`, `parking_logs.vehicle_id`, `license_plate_scans.vehicle_id`, INNER JOIN 11 จุด | 🔴 Conflict |
| 3 | Promotion | ไม่พบ | — | ✅ สะอาด |
| 4 | Coupon | ไม่พบ | — | ✅ สะอาด |
| 5 | Discount Code | ไม่พบ | — | ✅ สะอาด |
| 6 | User เลือก Slot เอง | **ยังอยู่** | `resources/views/user/reservations/create.blade.php:126-137`, `User/ReservationController.php:49, 92-105` | 🔴 Conflict |
| 7 | จำกัดจองล่วงหน้า 24 ชม. | **ยังอยู่** | `app/Http/Controllers/User/ReservationController.php:50` | 🔴 Conflict |
| 8 | Early Check-in 5 นาที | **ยังอยู่** | `app/Models/Reservation.php:106` | 🔴 Conflict |
| 9 | Walk-in แยก ParkingLog ไม่มี Reservation | **ยังอยู่** | `app/Http/Controllers/CarScanController.php:178` | 🔴 Conflict |
| 10 | Deposit ที่ไม่ตรง requirement ใหม่ | **ยังอยู่** | `app/Services/CheckOutService.php:38` | 🔴 Conflict |
| 11 | Flow อื่นที่ถูกแทนที่ | **ยังอยู่** | `admin.reservations.create/store` (บังคับ `vehicle_id`) | 🔴 Conflict |

**สรุป: 8 จาก 11 ข้อยังมีอยู่และยังทำงานอยู่จริง**

## D.2 Legacy ที่ยังทำให้ระบบใช้ Flow เก่าได้

| Legacy | Location | สถานะ | ผลกระทบ |
| ------ | -------- | ----- | ------- |
| `admin.reservations.create` / `store` | `app/Http/Controllers/Admin/ReservationController.php:95-168` | 🔴 Conflict | route เปิดอยู่ · บังคับ `vehicle_id` · ไม่ set `license_plate`/`plate_province`/`brand`/`color` → สร้างแล้ว check-in ไม่ได้ (TypeError 500) และ AI matching ไม่ผ่านแน่นอน |
| `User\VehicleController` | `app/Http/Controllers/User/VehicleController.php` | ⚪ Legacy | ไม่มี route · import ค้างอยู่ที่ `routes/web.php:6` |
| `user/vehicles/*.blade.php` | `resources/views/user/vehicles/` (2 ไฟล์) | ⚪ Legacy | อ้าง route ที่ไม่มีจริง 4 ตัว → `RouteNotFoundException` ถ้าถูก render |
| `Vehicle::reservations()` relation | `app/Models/Vehicle.php:19` | ⚪ Legacy | flow ใหม่ไม่ set `vehicle_id` |
| duplicate guard `whereHas('vehicle')` | `app/Http/Controllers/User/ReservationController.php:85-87` | 🔴 Conflict | dead code — ไม่เคยเป็นจริง → guard ไม่ทำงาน |
| `App\Models\Role` / `App\Models\Permission` | `app/Models/` | ⚪ Legacy | ไม่มี table ไม่มี migration ไม่มีการอ้างอิงเลย |
| `hasSlotConflict()` | `User/ReservationController.php:266`, `Admin/ReservationController.php:409` | 🔴 Conflict | รองรับการเลือก slot เอง ซึ่ง §27 ข้อ 6 ยกเลิกแล้ว |
| Marketplace (ไม่มี GPS) | `app/Http/Controllers/MarketplaceController.php` | ⚪ Future | §3.2 จัดเป็น future scope — **ไม่นับเป็นข้อบกพร่องรอบนี้** แต่ยังไม่มีลิงก์เข้าถึงและไม่มี GPS |

---

# E. Missing Requirements

| # | Requirement | อ้างอิง | สิ่งที่ไม่พบ |
| -: | ---------- | ------ | ----------- |
| 1 | **Deposit Payment** | §8.2, §13.1 | ไม่มี Payment record ตอนจอง · `payments.parking_log_id` NOT NULL บล็อกอยู่ |
| 2 | **AI Accuracy Threshold > 85%** | §10.3, §28.3 | ไม่มีเลข 85 ในโค้ดทั้งระบบ · `confidence` เก็บแล้วไม่เคยถูกอ่าน |
| 3 | **แจ้ง Owner+Admin เมื่อ AI อ่านทะเบียนไม่ได้** | §10.4, §15.2-15.3 | `CarScanController.php:84` ข้ามแบบเงียบๆ |
| 4 | **แจ้ง Owner+Admin เมื่อ Accuracy ต่ำ** | §15.2-15.3, §28.3 | ไม่พบใน 21 จุดที่เรียก `notify_user` |
| 5 | **`Walkin User`** | §3.1, §11.1 | ไม่มีทั้งใน code และ DB |
| 6 | **Walk-in สร้าง Reservation** | §11.1, §28.2 | ไม่มี `Reservation::create` ใน walk-in flow |
| 7 | **Blacklist เก็บจังหวัด** | §14 | `suspicious_vehicles` มี 8 คอลัมน์ ไม่มี `province` |
| 8 | **แจ้ง Owner เมื่อมี Reservation ใหม่ในลาน** | §15.2 | `User/ReservationController::store` ไม่เรียก `notify_user` |
| 9 | **ห้ามลบ Slot ที่มีรถจอด** | §6.3 ข้อ 8 | ไม่มีการตรวจใน `destroy()` ทั้ง Admin และ Owner |
| 10 | **Owner Audit Log** | §18 | Owner ทำ confirm / check-in / check-out / mark-paid / ลบลาน โดยไม่ถูกบันทึก |

---

# F. Database Audit

## F.1 ตรงกับ Requirement ใหม่แล้ว

| Table / Column | หมายเหตุ |
| -------------- | -------- |
| `reservations.license_plate` / `plate_province` / `brand` / `color` | ครบตาม §4 Plate-based ✅ |
| `parking_slots.status` | CHECK `available`/`reserved`/`occupied` ตรง §6.2 ✅ |
| `parking_lots` | ครบตาม §6.1 (ชื่อ/ที่อยู่/landmark/`hourly_rate`/`is_active`/`reservations_enabled`/`owner_id`) ✅ |
| `payments.hourly_rate` | snapshot ตาม §12.2 ✅ |
| `notifications.is_read` | ตาม §15 ✅ |
| `admin_actions` | actor/action/target/time/meta ครบตาม §18 ✅ (index ครบด้วย) |
| `reservations.status` (6 ค่า) | ตรงกับ §7.3 ทุกค่า ✅ (แต่ไม่มี CHECK constraint) |

## F.2 ไม่ตรง / ขาด

| ประเด็น | Table | ปัญหา | อ้างอิง |
| ------- | ----- | ----- | ------- |
| 🔴 **Deposit เก็บไม่ได้** | `payments` | `parking_log_id` **NOT NULL + UNIQUE** → Deposit ที่เกิดก่อน check-in ใส่ไม่ได้ | §13.1 |
| 🔴 **ไม่มีฟิลด์ Deposit** | `reservations` / `payments` | มีแค่ `reservation_fee` + `reservation_discount` ซึ่ง requirement ใหม่นิยามว่าเป็น "ส่วนลด" | §8, §9 |
| 🔴 **`vehicle_id` ยังอยู่** | `reservations`, `parking_logs`, `license_plate_scans` | §27 ข้อ 2 สั่งเลิกพึ่งพา | §27 |
| 🔴 **`vehicles` ทั้งตาราง** | `vehicles` (40 rows) | §27 ข้อ 1 สั่งยกเลิก Vehicle Entity | §27 |
| ❌ **ขาด `province`** | `suspicious_vehicles` | §14 ระบุให้เก็บจังหวัด | §14 |
| ❌ **ไม่มี Walkin User** | `users` | §11.1 ต้องมี identity นี้ | §11.1 |
| 🟡 **ไม่มี CHECK constraint** | `reservations.status`, `payments.payment_status`, `suspicious_vehicles.level`, `owner_applications.status` | ขณะที่ `users.role` และ `parking_slots.status` มี → ไม่สอดคล้องกัน | §20 |
| 🟡 **ขาด index** | `parking_lots.owner_id` · `parking_slots.parking_lot_id` · `parking_logs.license_plate` · `parking_logs.check_out_time` · `reservations.parking_lot_id/user_id/reserve_start` · `reservation_logs.*` | ทุก query หลักใช้คอลัมน์เหล่านี้ | — |
| 🟡 **ไม่มี UNIQUE** | `parking_slots (parking_lot_id, slot_number)` | บังคับแค่ระดับ application → race condition | §24 |
| 🟡 **FK RESTRICT** | `parking_logs.parking_lot_id`, `parking_logs.vehicle_id` | ลบลานที่มีประวัติ = FK error 500 | — |
| ⚪ **ไม่มี GPS** | `parking_lots` | ไม่มี `latitude`/`longitude` — จำเป็นเมื่อเริ่ม Marketplace | §3.2 (future) |

## F.3 สถานะข้อมูลปัจจุบัน (ยืนยันจาก DB)

```text
reservations (84 rows)
  expired 38 · completed 29 · checked_in 9 · cancelled 8
  pending 0 · confirmed 0

vehicle_id NOT NULL : 74 rows | 2026-06-21 .. 2026-07-19   ← ยุคเก่า (Vehicle-based)
vehicle_id IS NULL  : 10 rows | 2026-07-17 .. 2026-08-13   ← ยุคใหม่ (Plate-based)

plate_province NULL : 78 / 84
brand          NULL : 78 / 84
color          NULL : 78 / 84
→ ข้อมูลเดิมส่วนใหญ่ไม่มีฟิลด์ที่ requirement ใหม่ต้องใช้ทำ AI Matching (§10.6)
```

---

# G. Security / Permission Audit

> แยกจาก Requirement mismatch ตามที่กำหนดในข้อ 24

| # | ประเด็น | ระดับ | หลักฐาน | อ้างอิง requirement |
| -: | ------ | :---: | ------- | ------------------- |
| 1 | **User สแกน/check-in เข้าลานของ Owner รายอื่นได้** | 🔴 สูง | `CarScanController::authorizedLots()` — role `user` → `ParkingLot::reservable()` = **ทั้ง 8 ลาน** (รวมลานของ owner 6 ลาน) | §19.3 "ป้องกันการเข้าถึง Lot ของ Owner รายอื่น" |
| 2 | **AI Check-in ข้าม Lot ได้** | 🔴 สูง | `CarScanController.php:139` ส่ง `allowedLotIds = null` + `findMatchingReservation()` ไม่ผูกกับผู้สแกน/ลาน | §10.8, §26 "Reservation ข้าม Lot" |
| 3 | **SSL verification ปิดถาวร** | 🔴 สูง | `CarScanService.php:23` — `new GuzzleClient(['verify' => false])` hardcode ไม่ผูกกับ `APP_ENV` | §19.3 "ใช้ SSL/TLS ที่เหมาะสม" |
| 4 | **DB password ใน source code** | 🔴 สูง | `phpunit.xml:27` — `<env name="DB_PASSWORD" value="***"/>` ถูก commit เข้า git | §19.3 "ไม่เก็บ Secret ไว้ใน Source Code" |
| 5 | **เอกสารสมัคร Owner เปิดสาธารณะ** | 🔴 สูง | `Owner\ApplicationController` เก็บลง disk `public` (`visibility: public`) → เข้าถึงผ่าน `/storage/...` โดยไม่ต้อง login | §19.3 "ป้องกันการเข้าถึงไฟล์/เอกสารที่ไม่ควรเปิดสาธารณะ" |
| 6 | **Email verification เป็น no-op** | 🔴 สูง | `User` ไม่ implement `MustVerifyEmail` (`app/Models/User.php:5` ถูก comment ไว้) → middleware `verified` ผ่านตลอด | §19.1 |
| 7 | รูปสแกนป้ายทะเบียนเปิดสาธารณะ | 🟡 กลาง | `car-scans` บน public disk | §19.3 |
| 8 | `e2e/.auth/*.json` (session token) ถูก commit | 🟡 กลาง | 3 ไฟล์ใน repo | §19.3 |
| 9 | `force.password.reset` ไม่ครอบ admin | 🟡 กลาง | `routes/web.php:46` ขาด middleware ตัวนี้ | §19.1 |
| 10 | ไม่มี rate limit บน AI scan | 🟡 กลาง | ทุก role เรียก API ได้ไม่จำกัด | §26 "AI API มีปัญหา/Quota" |
| 11 | `storage/{path}` route ไม่มี middleware | 🟡 กลาง | Laravel 12 auto-register | §19.3 |
| 12 | `APP_DEBUG=true` | 🟡 กลาง | `.env` | — |

## G.1 ส่วนที่ผ่าน §19.2 แล้ว

- Ownership guard ครบทุก resource — `abort_unless($x->user_id === Auth::id(), 403)`
- Owner scope ผ่าน `ParkingLot::ownedBy()` · Admin scope ผ่าน `ParkingLot::unowned()`
- ป้องกัน ID enumeration ใน `admin/*` (exception handler ใน `bootstrap/app.php`)
- CSRF protection ทุกฟอร์ม (Laravel default)
- SQL parameter binding ทุกจุด รวม `whereRaw` ที่ใช้ `?` placeholder ถูกต้อง
- XSS — Blade `{{ }}` escape อัตโนมัติ
- Login rate limit 5 ครั้ง · bcrypt 12 rounds
- Admin เปลี่ยน role ตัวเองออกจาก admin ไม่ได้ / ลบบัญชีตัวเองไม่ได้
- File upload validate `mimes` + `max:5120` + ใช้ `store()` (random filename)

---

# H. Test Coverage Audit

**ผลรันจริง: `119 passed, 7 failed` (324 assertions, 33.46s)**

## H.1 Test ที่สะท้อน Requirement ใหม่

| Test | ครอบคลุม | หมายเหตุ |
| ---- | -------- | -------- |
| `AuthenticationTest`, `RegistrationTest`, `PasswordUpdateTest` | §25.1 Authentication | ✅ ใช้ต่อได้ |
| `ExpireReservationsTest` | §25.1 Reservation Time (บางส่วน) | 🟡 ทดสอบกลไกถูก แต่ยึดค่า 30 นาที |
| `SuspiciousVehicleBlacklistTest` | §25.1 Blacklist | 🟡 ยัง import `Vehicle` |
| `SlotReservationLifecycleTest` | §25.1 Slot Lock | 🟡 ยัง import `Vehicle` |

## H.2 Test ที่ยังเป็นของ Requirement เก่า

| Test | ปัญหา |
| ---- | ----- |
| `ReservationTest` (**5 failed**) | ส่ง `vehicle_id` ตาม API เก่า → error `"กรุณากรอกเลขทะเบียนรถ / กรุณาเลือกจังหวัด / กรุณากรอกยี่ห้อรถ / กรุณาเลือกสีรถ"` |
| `ReservationDepositTest` (**1 failed**) | **ทดสอบว่า `reservation_fee == hourly_rate`** — เข้ารหัสแนวคิดเก่าที่รวม Deposit กับส่วนลดเป็นตัวเดียว ขัดกับ §8/§9 โดยตรง |
| `LotReservationsEnabledTest` (**1 failed**) | ส่ง `vehicle_id` |
| `CheckInTest`, `CheckOutTest`, `OcrCheckInTest`, `ReservationCheckInIntegrationTest` | **ผ่าน** แต่สร้างข้อมูลผ่าน factory ที่มี `vehicle_id` และไม่มี `license_plate` → ทดสอบกับ data shape ที่ระบบจริงไม่ผลิตแล้ว = **false confidence** |
| **14 จาก 24 test files** | ยัง import / ใช้ `Vehicle` |
| **4 จาก 7 factories** | `ReservationFactory` และ `ParkingLogFactory` ตั้ง `vehicle_id` แต่ไม่ตั้ง `license_plate` / `plate_province` / `brand` / `color` |
| `DatabaseSeeder` | สร้าง `parking_logs` โดยไม่ใส่ `license_plate` เลย (0 ครั้งใน 4 จุด) และไม่เคยใส่ `plate_province` / `brand` / `color` → seed ใหม่แล้ว AI matching ใช้ไม่ได้ทั้งระบบ |

## H.3 สิ่งสำคัญที่ยังไม่มี Test (เทียบตาราง §25.1)

| Test Area ที่ §25.1 กำหนด | มี? |
| ------------------------ | :-: |
| Deposit — คำนวณ `hourly_rate × 1` และต้องชำระเพื่อยืนยัน | ❌ |
| Cancel Deposit — ยกเลิกแล้วไม่คืน | ❌ |
| Slot Assignment — ระบบเลือกอัตโนมัติและไม่ชนกัน | ❌ |
| AI Accuracy — `> 85%` ผ่าน / `<= 85%` แจ้งเตือน | ❌ |
| AI No Match → สร้าง Walk-in Reservation | ❌ |
| AI Read Error → แจ้ง Owner + Admin | ❌ |
| Walk-in — `Walkin User` / Deposit 0 / check-in ต่ออัตโนมัติ | ❌ |
| Payment — Deposit และยอด Checkout แยกกัน | ❌ |
| Notification — ผู้รับถูกต้อง + read/unread | ❌ |
| Owner Scope / Admin Scope | ❌ |
| Security — Route/ID Access ข้าม Role และข้าม Owner | ❌ |
| Reservation Time — ห้ามเวลาในอดีต | 🟡 มีแต่ failed |
| AI Matching — ทะเบียน+จังหวัด AND (ยี่ห้อ OR สี) | 🟡 `OcrCheckInTest` ผ่าน แต่ใช้ data shape เก่า |
| Authentication | ✅ |
| Reservation — สร้าง/แก้/ยกเลิก | 🟡 มีแต่ failed |
| Slot Lock | 🟡 |
| Blacklist | 🟡 |
| Checkout | 🟡 |
| Reservation Expire | 🟡 |

**ครอบคลุม requirement ใหม่ประมาณ 3 จาก 19 หัวข้อที่ §25.1 กำหนด**

---

# I. Final Assessment

## I.1 สิ่งที่ระบบมีแล้วและใช้ต่อได้ — **KEEP**

| รายการ | เหตุผล |
| ------ | ------ |
| Authentication + Force Password Reset | ตรง §19.1 |
| Role 3 ระดับ + middleware 4 ตัว | ตรง §5, §19.2 |
| Parking Lot CRUD + `hourly_rate` + เปิด/ปิดลาน/รับจอง | ตรง §6.1 |
| Parking Slot CRUD + Bulk Create + 3 สถานะ | ตรง §6.2 |
| `lockForUpdate()` + transaction ใน `CheckInService` | ตรง §24 — โครงสร้างพร้อมใช้กับ flow ใหม่ |
| Scheduler `reservations:expire` | ตรง §23 (แก้แค่ค่าเวลา) |
| Blacklist CRUD + ไม่ block + แจ้ง Owner/Admin | ตรง §10.9, §14.1 |
| AI Vision integration (อ่านครบ 5 ค่า) | ตรง §10.2 |
| `matchScanAgainstReservation()` logic | ตรง §10.6 เป๊ะ |
| Notification infrastructure + `notify_user()` | ตรง §15 (ขาดแค่ event) |
| `admin_actions` audit table + index ครบ | ตรง §18 |
| Owner Application workflow | ตรง §16 |
| Checkout: snapshot rate · ยอดไม่ติดลบ · คืน slot · transaction | ตรง §12.1-12.2, §12.4 |
| CSV Export infrastructure | ตรง §17.4 |

## I.2 สิ่งที่มีแต่ต้องเปลี่ยน — **MODIFY**

| รายการ | ต้องเปลี่ยนเป็น | อ้างอิง |
| ------ | -------------- | ------- |
| `.env` — `RESERVATION_GRACE_PERIOD=30` | `60` | §7.2, §23 |
| `Reservation::scopeCheckable()` — `addMinutes(5)` | เอาออก | §27 ข้อ 8 |
| `User/ReservationController.php:50` — `before: addDay()` | เอาออก | §27 ข้อ 7 |
| ฟอร์มจอง — dropdown เลือก slot | เอาออก ให้ระบบจัดให้ | §6.3, §27 ข้อ 6 |
| `reservation_fee` ที่ถูกใช้เป็น Deposit | แยกเป็น 2 แนวคิด: **Deposit** (เงินที่เก็บ) + **`reservation_fee`** (ส่วนลด) | §8, §9, §12.3 |
| `payments.parking_log_id` NOT NULL + UNIQUE | ต้องรองรับ Deposit payment ที่ยังไม่มี parking_log | §13.1 |
| `CheckOutService` — หักตัวเดียว | หัก Deposit แล้วหัก `reservation_fee` ต่อ | §12.3 |
| `CarScanController` — match ไม่ผ่านแล้วหยุด | ให้ตกไปเข้า Walk-in flow | §10.7 |
| `findMatchingReservation($plate)` | รับ lot แล้วกรองด้วย | §10.8 |
| `attemptWalkInCheckIn()` | สร้าง Reservation ก่อน แล้วค่อย check-in | §11.1, §27 ข้อ 9 |
| `pending → confirmed` ด้วยมือ | ผูกกับการชำระ Deposit | §7.3, §13.1 |
| Dashboard/Log queries — `INNER JOIN vehicles` (11 จุด) | เลิกพึ่งพา `vehicle_id` | §27 ข้อ 2 |
| `ParkingSlotController::destroy` | เพิ่ม guard ห้ามลบ slot ที่ `occupied` | §6.3 ข้อ 8 |
| `admin.reservations.create/store` | รับ plate/province/brand/color หรือถอดทิ้ง | §27 ข้อ 11 |
| Factory + Seeder ทั้งหมด | สร้างข้อมูลทรง plate-based | §25 |
| `ReservationDepositTest` | เขียนใหม่ตามนิยาม Deposit ใหม่ | §25.1 |

## I.3 สิ่งที่ต้องสร้างเพิ่ม — **ADD**

| รายการ | อ้างอิง |
| ------ | ------- |
| **Deposit Payment** — สร้าง payment ตอนจอง + ผูกกับการ confirm | §8.2, §13.1 |
| **AI Accuracy gate `> 85%`** | §10.3, §28.3 |
| **Notification: AI accuracy ต่ำ → Owner + Admin** | §15.2-15.3 |
| **Notification: AI อ่านทะเบียนไม่ได้ → Owner + Admin** | §10.4, §15.2-15.3 |
| **Notification: Reservation ใหม่ → Owner ของลาน** | §15.2 |
| **`Walkin User` identity** | §3.1, §11.1 |
| **Walk-in Reservation creation flow** | §11.1, §28.2 |
| **`suspicious_vehicles.province`** | §14 |
| **Owner Audit Log** | §18 |
| **Test ตาม §25.1 อีกประมาณ 16 หัวข้อ** | §25.1 |
| Index ที่ขาด + CHECK constraint ที่ขาด | §24, §20 |
| `parking_lots.latitude` / `longitude` (เมื่อเริ่ม Marketplace) | §3.2 (future) |

## I.4 สิ่งที่ควรลบเพราะเป็น Legacy — **REMOVE**

| รายการ | Location | อ้างอิง |
| ------ | -------- | ------- |
| `vehicles` table + `App\Models\Vehicle` | `app/Models/Vehicle.php`, migration | §27 ข้อ 1 |
| `admin.vehicles.*` (6 routes + controller + 3 views) | `app/Http/Controllers/Admin/VehicleController.php`, `resources/views/admin/vehicles/` | §27 ข้อ 1 |
| `User\VehicleController` + 2 views | `app/Http/Controllers/User/VehicleController.php`, `resources/views/user/vehicles/` | §27 ข้อ 1 |
| import ค้าง `UserVehicleController` | `routes/web.php:6` | — |
| `reservations.vehicle_id` · `parking_logs.vehicle_id` · `license_plate_scans.vehicle_id` | migrations | §27 ข้อ 2 |
| duplicate guard `whereHas('vehicle')` (dead code) | `app/Http/Controllers/User/ReservationController.php:85-87` | §27 ข้อ 2 |
| `App\Models\Role` · `App\Models\Permission` | `app/Models/` | — |
| `VehicleFactory` + การอ้าง Vehicle ใน 4 factories | `database/factories/` | §25 |
| `hasSlotConflict()` (ถ้าเลิกให้เลือก slot เอง) | `User/ReservationController.php:266`, `Admin/ReservationController.php:409` | §27 ข้อ 6 |

---

## I.5 ข้อสังเกตสำคัญก่อนเริ่มงาน

### 1. `payments.parking_log_id` เป็น NOT NULL + UNIQUE คือ blocker จริง

ไม่ใช่แค่ logic ที่ยังไม่ได้เขียน — requirement §13.1 เรื่อง Deposit Payment **ทำไม่ได้เลย**จนกว่าจะแก้ schema ตรงนี้ก่อน
ถ้าจะเริ่มงาน ควรเริ่มจากจุดนี้ เพราะ requirement อีกหลายข้อ (§7.3 confirm, §8.2, §12.3 checkout, §25.1 test) ผูกกับมันทั้งหมด

### 2. Seeder กำลังปิดบังปัญหาอยู่

`DatabaseSeeder` สร้างข้อมูลทรงเก่า (ใส่ `vehicle_id` ทุกแถว) ทำให้ dashboard ดูปกติดี
ถ้าแก้ Core Flow แล้วยังใช้ seeder เดิมทดสอบ **จะไม่เห็นว่าอะไรพัง**
แนะนำให้แก้ seeder เป็นลำดับต้นๆ ก่อนเริ่มงานอื่น

### 3. §27 ข้อ 9 (Walk-in) กระทบมากกว่าที่เห็น

การเปลี่ยน Walk-in ให้สร้าง Reservation ไม่ใช่แค่เพิ่ม `Reservation::create()`
แต่จะทำให้หน้า **"ประวัติการจอด"** ซึ่งตอนนี้ทำหน้าที่เป็นทางเช็คเอาท์ของ walk-in โดยเฉพาะ
**ซ้ำซ้อนกับหน้า "การจอง" ทันที** ควรตัดสินใจเรื่องนี้พร้อมกัน

### 4. Marketplace ไม่ใช่ข้อบกพร่องในรอบนี้

§3.2 จัด Marketplace เป็น future scope อย่างชัดเจน และระบุว่า
"ไม่ควรทำให้การพัฒนาหรือการทดสอบ Reservation/Check-in ต้องรอ Marketplace"
โค้ดที่มีอยู่จึงถือเป็น **FUTURE** ไม่ใช่ LEGACY ที่ต้องลบ — แต่ยังขาด GPS ตามแนวคิดใน §3.2

---

# ภาคผนวก: คำสั่งที่ใช้ตรวจสอบ

> ทุกคำสั่งเป็น read-only ทำซ้ำได้โดยไม่กระทบข้อมูล

```bash
# [R1] Deposit — มี Payment ตอนจองหรือไม่
grep -rn "Payment::create\|Payment::updateOrCreate\|new Payment" app/

# [R2] User เลือก Slot เองได้หรือไม่
grep -n "parking_slot_id" app/Http/Controllers/User/ReservationController.php
grep -n "parking_slot_id" resources/views/user/reservations/create.blade.php

# [R3] จำกัด 24 ชม.
grep -n "addDay\|before:" app/Http/Controllers/User/ReservationController.php

# [R4] Grace period
grep -rn "grace_period\|gracePeriodMinutes" config/parking.php app/Models/Reservation.php

# [R5] Early check-in 5 นาที
grep -n "addMinutes(5)\|subMinutes" app/Models/Reservation.php

# [R6] Accuracy > 85% ถูกใช้ที่ไหน
grep -rn "confidence\|accuracy\|85" app/Services/ app/Http/Controllers/CarScanController.php

# [R7] Walk-in สร้าง Reservation หรือไม่
grep -n "Reservation::create\|attemptWalkInCheckIn" \
  app/Http/Controllers/CarScanController.php app/Services/CheckInService.php

# [R8] มี Walkin User หรือไม่
php artisan tinker --execute='$u=DB::table("users")
  ->where("name","ilike","%walk%")->orWhere("email","ilike","%walk%")
  ->get(["id","name","email","role"]);
  echo $u->isEmpty() ? "ไม่พบ user Walkin\n" : $u;'

# [R9] Blacklist มีคอลัมน์ province หรือไม่
php artisan tinker --execute='foreach(DB::select(
  "select column_name,data_type,is_nullable from information_schema.columns
   where table_name=(?) order by ordinal_position",["suspicious_vehicles"]) as $c)
  printf("%-18s %-12s null=%s\n",$c->column_name,$c->data_type,$c->is_nullable);'

# [R14] payments schema — Deposit ใส่ได้หรือไม่
php artisan tinker --execute='foreach(DB::select(
  "select column_name,is_nullable from information_schema.columns
   where table_name=(?) and column_name in (?,?)",
  ["payments","parking_log_id","reservation_id"]) as $c)
  printf("%-18s nullable=%s\n",$c->column_name,$c->is_nullable);
foreach(DB::select("select indexdef from pg_indexes where tablename=(?)",["payments"]) as $i)
  printf("IDX %s\n",$i->indexdef);'

# [F3] สถานะข้อมูลปัจจุบัน
php artisan tinker --execute='
foreach(DB::select("select status,count(*) from reservations group by 1 order by 2 desc") as $r)
  printf("%-12s %d\n",$r->status,$r->count);
foreach(DB::select("select (vehicle_id is null) as veh_null, min(created_at) f,
  max(created_at) l, count(*) from reservations group by 1") as $x)
  printf("veh_null=%s n=%d %s..%s\n",var_export($x->veh_null,true),$x->count,$x->f,$x->l);
printf("province NULL: %d | brand NULL: %d | color NULL: %d | of %d\n",
  DB::table("reservations")->whereNull("plate_province")->count(),
  DB::table("reservations")->whereNull("brand")->count(),
  DB::table("reservations")->whereNull("color")->count(),
  DB::table("reservations")->count());'

# [C.2] ตรวจบัญชี Deposit
php artisan tinker --execute='
printf("parking_fee=%s discount=%s total=%s\n",
  DB::table("payments")->sum("parking_fee"),
  DB::table("payments")->sum("reservation_discount"),
  DB::table("payments")->sum("total_amount"));
printf("expired/cancelled fee total=%s\n", DB::table("reservations")
  ->whereIn("status",["expired","cancelled"])->where("reservation_fee",">",0)
  ->sum("reservation_fee"));'

# Notification events ทั้งหมด
grep -rn "notify_user(" app/ --include=*.php

# Test/Factory ที่ยังอ้าง Vehicle
grep -rln "Vehicle" tests/ database/factories/

# รัน test suite
php artisan test
```

---

**สิ้นสุดรายงาน** · ไม่มีการแก้ไข source code, database, migration, route, controller, model, view, service, test หรือ configuration ใดๆ ในการตรวจสอบครั้งนี้
