# PHASE 0 — BASELINE & ARCHITECTURE FREEZE

> ⚠️ **SUPERSEDED (2026-09-16)** — รายการ REMOVE/ADD ในเอกสารนี้ถูกดำเนินการครบใน Phase 1–16 แล้ว
> เก็บไว้เพื่อดูประวัติเท่านั้น · สถานะปัจจุบันดูที่ `docs/project-plan.md` (Source of Truth) และ `docs/TEST_COVERAGE.md`

> **วันที่:** 2026-09-13 · **Branch:** `main` · **Commit:** `f2e78e5`
> **Source of Truth:** `docs/project-plan.md` (1,482 บรรทัด)
> **อ้างอิงผลตรวจ:** `docs/REQUIREMENT_AUDIT.md`
> **ขอบเขต:** read-only — ไม่มีการแก้ code / migration / database / config / test

---

## 0. ข้อสังเกตก่อนอ่าน

1. **`REQUIREMENT_AUDIT.md` อ้างอิง project-plan ฉบับ 1,335 บรรทัด** แต่ฉบับปัจจุบันยาว 1,482 บรรทัด
   ทำให้เลข § บางจุดใน audit ไม่ตรงแล้ว เช่น audit อ้าง "§28.3 AI Accuracy" แต่ฉบับปัจจุบัน §28.3 = Admin Reservation Flow และ §28.4 = AI Error Flow
   ผลตรวจเชิงเนื้อหาของ audit ยังถูกต้อง เพราะ Phase 0 ตรวจซ้ำแล้ว (ดูหัวข้อ 7) แต่ **ให้ใช้เลข § ในเอกสารนี้แทน**
2. **`/docs` อยู่ใน `.gitignore`** (`.gitignore:3`) — เอกสารทั้งหมดรวมไฟล์นี้ไม่ถูก track โดย git
3. Test DB แยกเป็น `smart_parking_test` การรัน test ไม่กระทบ DB ที่ใช้งาน

---

## 1. Baseline ปัจจุบัน

| รายการ | ค่า |
| ------ | --- |
| Routes (ไม่รวม vendor) | 127 |
| Controllers | 37 (Admin 11 · Owner 8 · User 3 · Auth 9 · อื่น ๆ 6) |
| Models | 15 (รวม `Vehicle`, `Role`, `Permission`) |
| Services | 3 (`CarScanService`, `CheckInService`, `CheckOutService`) |
| Migrations | 22 (ทั้งหมดสถานะ Ran) |
| Factories | 7 (รวม `VehicleFactory`) |
| Seeders | 1 (`DatabaseSeeder` 996 บรรทัด) |
| Tests | 23 ไฟล์ · **126 tests: 119 passed / 7 failed** |
| E2E (Playwright) | 4 test files + `e2e/.auth/*.json` 3 ไฟล์ |
| Scheduler | `reservations:expire` ทุก 1 นาที (`routes/console.php:7`) |

### Test ที่ fail (baseline)

| File | Fail | สาเหตุ |
| ---- | ---: | ------ |
| `ReservationTest` | 5 | ส่ง `vehicle_id` แทน plate/province/brand/color |
| `ReservationDepositTest` | 1 | ทดสอบว่า `reservation_fee == hourly_rate` (แนวคิดเก่า) |
| `LotReservationsEnabledTest` | 1 | ส่ง `vehicle_id` |

Test ที่ **ผ่าน** แต่ใช้ data shape เก่าผ่าน factory ที่มี `Vehicle::factory()`: `CheckInTest`, `CheckOutTest`, `OcrCheckInTest`, `ReservationCheckInIntegrationTest`, `SlotReservationLifecycleTest`, `ExpireReservationsTest`, `ReservationNotificationsTest`, `UserCancelReservationTest`, `DashboardChartDataTest`

---

## 2. KEEP — ตรง Requirement และใช้ต่อได้

| รายการ | Location | อ้างอิง |
| ------ | -------- | ------- |
| `users.role` + CHECK (`user/owner/admin`) | `0001_01_01_000000_create_users_table.php` | §5, §3.3 |
| `users.force_password_reset` + middleware | `app/Http/Middleware/ForcePasswordReset.php` | §19.1 |
| Middleware `admin`, `owner`, `owner.approved`, `role` | `bootstrap/app.php:14-19` | §19.2 |
| Parking Lot CRUD + `hourly_rate` + `is_active` + `reservations_enabled` | `Admin/ParkingLotController`, `Owner/ParkingLotController` | §6.1 |
| Parking Slot CRUD + Bulk Create | `Admin/ParkingSlotController`, `Owner/ParkingSlotController` | §6.2 |
| `parking_slots.status` CHECK 3 ค่า | `2026_02_18_140455_create_parking_slots_table.php` | §6.2 |
| `reservations.license_plate / plate_province / brand / color` | migrations 2026_07_20_* | §4 |
| `reservations.status` ครบ 6 ค่า | `create_reservations_table.php` | §7.3 |
| Owner scope `ParkingLot::ownedBy()` / Admin scope `::unowned()` | `app/Models/ParkingLot.php` | §19.2 |
| `lockForUpdate()` + `DB::transaction` pattern | `CheckInService.php:62-80` | §24 |
| `ExpireReservations` command (โครงสร้าง: transaction · คืนเฉพาะ `reserved` · log · notify · `--dry-run`) | `app/Console/Commands/ExpireReservations.php` | §23 |
| `CarScanService::detect()` — prompt อ่าน 5 ค่า | `app/Services/CarScanService.php:36-88` | §10.2 |
| `matchScanAgainstReservation()` — ทะเบียน+จังหวัด AND (ยี่ห้อ OR สี) | `CarScanService.php:247+` | §10.6 |
| `notifySuspiciousVehicle()` — ไม่ block + แจ้ง Owner+Admin | `CarScanController.php:213-230` | §10.9, §14.1 |
| Blacklist CRUD + toggle | `Admin/SuspiciousVehicleController` | §14.2 |
| Checkout: snapshot `hourly_rate`, ยอดไม่ติดลบ, คืน slot, reservation → completed, transaction | `CheckOutService.php` | §12.1-12.4 |
| Notification table + `notify_user()` + read/read-all | `app/Support/notify_user.php`, `NotificationController` | §15 |
| `admin_actions` table + index + `admin_audit()` | `create_admin_actions_table.php`, `app/Support/admin_audit.php` | §18 (ต้องขยาย) |
| Owner Application (apply/edit/resubmit/approve/reject/reason) | `Owner/ApplicationController`, `Admin/OwnerApplicationController` | §16 |
| `reservation_logs` table | `create_reservation_logs_table.php` | §17.4, Phase 8 |
| Payment `payment_status` + `hourly_rate` snapshot | `create_payments_table.php` | §20 |
| CSV export infrastructure (StreamedResponse) | `Admin/ReservationLogController::export`, `AdminActionController::export` | §17.5 |
| Marketplace page (**Future scope — ไม่ลบ ไม่พัฒนา**) | `MarketplaceController` | §3.2 |
| Auth stack (Breeze) + verification routes | `routes/auth.php`, `Auth/*` | §19.1 (ต้องเปิดใช้จริง) |

---

## 3. MODIFY — มีอยู่แต่ต้องเปลี่ยน

### 3.1 Database (Phase 1)

| รายการ | ปัจจุบัน | ต้องเป็น | อ้างอิง |
| ------ | -------- | -------- | ------- |
| `payments.parking_log_id` | NOT NULL + UNIQUE | nullable — รองรับ Deposit ที่ยังไม่มี ParkingLog | §13 |
| `payments` | ไม่มีประเภท / ผู้ยืนยัน / เวลายืนยัน | แยก Deposit / Checkout · `paid_by` · `paid_at` · กัน Deposit ซ้ำ | §13.2, §30.8 |
| `payments.reservation_discount` | ถูกใช้เก็บ "มัดจำ" | แยก Deposit ที่หัก กับ `reservation_fee` ที่หัก | §12.3 |
| `reservations` | ไม่มีฟิลด์ Deposit | มีข้อมูล Deposit แยกจาก `reservation_fee` | §8, §9 |
| `reservations.status` | ไม่มี CHECK | CHECK 6 ค่า | §20 |
| `payments.payment_status` | ไม่มี CHECK | CHECK | §20 |
| `suspicious_vehicles` | ไม่มี `province` · `license_plate` UNIQUE เดี่ยว · `level` ไม่มี CHECK | เพิ่ม `province` · ทบทวน unique · CHECK `low/medium/high` | §14 |
| `parking_slots` | ไม่มี UNIQUE `(parking_lot_id, slot_number)` | เพิ่ม UNIQUE | Phase 1 ข้อ 6 |
| `license_plate_scans.license_plate` | NOT NULL | ต้องบันทึก Scan ที่อ่านทะเบียนไม่ได้ได้ | §10.4 |
| `license_plate_scans` | ไม่มีสถานะผลตรวจ (passed / low accuracy / unreadable) | ต้องบันทึกผลได้ | §10.3-10.4 |
| `admin_actions` | `admin_id` · ไม่มี role ของผู้กระทำ | actor ทุก Role + role ขณะทำรายการ | §18 |
| `parking_logs.parking_lot_id` | FK RESTRICT | ทบทวน delete behavior | Phase 1 ข้อ 7 |
| Indexes ที่ขาด | `parking_lots.owner_id`, `parking_slots.parking_lot_id`, `parking_logs.license_plate`, `parking_logs.check_out_time`, `reservations.(parking_lot_id, user_id, reserve_start)`, `reservation_logs.reservation_id` | เพิ่ม | Phase 1 ข้อ 7 |
| `config/parking.php` + `.env:59` | `grace_period = 30` | 60 นาที | §7.2, §23 |

### 3.2 Business Logic

| รายการ | Location | ต้องเปลี่ยนเป็น | Phase |
| ------ | -------- | --------------- | :---: |
| `before: now()->addDay()` | `User/ReservationController.php:50` | ไม่จำกัดวัน | 2 |
| ข้อความ "รอ Admin ยืนยันการจอง" + `reservation_fee = hourly_rate` | `User/ReservationController.php:127, 139` | Deposit + Payment flow | 2 |
| duplicate guard `whereHas('vehicle')` (dead code) | `User/ReservationController.php:83-85` | ใช้ `parking_logs.license_plate` | 2 |
| Admin `store()` บังคับ `vehicle_id` ไม่มี plate/province/brand/color | `Admin/ReservationController.php:112-174` | Plate-based + Deposit | 2 |
| Admin `update()` แก้ `status` ได้อิสระ + `parking_slot_id` | `Admin/ReservationController.php:193+` | ตาม state machine | 2 |
| `confirm()` แบบกดมือไม่ผูก Payment (Admin + Owner) | `Admin/ReservationController.php:262`, `Owner/ReservationController.php:67` | Mark Paid Deposit → Confirmed | 2 |
| `Reservation::scopeCheckable()` — `addMinutes(5)` | `app/Models/Reservation.php:106` | ไม่มี early window | 2 / 5 |
| Slot ถูก Lock ที่ confirm เฉพาะเมื่อ user เลือก slot | confirm() ทั้ง 2 ที่ | ระบบจัด slot เอง | 3 |
| `ParkingSlotController::destroy` (Admin) ไม่มี guard | `Admin/ParkingSlotController.php:115` | ห้ามลบ `occupied` | 3 |
| `CarScanController::store` — ไม่มี accuracy gate · อ่านไม่ได้เงียบ | `CarScanController.php:84` | gate > 85% + แจ้งเตือน + log | 4 |
| `CarScanService` — `verify => false` hardcode | `CarScanService.php:23` | ผูกกับ environment | 4 |
| `CarScanService::scanAndSave` — จับคู่/อัปเดต `Vehicle` | `CarScanService.php:162-173` | ไม่พึ่ง Vehicle | 1 / 4 |
| `findMatchingReservation($plate)` ไม่รับ lot · มี Vehicle fallback | `CarScanService.php:203-220` | กรองด้วย lot ที่สแกน | 5 |
| match ไม่ผ่าน → error หยุด | `CarScanController.php:106-113` | ตกไป Walk-in | 5 |
| `attemptWalkInCheckIn()` สร้างแค่ ParkingLog | `CarScanController.php:178` | สร้าง Reservation (Walkin User) ก่อน | 5 |
| `CheckInService::checkIn()` — ส่ง `allowedLotIds = null` จาก scan · param `$vehicleId` | `CheckInService.php`, `CarScanController.php:124-131` | lot scope + ไม่มี vehicle | 5 |
| `CheckOutService` — หัก `reservation_fee` เป็นมัดจำตัวเดียว · expire inline · หา user จาก Vehicle | `CheckOutService.php:38-39, 80-86, 89-90` | หัก Deposit แล้วหัก `reservation_fee` · ไม่พึ่ง Vehicle | 7 |
| `markPaid()` ผูกกับ `parkingLog` (Admin + Owner) · Owner ไม่มี audit · ไม่บันทึกผู้ยืนยัน/เวลา | `Admin/PaymentController.php:31`, `Owner/PaymentController.php:33` | รองรับ Deposit Payment + paid_by/paid_at + audit | 7 |
| `ExpireReservations` ใช้ grace 30 | `ExpireReservations.php:19-20` | 60 นาที + boundary test | 6 |
| Dashboard/Log queries — `INNER JOIN vehicles` **14 จุด** + `DB::table('vehicles')` 1 จุด | `DashboardController.php:35,39,60,80,91,100,217,264,277,291` · `Owner/DashboardController.php:104,114` · `Admin/ReservationLogController.php:24,77` · `User/ParkingLogController.php:15` | ใช้ plate บน reservation/parking_log | 1 |
| `orWhereHas('vehicle')` ในการค้นหา | `Admin/ReservationController.php:65`, `Owner/ReservationController.php:44` | ตัดออก | 1 |
| `admin_audit()` บันทึกเฉพาะ Admin | `app/Support/admin_audit.php` | ทุก Role | 8 |
| Notification ขาด: Reservation ใหม่ → Owner · AI low accuracy · AI unreadable · Deposit | หลายจุด | เพิ่ม event | 9 |
| `User` ไม่ implement `MustVerifyEmail` | `app/Models/User.php:5` | เปิดใช้ | 10 |
| Admin route group ขาด `force.password.reset` | `routes/web.php:48` | เพิ่ม | 10 |
| `CarScanController::authorizedLots()` — role `user` = ทุกลานที่ reservable | `CarScanController.php:25-32` | ดู UNDECIDED U5 | 4 / 10 |
| เอกสาร Owner Application บน disk `public` | `Owner/ApplicationController.php:77, 204` | private disk | 11 |
| `phpunit.xml:31` มี DB password | `phpunit.xml` | ย้ายออก | 15 |

### 3.3 View / UI (แก้เท่าที่จำเป็นต่อ Phase; redesign อยู่ Phase 14)

| รายการ | Location | Phase |
| ------ | -------- | :---: |
| Dropdown เลือก slot | `resources/views/user/reservations/create.blade.php:126-137` | 3 |
| Admin reservation form เลือก Vehicle + slot | `resources/views/admin/reservations/create.blade.php`, `edit.blade.php` | 2 / 3 |
| ปุ่ม Confirm / Check-in / Check-out | `admin/reservations/index.blade.php:129,139,156` · `owner/reservations/index.blade.php:117,123,140` | 2 / 5 / 7 |
| ปุ่ม Check-out ในหน้าประวัติการจอด | `admin/parking-logs/index.blade.php:127` · `owner/parking-logs/index.blade.php:127` | 7 |
| fallback `->vehicle?->license_plate` | `admin/reservations/index,edit` · `owner/reservations/index` · `scan/index.blade.php:150,222` · `admin|owner/scan/history.blade.php:74` | 1 |
| ลิงก์ `admin.vehicles.index` | `layouts/navigation.blade.php:310` · `admin/dashboard.blade.php:25` | 1 |
| สถิติ `my_vehicles` | `DashboardController.php:125` + `dashboard-user.blade.php` | 1 |

### 3.4 Factory / Seeder / Test

| รายการ | ปัญหา | Phase |
| ------ | ----- | :---: |
| `ReservationFactory` | `Vehicle::factory()` · ไม่มี plate/province/brand/color | 1 |
| `ParkingLogFactory` | `Vehicle::factory()` · ไม่มี license_plate | 1 |
| `ParkingSlotFactory` | `slot_number` สุ่ม `?##` — ชนกันได้เมื่อเพิ่ม UNIQUE | 1 |
| `SuspiciousVehicleFactory` | ไม่มี province | 1 |
| `DatabaseSeeder` | Vehicle-based · `reservation_fee = hourlyRate` · walk-in = ParkingLog อย่างเดียว · payment ใช้ fee เป็นส่วนลด | 1 |
| Test 13 ไฟล์อ้าง Vehicle / `vehicle_id` | data shape เก่า | ตาม Phase ที่เกี่ยวข้อง / 15 |
| E2E 3 ไฟล์อ้าง vehicle | `e2e/ai-test.test.js`, `e2e/qa-audit.test.js`, `e2e/utils/routes.js` | 15 |

---

## 4. ADD — ต้องสร้างใหม่

| รายการ | อ้างอิง | Phase |
| ------ | ------- | :---: |
| Deposit Payment (สร้างตอนจอง · กันซ้ำ) | §8.2, §13.2 | 1 (schema) / 2 (flow) |
| Mark as Paid Deposit → Reservation Confirmed | §13.2 | 2 |
| `Walkin User` identity | §3.1, §11 | 1 |
| Walk-in Reservation creation (Deposit 0, reserve_start = now) | §11.1 | 5 |
| Automatic slot allocation ตอนจอง (transaction + lock) | §6.3, §24 | 3 |
| AI accuracy gate `> 85%` | §10.3 | 4 |
| บันทึก + แจ้ง Owner/Admin เมื่ออ่านทะเบียนไม่ได้ / accuracy ต่ำ | §10.4, §28.4 | 4 / 9 |
| Lot scope ตอนหา Reservation | §10.8 | 5 |
| Checkout: หัก Deposit แล้วหัก `reservation_fee` แยกกัน | §12.3 | 7 |
| Checkout trigger ที่ไม่ใช่ปุ่ม manual | §10.1 | **UNDECIDED U1** |
| Audit ทุก Role (User / Owner / System) + role ของ actor | §18 | 8 |
| Notification: Reservation ใหม่ → Owner · Deposit · Payment | §15 | 9 |
| Email verification จริง | §19.1 | 10 |
| CHECK constraints / UNIQUE / indexes ที่ขาด | §20, §24 | 1 |
| Tests ตาม §25.1 (~16 หัวข้อที่ยังไม่มี) | §25 | ทุก Phase / 15 |

---

## 5. REMOVE — Legacy ที่ต้องลบ

| รายการ | Location | อ้างอิง | Phase |
| ------ | -------- | ------- | :---: |
| `App\Models\Vehicle` | `app/Models/Vehicle.php` | §27-1 | 1 |
| `vehicles` table | `2026_02_18_140456_create_vehicles_table.php` (+ migration ใหม่ drop) | §27-1 | 1 |
| `reservations.vehicle_id` · `parking_logs.vehicle_id` · `license_plate_scans.vehicle_id` | migrations | §27-2 | 1 |
| Relations `vehicle()` / `vehicles()` | `Reservation.php`, `ParkingLog.php`, `LicensePlateScan.php`, `User.php` | §27-2 | 1 |
| `Admin\VehicleController` + `admin.vehicles.*` (6 routes) + 3 views | `app/Http/Controllers/Admin/VehicleController.php`, `routes/web.php:15,82`, `resources/views/admin/vehicles/` | §27-1 | 1 |
| `User\VehicleController` (ไม่มี route แล้ว) + import ค้าง + 2 views | `app/Http/Controllers/User/VehicleController.php`, `routes/web.php:6`, `resources/views/user/vehicles/` | §27-1 | 1 |
| `VehicleFactory` | `database/factories/VehicleFactory.php` | §27-1 | 1 |
| Seeder `seedVehicles()` + การใช้ Vehicle ทั้งหมด | `DatabaseSeeder.php:57-64, 299-340, 389-700` | §27-1 | 1 |
| page titles vehicles | `config/page_titles.php:26-29, 52-53` | §27-1 | 1 |
| `App\Models\Role` · `App\Models\Permission` (ไม่มี table / ไม่มีการอ้างอิง) | `app/Models/Role.php`, `Permission.php` | §3.3 | 10 |
| `hasSlotConflict()` + slot selection validation | `User/ReservationController.php:267`, `Admin/ReservationController.php:408` | §27-6 | 3 |
| `parking_slot_id` input จาก reservation forms + `$slots` ใน `create()` | User/Admin ReservationController + views | §27-6 | 3 |
| กฎ 24 ชม. | `User/ReservationController.php:50` + message `reserve_start.before` | §27-7 | 2 |
| Early check-in 5 นาที | `Reservation.php:106` | §27-8 | 2 / 5 |
| Manual confirm (`pending → confirmed` ไม่ผูก payment) | `admin|owner.reservations.confirm` routes + methods + ปุ่ม | §5.1, §28.3 | 2 |
| Manual Check-in | `admin|owner.reservations.check-in` routes + methods + ปุ่ม | §10.1 | 5 |
| Walk-in แบบ ParkingLog-only | `CarScanController::attemptWalkInCheckIn()` · seeder `seedWalkInParkingLogs()` | §27-9 | 5 |
| Deposit แบบ `reservation_fee = hourly_rate` | `User/ReservationController.php:127` · `CheckOutService.php:38` · seeder | §27-10 | 2 / 7 |
| Manual Check-out | `admin|owner.reservations.check-out` · `admin|owner.parking-logs.check-out` routes + methods + ปุ่ม | §10.1 | **รอ U1** |
| `ReservationDepositTest` (แนวคิดเก่า) | `tests/Feature/ReservationDepositTest.php` | §25 | 2 |
| Tests ที่ทดสอบ Vehicle ownership (`user cannot reserve other users vehicle` ฯลฯ) | `tests/Feature/ReservationTest.php` | §27-1 | 1 / 2 |

---

## 6. UNDECIDED — Requirement ยังไม่ชัด

> เรียงตามผลกระทบ · ระบุ Phase ที่จะติด · **ไม่ได้ตัดสินใจแทน**

| # | ประเด็น | ทำไมต้องตัดสินใจ | กระทบ Phase |
| -: | ------- | ---------------- | :---------: |
| **U1** | **Check-out Trigger** — §10.1 ห้าม Manual Check-out แต่ "ยังไม่กำหนดวิธี Trigger" | ปัจจุบัน `CheckOutService` ถูกเรียกจาก **ปุ่ม manual 4 จุดเท่านั้น** ถ้าลบตามข้อห้าม ระบบจะ Check-out ไม่ได้เลย (เช่น ต้องสแกนขาออก? ใครกด?) | 5, 7 |
| **U2** | **Reservation ที่ Admin สร้าง** — `user_id` เป็นของใคร และใครชำระ Deposit | §28.3 ระบุ UNDECIDED ไว้เอง · ปัจจุบันเอา `user_id` จาก Vehicle ซึ่งจะถูกลบ | 2, 12 |
| **U3** | **จังหวะ Lock Slot เมื่อจองล่วงหน้าไม่จำกัดวัน** | ถ้า Lock ตั้งแต่ Confirmed แต่ `reserve_start` อีกหลายเดือน slot จะถูกกันไว้ตลอด · Pending (ยังไม่จ่าย) กัน slot หรือไม่ · slot เต็มตอนจอง/ตอน confirm ต้องทำอย่างไร | 2, 3 |
| **U4** | **หลัง AI ไม่ผ่าน (≤85% / อ่านไม่ได้) รถจะเข้าได้อย่างไร** | ห้าม Manual Check-in · มีแค่ "แจ้งเตือน" — อัปโหลดใหม่เท่านั้น? | 4, 5 |
| **U5** | **Role ไหน "เป็นกล้อง" ได้ และสแกนให้ลานไหนได้** | §5.3 ให้ User อัปโหลดภาพได้ แต่ User สแกน walk-in เข้าลานของ Owner อื่นได้ทุกลาน (security G.1 ใน audit) | 4, 5, 10 |
| **U6** | **`reservation_fee` (ส่วนลด) — ใครกำหนด เมื่อไร ค่า default** | §9 นิยามว่าเป็นส่วนลดแต่ไม่บอกที่มา · Walk-in "ถ้ามี" | 2, 7 |
| **U7** | **User "ชำระ Deposit" ทำอะไรในระบบ** | ไม่มี Gateway/Slip · User มี action ในระบบหรือจ่ายนอกระบบแล้วรอ Mark Paid เท่านั้น | 2 |
| **U8** | **ข้อมูลเดิมใน DB ใช้งาน** (reservations 84 แถว · 74 มี `vehicle_id` · 78 ขาด province/brand/color · walk-in ParkingLog ไม่มี reservation · payments ที่ `reservation_discount` = มัดจำ) | Drop `vehicles` เป็นการเปลี่ยนแบบทำลายข้อมูล — จะ `migrate:fresh` + seeder ใหม่ หรือแปลง/backfill ข้อมูลเดิม | **1** |
| **U9** | **ลานของ Admin แทนด้วยอะไร** | ปัจจุบัน = `owner_id IS NULL` (Admin ทุกคนแชร์) · §5.1 พูดถึง "ลานที่เป็นของ Admin" | 1, 12 |
| **U10** | **`Walkin User` — ระบุตัวอย่างไร** | `users.role` CHECK มีแค่ `user/owner/admin` · login ได้หรือไม่ · ระบุด้วย email คงที่ / flag / config | 1 |
| **U11** | **Blacklist match ด้วย ทะเบียน+จังหวัด หรือทะเบียนอย่างเดียว** | §14 เพิ่ม province แต่ §14.1 บอกแค่ "เปรียบเทียบข้อมูลทะเบียน" · แถวเดิมไม่มี province | 1, 4 |
| **U12** | **แก้ Reservation ได้แค่ไหน** | §7/§4.1 "ตามสถานะที่อนุญาต" · ปัจจุบันแก้ข้อมูลรถได้ใน pending/confirmed · แก้ lot/เวลาได้หรือไม่ (กระทบ Deposit) | 2 |
| **U13** | **Admin ลบ Reservation แบบ hard delete / แก้ status อิสระ** | ขัดกับหลัก Log ครบ (§18) แต่ requirement ไม่ได้ห้ามตรง ๆ | 2, 12 |
| **U14** | **กฎ "ทะเบียนเดียวมี Reservation active ได้ 1 รายการ"** | มีในโค้ด ไม่มีใน requirement | 2 |
| **U15** | **Payment ของ Reservation ที่ยกเลิก/หมดอายุก่อนจ่าย** | สถานะ Deposit Payment ที่ไม่เคยจ่าย (void? ค้าง unpaid?) — §20 ระบุแค่ `unpaid/paid` | 1, 2, 6 |
| **U16** | **Walk-in: สถานะที่ผ่าน และใครรับ Notification** | Walkin User รับแจ้งเตือนไม่ได้จริง · status เริ่มที่อะไรก่อน `checked_in` | 5, 9 |
| **U17** | **Walk-in เข้าลานที่ `is_active=false` หรือ `reservations_enabled=false`** | ไม่ระบุ | 5 |
| **U18** | **Owner ลาออก** | §16 "ระบบ/ผู้ดูแลตรวจสอบตาม Flow" · ปัจจุบัน demote ทันที | 11 |

---

## 7. Legacy Search — Baseline (ก่อนเริ่ม Phase 1)

| คำสั่ง | ผล |
| ------ | --- |
| `grep -Rni "vehicle_id" app database routes resources tests config` | **109 hits / 33 ไฟล์** (app 12 · database 8 · resources 1 · tests 12) |
| `grep -RnE "\bVehicle\b\|vehicles\b\|->vehicle\b"` (ไม่นับ SuspiciousVehicle) | **57 ไฟล์** (app 19 · config 1 · database 8 · resources 15 · routes 1 · tests 13) |
| `grep -Rni "parking_slot_id" resources/views/user` | 5 hits — `user/reservations/create.blade.php:126-137` |
| `grep -Rni "addDay" app` | 1 — `User/ReservationController.php:50` |
| `grep -Rni "addMinutes(5)" app` | 1 — `Models/Reservation.php:106` |
| `grep -Rni "reservation_fee"` | 15 ไฟล์ (app 4 · database 3 · resources 5 · tests 3) — ใช้ในความหมาย "มัดจำ" |
| `grep -Rni "Walkin"` | ไม่มี identity `Walkin User` · walk-in mentions 6 ไฟล์ = ความหมาย ParkingLog-only |
| `grep -rn "Payment::create" app` | 1 จุด — `CheckOutService.php:50` |
| `grep -rn "MustVerifyEmail" app` | ถูก comment — `User.php:5` |
| `grep -RnE "Models\\\\(Role\|Permission)"` | 0 การอ้างอิง |
| `grep "85\|accuracy threshold"` ใน scan flow | 0 |
| Manual routes ที่ยังเปิด | `admin|owner.reservations.{confirm,check-in,check-out}` · `admin|owner.parking-logs.check-out` = **8 routes** |

---

## 8. Dependency Map

### 8.1 Core (ปัจจุบัน)

```text
Reservation
├── User                  (user_id, cascade)           ← Walk-in ยังไม่มี Walkin User
├── Vehicle  ✖ LEGACY     (vehicle_id, nullOnDelete)
├── ParkingLot            (parking_lot_id, cascade)
├── ParkingSlot           (parking_slot_id, nullOnDelete) ← user เลือกเอง ✖
├── ReservationLog        (hasMany)
├── Payment               (hasOne)                     ← มีแค่ Checkout Payment
├── ParkingLog            (hasOne)
└── Notification          (ผ่าน notify_user ไม่มี FK)

ParkingLog
├── Vehicle  ✖ LEGACY     (vehicle_id, nullable, FK RESTRICT)
├── ParkingLot            (FK RESTRICT)
├── ParkingSlot           (nullOnDelete)
├── Reservation           (nullable ← walk-in ✖)
└── Payment               (hasOne, parking_log_id NOT NULL UNIQUE ✖)

Payment
├── ParkingLog            (NOT NULL ✖ บล็อก Deposit)
└── Reservation           (nullable)

LicensePlateScan
├── User                  (ผู้สแกน)
├── Vehicle  ✖ LEGACY
└── ParkingLot            (ลานที่จำลองกล้อง)

ParkingSlot ── ParkingLot (cascade) ── User(owner_id, nullOnDelete)
SuspiciousVehicle ── User(added_by)
AdminAction ── User(admin_id)                       ← ต้องขยายเป็นทุก Role
OwnerApplication ── User(user_id, reviewed_by)
Role ✖ / Permission ✖                             ← ไม่มี dependency
```

### 8.2 Vehicle — ทุกจุดที่ต้องตัดก่อนลบ (Phase 1)

```text
Vehicle
├── Models
│   ├── Reservation::vehicle()
│   ├── ParkingLog::vehicle()
│   ├── LicensePlateScan::vehicle()
│   └── User::vehicles()
├── Services
│   ├── CarScanService::scanAndSave()            (match + update Vehicle)
│   ├── CarScanService::findMatchingReservation()(orWhere vehicle_id)
│   ├── CheckInService::checkIn($vehicleId)
│   └── CheckOutService                          (Vehicle::find → notify)
├── Controllers
│   ├── Admin\VehicleController                  (CRUD ทั้งตัว)
│   ├── User\VehicleController                   (ไม่มี route)
│   ├── Admin\ReservationController              (create/store/edit/index search)
│   ├── Owner\ReservationController              (index search, checkIn)
│   ├── User\ReservationController               (duplicate guard)
│   ├── Admin\UserController                     (vehicle_id refs)
│   ├── CarScanController                        (vehicle_id pass-through, history eager load)
│   ├── DashboardController                      (JOIN ×9 + count)
│   ├── Owner\DashboardController                (JOIN ×2)
│   ├── Admin\ReservationLogController           (JOIN ×2 รวม CSV export)
│   └── User\ParkingLogController                (JOIN ×1)
├── Routes     web.php:6, 15, 82
├── Views      admin/vehicles/*, user/vehicles/*, navigation, admin/dashboard,
│              admin/reservations/{create,edit,index}, owner/reservations/index,
│              scan/index, admin|owner/scan/history, dashboard-user (my_vehicles)
├── Config     page_titles.php
├── Database   vehicles migration, 3 FK columns, 2026_07_21 backfill migration,
│              VehicleFactory, ReservationFactory, ParkingLogFactory, DatabaseSeeder
└── Tests      13 feature tests + e2e 3 files
```

### 8.3 Check-in / Check-out callers

```text
CheckInService::checkIn
├── CarScanController::store                (reservation branch, allowedLotIds=null ✖)
├── CarScanController::attemptWalkInCheckIn (ParkingLog-only ✖)
├── Admin\ReservationController::checkIn    (manual ✖)
└── Owner\ReservationController::checkIn    (manual ✖)

CheckOutService::checkOut
├── Admin\ReservationController::checkOut   (manual ✖)
├── Owner\ReservationController::checkOut   (manual ✖)
├── Admin\ParkingLogController::checkOut    (manual ✖)
└── Owner\ParkingLogController::checkOut    (manual ✖)
    → ไม่มี automated caller เลย → U1
```

---

## 9. Phase Report

| # | หัวข้อ | ผล |
| - | ------ | --- |
| 1 | **Phase** | PHASE 0 — BASELINE & ARCHITECTURE FREEZE |
| 2 | **Changed** | ไม่มี |
| 3 | **Added** | `docs/PHASE_0_BASELINE.md` (เอกสารนี้เท่านั้น — ไม่ถูก track โดย git) |
| 4 | **Removed** | ไม่มี |
| 5 | **Database** | ไม่มีการเปลี่ยน |
| 6 | **Tests** | Baseline: 126 tests — 119 passed / 7 failed (3 ไฟล์, สาเหตุ data shape เก่า) |
| 7 | **Legacy Search** | ดูหัวข้อ 7 |
| 8 | **Remaining** | Phase 1–16 ทั้งหมด |
| 9 | **Blockers** | Phase 0 ไม่มี · สำหรับ Phase 1 ต้องตัดสินใจ **U8** (ข้อมูลเดิม) · **U9** (ลานของ Admin) · **U10** (Walkin User) · **U11** (Blacklist match) · **U15** (สถานะ Payment) ก่อนออกแบบ schema ส่วนที่เกี่ยวข้อง · **U1** เป็น blocker ใหญ่ของ Phase 5/7 |
| 10 | **Status** | `PHASE 0 COMPLETE` |
