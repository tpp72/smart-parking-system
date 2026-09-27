# PHASE PROGRESS SUMMARY — ระบบก่อน UI Rebuild (Phase 0–16)

> **วันที่ตรวจ:** 2026-09-17 23:52 – 2026-09-18 00:10 (เวลาไทย)
> **Branch:** `main` · **HEAD:** `5f0c73c` (Merge PR #2) · **Working tree:** สะอาด (`git status` ว่าง) · **Stash:** ไม่มี · **Branch อื่น:** ไม่มี (มีแค่ `main` และ `origin/main`)
> **Source of Truth:** `docs/project-plan.md` + `docs/PRODUCT.md`
> **ประเภทงาน:** Documentation / Audit เท่านั้น — ไม่มีการแก้ code, database, test หรือ config
> **เอกสารคู่กัน:** `docs/UI_PHASE_PROGRESS_SUMMARY.md` (Phase 14 — UI Rebuild)

---

## วิธีตรวจและแหล่งหลักฐาน

| แหล่ง | ใช้ตรวจอะไร |
|---|---|
| `git log` / `git show --stat` | commit ของแต่ละ Phase และจำนวนไฟล์ที่เปลี่ยน |
| Source code ปัจจุบัน | migrations, models, services, controllers, routes, middleware, views |
| `php artisan route:list --json` | route inventory (125 routes) |
| `php artisan migrate:status` | สถานะ migration บนฐาน dev (อ่านอย่างเดียว) |
| Query แบบ `SELECT` บนฐาน dev | ข้อมูลที่อาจมีปัญหา (อ่านอย่างเดียว) |
| `php artisan test` | PHPUnit บนฐาน `smart_parking_test` |
| `npx playwright test` | E2E บนฐาน `smart_parking_test` (output เขียนนอก repo) |
| `vite build` / `view:cache` | build และ compile Blade — เขียนผลไป scratchpad ไม่แตะไฟล์ในโปรเจกต์ |
| `composer audit` / `npm audit` | ช่องโหว่ของ dependency |
| GitHub Actions API (public) | ผล CI ล่าสุด |

**รายงาน Phase ที่มีเป็นไฟล์ใน repo:** มีเพียง `docs/PHASE_0_BASELINE.md` (ติดหมายเหตุ SUPERSEDED) — รายงานของ Phase 1–16 ไม่มีเป็นไฟล์แยก หลักฐานหลักจึงมาจาก **git commit + code + test ปัจจุบัน**

**ข้อจำกัด:** ไม่ใช้ memory/บันทึกการสนทนาเป็นหลักฐานตัดสินสถานะ ใช้เป็นเพียงเบาะแสในการค้นหา แล้วยืนยันกับ code/test/git ทุกครั้ง

---

## 1. Executive Summary

### สถานะปัจจุบัน

**ไม่มี Phase ที่กำลังดำเนินการ** — Phase 0–16 ของ Master Plan และ UI Phase 1–9 ถูก commit และ merge เข้า `main` แล้ว (`5f0c73c`, 2026-09-17) ใน repo ไม่มีการกำหนด Phase ถัดไป (Marketplace เป็น Future Scope ตาม §3.2)

| สถานะ | จำนวน Phase | Phase |
|---|:-:|---|
| ✅ Complete | 13 | 0, 1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13 |
| ⚠️ Complete with Issues | 4 | 4, 14, 15, 16 |
| 🟡 Partial | 0 | — |
| ⏳ Not Started | 0 | — |
| 🔴 Blocked | 0 | — |
| **รวม** | **17** | Phase 0–16 |

Phase 14 (UI Rebuild) ประเมินละเอียดในไฟล์ `docs/UI_PHASE_PROGRESS_SUMMARY.md`

### ตัวเลขภาพรวม (คำนวณได้)

- **Phase ที่ส่งมอบแล้ว** = (✅ + ⚠️) ÷ ทั้งหมด = 17 ÷ 17 = **100%**
- **Phase ที่ไม่มีปัญหาค้างที่ตรวจพบ** = ✅ ÷ ทั้งหมด = 13 ÷ 17 = **76%**
- **Test:** PHPUnit **325 passed / 0 failed / 0 skipped** (1,849 assertions) · E2E **2/2 passed** · CI บน `main` ล่าสุด **success**

> ตัวเลขทั้งสองคำนวณจากจำนวน Phase เท่านั้น ไม่ได้ถ่วงน้ำหนักตามขนาดงาน และไม่ใช่ "เปอร์เซ็นต์ความสมบูรณ์ของระบบ"

### ประเด็นที่ต้องรู้

1. **Core Business Rule ตรงกับ `project-plan.md` ฉบับล่าสุดครบทุกข้อที่ตรวจ** (Deposit, Walk-in, Accuracy > 85%, Lot scope, Slot allocation, Expire 60 นาที, Checkout, Blacklist, Notification, Audit, CSV, Owner resignation)
2. **ไม่มี Critical issue** ส่วนปัญหาระดับ **High** มี 2 ข้อ:
   - dependency มี **36 security advisories ใน 10 package** (`composer audit`)
   - หน้า Dashboard ของ User บนมือถือ **ล้นจอ 108px** (อยู่ในไฟล์ UI)
3. **Legacy ของระบบเก่าถูกลบหมดจาก code** (ไม่พบ Vehicle / `vehicle_id` / Role / Permission / AdminMiddleware / `admin_audit`) เหลือเพียงไฟล์ภาพโลโก้เก่า 2 ไฟล์ที่ไม่มีใครอ้างอิง
4. **เอกสารบางไฟล์ตัวเลขล้าสมัย** (README / TEST_COVERAGE / PRODUCT.md ยังระบุ 272 tests แต่ปัจจุบันมี 325)

---

## 2. Phase-by-Phase Status

| Phase | ชื่อ | สถานะ | หลักฐาน | สิ่งที่ทำแล้ว | สิ่งที่ยังขาด | ปัญหาที่พบ |
|:-:|---|:-:|---|---|---|---|
| 0 | Baseline & Architecture Freeze | ✅ | `docs/PHASE_0_BASELINE.md` (2026-09-13, SUPERSEDED 2026-09-16) | Baseline, KEEP/MODIFY/ADD/REMOVE, dependency map, รายการ UNDECIDED | — | เอกสารเป็นประวัติแล้ว (ตั้งใจ) |
| 1 | Database & Data Model | ✅ | commit `a285e86` · migrations 22 ไฟล์ · `tests/Feature/DataModelTest.php` (assert ว่าไม่มี `vehicle_id`) · `migrate:status` = Ran ทั้งหมด | ลบ Vehicle / `vehicle_id` · `license_plate` + `plate_province` · `payments.type` (deposit/checkout) + `void` + `paid_by`/`paid_at` · `users.is_system` (Walkin User) · UNIQUE `(parking_lot_id, slot_number)` · `owner_resignations` · `parking_logs.hourly_rate` | — | แก้ migration แบบ create ย้อนหลัง (in place) → ฐานเก่าก่อน Phase 1 upgrade ไม่ได้ ต้อง `migrate:fresh` (Low) |
| 2 | Reservation | ✅ | `a285e86` · `app/Services/ReservationService.php` · `ReservationTest`, `DepositPaymentTest`, `ReservationStateMachineTest`, `UserCancelReservationTest` | สร้างการจองพร้อม Deposit Payment · ยืนยันผ่าน Mark as Paid เท่านั้น · ยกเลิก → void · ลานเต็มขณะรับเงิน → cancel + void · 1 active ต่อทะเบียน+จังหวัด (partial unique index) · จองล่วงหน้าไม่เกิน 1 วัน | — | — |
| 3 | Slot Allocation | ✅ | `a285e86` · `app/Services/SlotAllocator.php` · `SlotAllocationTest`, `SlotReservationLifecycleTest`, `ParkingSlotManagementTest` | ระบบจัด Slot เอง (`FOR UPDATE SKIP LOCKED`) · Lock เมื่อรับมัดจำ · คืนเมื่อ cancel/expire · ห้ามลบ Slot ที่ `occupied`/`reserved` · User เลือก Slot ไม่ได้ | — | — |
| 4 | AI Scan | ⚠️ | `a285e86` · `app/Services/CarScanService.php` · `config/carscan.php` (`accuracy_threshold` = 85) · `AiScanTest`, `CarScanFakeModeTest` | อ่าน ทะเบียน/จังหวัด/ยี่ห้อ/สี/Accuracy · classify passed/low_accuracy/unreadable · Blacklist ตรวจด้วยทะเบียน+จังหวัด · แจ้ง Owner+Admin · Fake mode สำหรับ local/testing | — | ข้อมูล seed ของผลสแกน: ไฟล์ภาพไม่มีอยู่จริง (ฐาน dev 20/20 ไฟล์หาย) → หน้าประวัติสแกนเกิด HTTP 403 ใน console (Medium) · ไม่มี rate limit ที่ route สแกนซึ่งเรียก Claude API (Medium) |
| 5 | Auto Check-in + Walk-in | ✅ | `a285e86` · `app/Services/AutoCheckInService.php`, `CheckInService.php` · `AutoCheckInTest`, `CheckInServiceTest`, `CheckInTest` · E2E `walk-in-flow.test.js` ผ่าน | Walk-in = Reservation จริง (`is_walk_in`, Walkin User, deposit/fee 0, `checked_in`) · ตรวจ Lot scope · ทะเบียนไม่ตรง → Walk-in · กรณีพิเศษ §10.10 ครบ (มาก่อนเวลา / ยี่ห้อ+สีไม่ตรง / การจองอยู่ลานอื่น / pending / เลยเวลา) · ลานเต็ม → "ลานเต็ม" ไม่บันทึกอะไร ยกเว้น Blacklist | — | — |
| 6 | Reservation Expiration | ✅ | `ec7ef2c` · `config/parking.php` (`grace_period` = 60 คงที่) · `Reservation::scopeOverdue()` · `ExpireReservationsTest` | Expire เมื่อ **เกิน** 60 นาที · Deposit unpaid → void · Mark as Paid หลังเลยเวลา → expire · Scheduler ทุกนาที + `withoutOverlapping` | — | `.env` ยังมี `RESERVATION_GRACE_PERIOD=30` แต่ config ไม่อ่านแล้ว (Low) |
| 7 | Checkout & Payment | ✅ | `ec7ef2c` · `app/Services/CheckOutService.php`, `ScanGateService.php` · `CheckoutPaymentTest`, `CheckoutReservationFeeTest`, `ScanCheckOutTest`, `CheckOutTest` | ค่าจอด = ceil ชม. (ขั้นต่ำ 1) × `hourly_rate` ณ ตอน Check-in · หักมัดจำที่ paid ก่อน แล้วหัก `reservation_fee` · ยอดไม่ติดลบ · ยอด 0 → paid โดยระบบ · Auto Check-out ตรวจทิศทางเอง | — | — |
| 8 | Logging & Audit | ✅ | `ec7ef2c` · `app/Support/audit_log.php`, `AuditCatalog.php` · `app/Queries/ReservationLogQuery.php` · `AuditLogTest` | Audit ครอบ user/owner/admin/system · Payment log · Auth events · AI scan anomalies · Owner เห็น Reservation Log ของลานตัวเอง | — | ฐาน dev มี `reservation_logs.note` ถ้อยคำเก่าภาษาอังกฤษ 70 แถว (ข้อมูลเก่า ไม่ใช่ code) (Low) |
| 9 | Notification | ✅ | `ec7ef2c` · `app/Support/notify_user.php` (ข้าม `is_system`) · `ParkingLot::notifyManagers` · `NotificationPolicyTest`, `ReservationNotificationsTest` | ผู้รับตาม §15.4 · Walkin User ไม่ได้รับ · ไม่แจ้ง Owner เมื่อมีการจองใหม่ (ตาม §15.4) | — | Requirement doc ขัดกันเอง: §15.2 ยังเขียน "มี Reservation ในลาน" แต่ §15.4 ยกเลิกไปแล้ว (Low, เอกสาร) |
| 10 | Authentication & Authorization | ✅ | `ec7ef2c` · `User implements MustVerifyEmail` · middleware `RoleMiddleware`, `OwnerApprovedMiddleware`, `ForcePasswordReset`, `EnsureNotSystemUser` · `AuthorizationTest` · route list: `force.password.reset` 104 routes รวม admin | ยืนยันอีเมลบังคับ · Force reset ครอบ Admin · Walkin User login/reset ไม่ได้ · เอกสาร Owner Application อยู่ disk `local` (private) เปิดผ่าน route ที่ตรวจสิทธิ์ | — | — |
| 11 | Owner System | ✅ | `ec7ef2c` · `OwnerResignationService`, `OwnerLotClosureService` · `app/Queries/RevenueQuery.php` · `OwnerSystemTest` | คำร้องลาออก + Admin อนุมัติ/ปฏิเสธ (เหตุผล ≥ 10 ตัวอักษร) · ปิดลานตามลำดับ §16.1 · รายได้ = เงินที่รับจริงตาม `paid_at` · ลบลานไม่ได้ถ้ามีการจอง active | — | — |
| 12 | Admin System | ✅ | `710a59a` · `app/Services/UserAccountService.php` · `AdminSystemTest` · ไม่มี route `admin.reservations.create/store` | Admin dashboard ทั้งระบบ · เป็น Owner ได้ผ่านใบสมัครเท่านั้น · demote Owner = ปิดลาน · Admin สร้างการจองเองไม่ได้ · ลบลาน Admin ไม่ได้ถ้ามีการจอง active | — | — |
| 13 | CSV Export | ✅ | `a65f705` · `app/Support/CsvExport.php` (BOM + กัน formula injection) · `app/Queries/AdminExportQuery.php` · `CsvExportTest` · ไม่มี export route ฝั่ง owner | Export การจอง / ประวัติจอด / รายได้รายวัน / Reservation Log / Audit Log · Admin เท่านั้น | — | — |
| 14 | UI Rebuild | ⚠️ | commit `2c250af` (UI Phase 1–9) — ดู `docs/UI_PHASE_PROGRESS_SUMMARY.md` | ระบบดีไซน์ใหม่ทั้งแอป | — | User dashboard ล้นจอบนมือถือ (High) และข้อย่อยอื่น ๆ ตามไฟล์ UI |
| 15 | Testing & QA | ⚠️ | `5c217a4` · E2E 2 flow · `.github/workflows/tests.yml` (PHP 8.4 + postgres:17) · `docs/TEST_COVERAGE.md`, `docs/UAT_CHECKLIST.md` · `phpunit.xml` ไม่มีรหัสผ่านแล้ว | เขียน E2E ใหม่ · CI จริง · ย้ายรหัสผ่าน DB ออกจาก `phpunit.xml` · Fake AI mode | — | รหัสผ่าน DB เดิม **ยังอยู่ในประวัติ git** (เพิ่มใน `9c93881`, เอาออกใน `5c217a4`) และ repo เป็น **public** (Medium) · `docs/TEST_COVERAGE.md` ยังระบุ 272 tests (Low) |
| 16 | Legacy Cleanup | ⚠️ | `2afd23e`, `820dc81` · ไม่พบ Vehicle/Role/Permission/AdminMiddleware/`admin_audit` ใน `app`, `routes`, `database`, `resources`, `tests`, `config`, `e2e` | README ใหม่ · `.env.example` ใช้ Anthropic · ลบ Breeze components + deps (breeze, sail, @tailwindcss/vite, axios) · ติด SUPERSEDED ให้เอกสารเก่า | — | README badge และข้อความยังเป็น "272 passed" (Low) · `public/images/logo.png` และ `logo-email.png` ยัง track แต่ไม่มีการอ้างอิง (Low) · dependency advisories ไม่ได้อยู่ในขอบเขต Phase นี้และยังค้าง (ดู §5) |

---

## 3. สิ่งที่ทำเสร็จแล้วจริง

ตรวจจาก code + test ปัจจุบัน (ไม่ได้อ้างจากรายงาน)

| Feature | สถานะ | หลักฐานหลัก |
|---|:-:|---|
| Authentication (register / login / logout / reset / change password) | ✅ | `app/Http/Controllers/Auth/*` · `tests/Feature/Auth/*` (6 ไฟล์) |
| Email Verification (บังคับจริง) | ✅ | `User implements MustVerifyEmail` · middleware `verified` 101 routes · `EmailVerificationTest` |
| Force Password Reset (ทุก Role รวม Admin) | ✅ | `ForcePasswordReset` บน 104 routes · `AccountPagesTest` |
| Roles `users.role` + middleware เดียว | ✅ | `RoleMiddleware` (`role:admin` 54 / `role:owner` 27 / `role:user` 10 routes) |
| Walkin User (system account) | ✅ | migration `2026_09_13_000000_insert_walkin_system_user` · `EnsureNotSystemUser` · `LoginRequest::attemptWhen` · ฐาน dev มี `is_system` 1 บัญชี |
| Parking Lot (ไม่มีสถานะเปิด/ปิดลาน, มี `reservations_enabled`) | ✅ | `Owner/Admin ParkingLotController` · `LotReservationsEnabledTest` |
| Parking Slot + Bulk Create + UNIQUE ต่อลาน | ✅ | `ParkingSlotController` · migration `unique(['parking_lot_id','slot_number'])` · `ParkingSlotManagementTest` |
| Reservation (จอง / แก้ข้อมูลรถ / ยกเลิก) | ✅ | `ReservationService` · `User/ReservationController` · `ReservationTest` |
| Deposit = `hourly_rate × 1` เป็น Payment จริง | ✅ | `Reservation::depositFor()` · `Payment::create(type=deposit)` ใน `ReservationService.php:58` · `DepositPaymentTest` |
| `reservation_fee` = ส่วนลด แยกจาก Deposit | ✅ | `ReservationService.php:54` · `CheckoutReservationFeeTest` |
| Mark as Paid (กันซ้ำ, ห้าม void, บันทึก `paid_by`/`paid_at`) | ✅ | `ReservationService.php:101–162` (lockForUpdate) |
| Slot Allocation อัตโนมัติ + Race condition | ✅ | `SlotAllocator` (`FOR UPDATE SKIP LOCKED`) · `SlotAllocationTest` |
| AI Scan (Claude Vision + Fake mode) | ✅ | `CarScanService` · `CarScanFakeModeTest` |
| Accuracy > 85% | ✅ | `LicensePlateScan::classify()` · `config/carscan.php:27` |
| Auto Check-in (Reservation) | ✅ | `AutoCheckInService` · `AutoCheckInTest` · E2E `reservation-flow.test.js` |
| Walk-in (สร้าง Reservation) | ✅ | `CheckInService::checkInWalkIn` → `Reservation::create(is_walk_in, Walkin User, checked_in)` · E2E `walk-in-flow.test.js` |
| Manual Check-in (fallback) | ✅ | `Owner/Admin ReservationController::checkIn` · `CheckInTest` |
| Auto / Manual Check-out | ✅ | `ScanGateService` · `CheckOutService` · `ScanCheckOutTest` |
| Blacklist (ทะเบียน + จังหวัด, แจ้งเตือนอย่างเดียว) | ✅ | `suspicious_vehicles` UNIQUE `(license_plate, plate_province)` · `SuspiciousVehicleBlacklistTest`, `AdminSuspiciousVehicleTest` |
| Notification (ตามตาราง §15.4) | ✅ | `NotificationPolicyTest` |
| Audit Log (ทุก Role + System) | ✅ | `audit_log.php` · `AuditLogTest` |
| Reservation Log (Admin ทั้งระบบ / Owner เฉพาะลาน) | ✅ | `ReservationLogQuery` · `Owner/ReservationLogController` |
| Owner Application + เอกสาร private | ✅ | `OwnerApplicationDocumentController` · migration `move_owner_application_documents_to_private_disk` |
| Owner Resignation (Admin อนุมัติ) | ✅ | `OwnerResignationService` · `OwnerSystemTest` |
| Scheduler — Expire Reservation | ✅ | `routes/console.php` · `ExpireReservations` · `ExpireReservationsTest` |
| CSV Export (Admin เท่านั้น) | ✅ | `ExportController` · `CsvExportTest` |
| Dashboard 3 Role + Revenue | ✅ | `DashboardController`, `Owner/DashboardController`, `RevenueQuery` · `DashboardChartDataTest` |
| Marketplace | 🟡 | มีหน้า `/marketplace` แต่ไม่มี GPS — เป็น **Future Scope** (§3.2) จึงไม่นับเป็นข้อบกพร่อง |

---

## 4. Requirement Alignment

เทียบกับ `docs/project-plan.md` (ฉบับล่าสุด §31 "ไม่มีประเด็นค้าง") และ `docs/PRODUCT.md`

### ✅ ตรง Requirement

| Requirement | § | หลักฐาน |
|---|---|---|
| Plate-based (ไม่มี Vehicle Entity) | §4, §27.1–2 | ไม่มี `vehicle_id` ในทุกตาราง (`DataModelTest`) |
| Walkin User = `role=user`, Login ไม่ได้, ไม่รับแจ้งเตือน | §4 | `EnsureNotSystemUser`, `notify_user.php:19` |
| แก้ได้เฉพาะ ทะเบียน/จังหวัด/สี/ยี่ห้อ ก่อน Check-in | §4.1 | `User/ReservationController` |
| Slot rules 1–12 | §6.3 | `SlotAllocator`, slot destroy guard (`occupied`/`reserved`), UNIQUE index |
| 1 active ต่อทะเบียน+จังหวัด (ไม่นับ Walk-in) | §7.1 | partial unique index ใน migration `2026_09_13_000200` |
| Admin สร้าง Reservation ไม่ได้ | §7.1, §28.3 | ไม่มี route `admin.reservations.create/store` |
| จองล่วงหน้าไม่เกิน 1 วัน, ห้ามเวลาในอดีต | §7.2 | `User/ReservationController.php:69` (`after:now`, `before: +1 day`) |
| Expire เมื่อเกิน 60 นาที (คงที่) | §7.2, §23 | `config/parking.php` = 60 ไม่อ่าน env |
| ห้าม hard delete Reservation (ยกเลิกแทน) | §7.5 | ไม่มี route destroy ของ reservation |
| Deposit = `hourly_rate × 1` เป็น Payment + void rules | §8, §13.2 | `ReservationService` |
| `reservation_fee` = ส่วนลด เฉพาะการจองที่มีมัดจำ | §9 | Walk-in `reservation_fee = 0` + CHECK constraint |
| Accuracy > 85% / อ่านไม่ได้ → แจ้ง Owner+Admin | §10.3–10.4 | `LicensePlateScan::classify`, `CarScanService::alertStaff` |
| Matching = ทะเบียน+จังหวัด AND (ยี่ห้อ OR สี) | §10.6 | `AutoCheckInService` |
| ทะเบียนไม่ตรง → Walk-in | §10.7 | `AutoCheckInService` |
| ตรวจ Lot scope | §10.8 | `AutoCheckInService.php:51,163` |
| กรณีพิเศษ Auto Check-in ทั้งตาราง | §10.10 | audit keys `ai_scan.early_arrival` / `vehicle_mismatch` / `booking_not_used` |
| สแกนได้ทุก Role ทุกลาน (จำลองกล้อง) | §10.2 | `CarScanController` |
| Walk-in = Reservation, ลานเต็ม → ไม่บันทึกอะไร ยกเว้น Blacklist | §11 | `CheckInService::checkInWalkIn`, `CarScanService::discardForFullLot` |
| Checkout ลำดับการหัก, ยอด 0 → paid โดยระบบ | §12.3, §12.5 | `CheckOutService.php:47,58–62` |
| Auto Check-out ตรวจทิศทาง / แจ้งเมื่อไม่สำเร็จ | §12.5 | `ScanGateService` (`parked_elsewhere`, `check_out_failed`) |
| Payment unpaid / paid / void | §13, §20 | `Payment::STATUS_*` |
| Blacklist ด้วยทะเบียน+จังหวัด, ไม่ Block | §10.9, §14 | `CarScanService.php:195–205` |
| ผู้รับแจ้งเตือนตาม §15.4 (ไม่แจ้ง Owner เมื่อจองใหม่) | §15.4 | ไม่มี `notify` ใน `ReservationService::create` · `NotificationPolicyTest` |
| คำร้องลาออก + ลำดับการปิดลาน | §16, §16.1 | `OwnerResignationService`, `OwnerLotClosureService` |
| CSV Admin-only + BOM + กัน formula injection | §17.5 | `CsvExport.php:41` |
| Audit Log ทุก Role + System, Owner ไม่เห็น Audit Log | §18.1 | routes: admin-actions เฉพาะ `role:admin` |
| Email verification, Force reset ทุก Role, เอกสาร private | §19.4 | ดู §3 |
| SSL/TLS ปรับได้ตาม Environment | §19.3 | `config/carscan.php:36` (`CARSCAN_VERIFY_SSL`, `.env.example` = true) |
| ไม่มีรหัสผ่านใน source ปัจจุบัน | §25.4 | `phpunit.xml:28` |
| CI บน main + PR (PHP 8.4 + PostgreSQL) | §25.4 | `tests.yml` · GitHub run ล่าสุด success (2026-09-17 16:51 UTC) |

### 🟡 ตรงบางส่วน

| Requirement | § | Current behavior | Gap |
|---|---|---|---|
| User Dashboard ควรแสดง Notification | §17.3 | Notification เข้าถึงผ่านแถบนำทาง (กระดิ่ง/แท็บล่าง) และหน้า `/notifications` ส่วนหน้า `dashboard-user.blade.php` ไม่มีส่วนแสดงการแจ้งเตือน (grep ไม่พบ) | แสดงไม่ได้อยู่ "บน Dashboard" ตามตัวอักษร — ต้องตัดสินใจว่าแถบนำทางถือว่าตรงหรือไม่ |
| Owner Dashboard "สถิติรายวัน/รายเดือน" | §17.2 | Dashboard แสดงรายได้วันนี้ ส่วนแนวโน้ม 12 เดือนอยู่ที่หน้า Revenue | ครบเมื่อรวมหน้า Revenue แต่ไม่ได้อยู่บน Dashboard หน้าเดียว |

### 🔴 ยังไม่ตรง

**ไม่พบ** — ทุกกฎที่ตรวจตรงกับ requirement ฉบับล่าสุด

> หมายเหตุ: ใน `project-plan.md` มีจุดที่ขัดกันเองในเอกสาร ได้แก่ §15.2 ยังเขียนว่าแจ้ง Owner เมื่อ "มี Reservation ในลาน" แต่ §15.4 (ตัดสินภายหลัง) ระบุว่าไม่แจ้ง และมีหัวข้อ §10.2 ซ้ำ 2 ครั้ง โค้ดทำตาม §15.4 ส่วนการแก้ requirement ต้องให้เจ้าของเอกสารตัดสินใจ

### ⏳ Requirement ที่ยังไม่ได้ implement

| Requirement | § | สถานะ |
|---|---|---|
| Marketplace แบบใช้ GPS แนะนำลานใกล้เคียง | §3.2 | Future Scope — มีหน้ารายการลาน แต่ไม่มีตำแหน่ง GPS (ไม่มีคอลัมน์ lat/lng ใน `parking_lots`) |
| กล้องจริง / Barrier gate / Payment gateway / Slip verification | §3.3, §13.5 | นอกขอบเขตโดยเจตนา |

---

## 5. Problems Introduced / Remaining Problems

**ไม่มี Critical** · ห้ามแก้ในงานนี้

| Problem | พบที่ไหน | เกี่ยวกับ Phase ไหน | Severity | สถานะ |
|---|---|---|:-:|---|
| Dependency มี **36 security advisories ใน 10 package** — high: `league/commonmark` 8, `guzzlehttp/guzzle` 1, `laravel/framework` 1, `symfony/http-kernel` 1, `symfony/mime` 1 | `composer audit --locked` (2026-09-18) · `npm audit --omit=dev` = 0 | Maintenance (ไม่ได้อยู่ใน Phase ใด) | **High** | Open |
| หน้า User Dashboard บนมือถือ **ล้นจอแนวนอน** +108px (390px) / +123px (375px) | `resources/views/dashboard-user.blade.php` — section "ลานที่จองได้ตอนนี้" กว้าง 482px | 14 (UI Phase 4) | **High** | Open — รายละเอียดในไฟล์ UI |
| รหัสผ่าน DB เดิมยังอยู่ในประวัติ git ของ repo **public** | `git log -S` → `9c93881` (เพิ่ม), `5c217a4` (ลบ) · GitHub API `visibility=public` | 15 | Medium | Open (code ปัจจุบันสะอาดแล้ว แต่ประวัติยังอยู่) |
| ไม่มี rate limit บน route สแกนที่เรียก Claude API (ทุก Role ใช้ได้) | `routes/web.php` — throttle มีแค่ verification (`throttle:6,1`) และ login rate limiter | 4 | Medium | Open |
| ไฟล์ภาพของผลสแกนใน seed ไม่มีอยู่จริง → หน้าประวัติสแกนเกิด 403 | ฐาน dev `license_plate_scans.image_path` หาย 20/20 · crawl ฐานทดสอบ: console 403 ที่ `/owner/scan/history`, `/admin/scan/history` | 4 (data/seed) | Medium | Open |
| อีเมลแสดงชื่อ "Smart-Parking" แทนชื่อที่ผูกพัน "Smart Parking System" | `.env`/`.env.example` `APP_NAME=Smart-Parking` → `MAIL_FROM_NAME`, `vendor/mail/html/message.blade.php:24` | 16 / 14 | Medium | Open (Brand commitment ใน PRODUCT.md) |
| Migration แบบ create ถูกแก้ย้อนหลัง | `database/migrations/2026_02_18_*` (แก้ใน `a285e86`) | 1 | Low | ยอมรับแล้ว (ใช้ `migrate:fresh`) — technical debt |
| Model ใช้ `$guarded = []` ทุกตัว | 13 ไฟล์ใน `app/Models` | ก่อน Phase 0 | Low | Open — technical debt |
| `.env` ยังมี `RESERVATION_GRACE_PERIOD=30` ที่ไม่ถูกอ่าน | `.env:60` | 6 | Low | Open (config ถูกต้องแล้ว) |
| `reservation_logs.note` ถ้อยคำเก่า (อังกฤษ) ในฐาน dev | 70 แถว | 8 (data) | Low | Open — ข้อมูลเก่า ไม่ใช่ code |
| ตัวเลข test ล้าสมัยในเอกสาร (272 แต่จริง 325) | `README.md:8,241` · `docs/TEST_COVERAGE.md:4` · `docs/PRODUCT.md:59` | 15, 16 | Low | Open |
| Requirement doc ขัดกันเอง (§15.2 vs §15.4) และหัวข้อ §10.2 ซ้ำ | `docs/project-plan.md` | 9 (เอกสาร) | Low | Open |
| ไฟล์โลโก้เก่าไม่มีการอ้างอิง | `public/images/logo.png`, `logo-email.png` (tracked, 0 refs) | 16 | Low | Open — legacy asset |
| `APP_DEBUG=true` เป็นค่าใน `.env.example` | `.env.example:4` | 16 | Low | Open (เหมาะกับ local เท่านั้น) |
| CI บน `main` เคย fail 2 ครั้งก่อน merge | GitHub runs 2026-09-16 02:42Z, 2026-09-17 02:46Z = failure · run ล่าสุด 2026-09-17 16:51Z = success | 15 | Low | Resolved (run ล่าสุดผ่าน) |
| E2E เคยมีบันทึกว่า timeout ครั้งแรกหลัง reseed | ไม่เกิดในรอบตรวจนี้ (2/2 passed) | 15 | Low | **ยังไม่ยืนยัน** |

**ไม่ใช่ปัญหา (ตรวจแล้ว):**
- `storage/{path}` ของ disk `local` ต้องมี signed URL (`vendor/.../ServeFile.php`) — ไฟล์ private ไม่หลุด
- การสแกนลานของ Owner อื่นได้ — เป็น requirement §10.2
- `sk-ant-` ในประวัติ git เป็นข้อความ placeholder `sk-ant-...` ไม่ใช่ key จริง

---

## 6. Test Status

**จุดอ้างอิง:** HEAD `5f0c73c`, รันเมื่อ 2026-09-17 23:54 – 2026-09-18 00:05

| รายการ | ผล | รายละเอียด |
|---|---|---|
| PHPUnit | ✅ **325 passed** | 1,849 assertions · 0 failed · 0 skipped · 87.34s · ฐาน `smart_parking_test` · 46 ไฟล์ Feature test |
| Playwright E2E | ✅ **2 passed** (2.4 นาที) | `reservation-flow.test.js`, `walk-in-flow.test.js` · `migrate:fresh --seed` ฐานทดสอบก่อนรัน |
| Build (`vite build`) | ✅ ผ่าน (13.89s) | build ลง scratchpad · hash ของ `public/build/manifest.json` ตรงกับ build ใหม่ = asset ที่ commit ไว้ไม่ล้าสมัย |
| View cache (`view:cache`) | ✅ ผ่าน | compile 163 ไฟล์ลง scratchpad |
| Browser console errors | ⚠️ พบ 4 view | HTTP 403 ของไฟล์ภาพสแกนที่หาย (`/owner/scan/history`, `/admin/scan/history`) — ไม่มี JS error หรือ pageerror |
| CI (GitHub Actions) | ✅ ล่าสุด success | `Tests` บน `main` 2026-09-17 16:51 UTC |
| `composer audit` | ⚠️ | 36 advisories / 10 packages |
| `npm audit --omit=dev` | ✅ | 0 |

**Test ที่ครอบคลุมตาม §25.1:** Authentication, Reservation, Reservation Time, Deposit, Cancel Deposit, Slot Assignment/Lock, Auto Check-in, AI Accuracy/Matching/No Match/Read Error, Walk-in, Blacklist, Checkout, Payment, Notification, Owner/Admin Scope, Security (ดูชื่อไฟล์ใน §3)

**Test ที่ยังไม่มี (ตรวจจากรายชื่อไฟล์ test):**
- Concurrency จริง (2 request พร้อมกันแย่ง Slot) — มีเพียง lock ใน code, ไม่พบ test แบบ parallel
- Rate limit / ปริมาณการเรียก AI — ไม่มี feature นี้จึงไม่มี test
- Responsive / Accessibility อัตโนมัติใน test suite — ไม่มีใน PHPUnit/E2E (ตรวจแยกในไฟล์ UI)
- Unit test suite — ถูกลบออก เหลือ Feature test อย่างเดียว

---

## 7. Database / Architecture Status

### Schema / Migrations

- **22 migrations** — `migrate:status` บนฐาน dev = Ran ทั้งหมด (batch 1–5)
- **13 business tables:** `users`, `parking_lots`, `parking_slots`, `reservations`, `parking_logs`, `payments`, `license_plate_scans`, `suspicious_vehicles`, `reservation_logs`, `admin_actions`, `notifications`, `owner_applications`, `owner_resignations` + system tables (`sessions`, `cache`, `jobs`, `password_reset_tokens`)
- **Migration ที่ควรรู้:**
  - `2026_02_18_*` (create) ถูกแก้ in place ใน Phase 1 — ไม่ใช่ obsolete แต่ upgrade จากฐานเก่าไม่ได้
  - `2026_09_13_000000_insert_walkin_system_user` — data migration สร้างบัญชีระบบ
  - `2026_09_14_000100_move_owner_application_documents_to_private_disk` — data migration ย้ายไฟล์ (มี side effect ต่อ storage)
  - ไม่พบ migration ที่ obsolete (ไฟล์ Vehicle และ incremental เก่าถูกลบแล้ว)

### Models / Relationships

13 models (`AdminAction`, `LicensePlateScan`, `Notification`, `OwnerApplication`, `OwnerResignation`, `ParkingLog`, `ParkingLot`, `ParkingSlot`, `Payment`, `Reservation`, `ReservationLog`, `SuspiciousVehicle`, `User`) — ไม่มี `Vehicle`, `Role`, `Permission`

### Services / Queries / Support

- **Services (10):** `ReservationService`, `SlotAllocator`, `CarScanService`, `AutoCheckInService`, `CheckInService`, `CheckOutService`, `ScanGateService`, `OwnerResignationService`, `OwnerLotClosureService`, `UserAccountService` — ทุกตัวถูกอ้างอิงจาก controller/service อื่น
- **Queries (3):** `AdminExportQuery`, `ReservationLogQuery`, `RevenueQuery`
- **Support (7):** `AuditCatalog`, `CsvExport`, `Format`, `Navigation`, `StatusCatalog`, `audit_log.php`, `notify_user.php`

### Controllers / Routes / Middleware

- **Controllers 40 ไฟล์** — ทุกไฟล์มี route อ้างอิง (ไม่พบ controller กำพร้า)
- **Routes 125:** admin 54 · owner 32 · user 10 · auth/guest/public ที่เหลือ
- **Middleware 4:** `RoleMiddleware`, `OwnerApprovedMiddleware`, `ForcePasswordReset`, `EnsureNotSystemUser`
- Route พิเศษ `/_ui` (dev showcase) ลงทะเบียนเฉพาะ `app()->environment('local')` (`routes/web.php:40`)
- ไม่มี Policy/Gate — ตรวจสิทธิ์ใน controller/service ตามแนวทางเดิม (technical debt ระดับ Low ไม่ขัด requirement)

### Factories / Seeders

6 factories (ทุกตัวถูกใช้ใน test) · `DatabaseSeeder` 1 ไฟล์ — ไม่พบการอ้าง Vehicle

---

## 8. Legacy / Dead Code

| Category | ผลตรวจ | Evidence |
|---|---|---|
| Vehicle legacy | ✅ ไม่พบ | grep `vehicle_id`, `Models\Vehicle` ใน app/routes/database/resources/tests/config/e2e — พบเฉพาะ `DataModelTest` ที่ assert ว่า**ไม่มี**คอลัมน์ |
| Role / Permission legacy | ✅ ไม่พบ | ไม่มี model/migration/การอ้างอิง |
| Old middleware / helper | ✅ ไม่พบ | `AdminMiddleware`, `OwnerMiddleware`, `admin_audit(` = 0 |
| Old routes (admin create reservation, user vehicles, manual confirm) | ✅ ไม่พบ | route list |
| Old tests / E2E | ✅ ไม่พบ | E2E เหลือ 2 flow · ไม่มี test อ้าง API เก่า |
| Old factories | ✅ ไม่พบ | ไม่มี `VehicleFactory` · factory ทุกตัวมีการใช้ |
| Old screenshots / reports | ✅ ไม่ track | `e2e/reports/html`, `e2e/screenshots/playwright-output` มีในเครื่อง (สร้าง 2026-09-17 19:40) แต่อยู่ใน `e2e/.gitignore` |
| Obsolete assets | ⚠️ พบ | `public/images/logo.png`, `public/images/logo-email.png` — tracked แต่ไม่มีการอ้างอิงใน app/resources/config |
| Stale config key | ⚠️ พบ | `.env:60 RESERVATION_GRACE_PERIOD` |
| Legacy UI (CSS/components) | ดูไฟล์ UI | — |

---

## 9. Security / Reliability Issues

รวมเฉพาะที่มีหลักฐาน

| Issue | Evidence | Severity |
|---|---|:-:|
| Dependency vulnerabilities (36 advisories / 10 packages) | `composer audit --locked` | High |
| รหัสผ่าน DB อยู่ในประวัติ git ของ public repo | commits `9c93881` / `5c217a4` · repo visibility public | Medium |
| ไม่มี rate limit ต่อการเรียก AI scan (ค่าใช้จ่าย API / การใช้งานเกิน) | ไม่มี `throttle` บน `*/scan` ใน route list | Medium |
| `CARSCAN_VERIFY_SSL=false` ใน `.env` เครื่อง dev | `.env:57` (`.env.example` = true) | Low (local only) |
| `APP_DEBUG=true` ในค่าเริ่มต้น | `.env`, `.env.example` | Low |
| Mass assignment เปิด (`$guarded = []`) | 13 models | Low |

**ตรวจแล้วเรียบร้อย:**

| หัวข้อ | Evidence |
|---|---|
| Authorization ตาม Role | middleware เดียว + `AuthorizationTest` |
| Ownership scope | `ParkingLot::ownedBy/unowned` + test |
| Cross-lot check-in อัตโนมัติ | ถูกกันด้วย Lot scope §10.8 |
| Secret ใน source ปัจจุบัน | `phpunit.xml` ไม่มีรหัสผ่านแล้ว · `.env` ไม่ถูก track |
| File exposure | เอกสาร Owner อยู่ private disk · `storage/{path}` ต้อง signed URL |
| Session/Auth | ยืนยันอีเมล · Force reset ทุก Role · Walkin User ถูกบังคับออกจากระบบ |
| Logging | Audit ไม่เก็บ IP ของ system actor ตาม §18.1 |
| CSV injection | ป้องกันด้วย prefix `'` (`CsvExport.php:41`) |

---

## 10. Ready for Next Phase?

**ไม่มี Phase ถัดไปที่กำหนดไว้ใน repo** (§31 ไม่มีประเด็นค้าง · Marketplace = Future)

| หมวด | รายการ |
|---|---|
| **พร้อมแล้ว** | Core Flow ครบ: จอง → มัดจำ → AI Scan → Auto Check-in/Walk-in → Check-out → Payment · Test 325 + E2E 2 ผ่าน · CI ผ่าน · Legacy ของระบบเก่าหมดจาก code |
| **ควรแก้ก่อนใช้งานเดโม/ส่งงาน** | Dashboard ของ User ล้นจอบนมือถือ (High, UI) · ไฟล์ภาพ seed ของผลสแกนหาย (หน้าประวัติสแกนมีภาพเสีย) · ชื่อแบรนด์ในอีเมล "Smart-Parking" |
| **ควรทำก่อนนำไป deploy จริง** (ปัจจุบันไม่มีแผน deploy ตาม PRODUCT.md) | อัปเดต dependency ที่มี advisory · เปลี่ยนรหัสผ่านฐานข้อมูลที่หลุดในประวัติ git · rate limit ของ AI scan · `APP_DEBUG=false` |
| **เลื่อนได้** | อัปเดตตัวเลข test ในเอกสาร · ลบโลโก้เก่า · `.env` key ที่ไม่ใช้ · `$guarded = []` · ความไม่สอดคล้องในเอกสาร requirement |
| **Blocker** | **ไม่มี** — ไม่มีรายการใดขวางการทำงานของ Core Flow หรือการรัน test |
| **Dependency ของงานอนาคต** | Marketplace ต้องมีข้อมูลตำแหน่งลาน (ยังไม่มีคอลัมน์) และต้องไม่กระทบ Core Flow ตาม §3.2 |

---

## 11. Final Snapshot

```text
Current Branch:        main (HEAD 5f0c73c — Merge PR #2, working tree clean)
Current Phase:         ไม่มี Phase ที่กำลังดำเนินการ — Phase 0–16 และ UI Phase 1–9 ส่งมอบและ merge แล้ว
Completed Phases:      13 ✅ (0, 1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13) + 4 ⚠️ Complete with Issues (4, 14, 15, 16)
Partial Phases:        0
Not Started:           0 (Marketplace = Future Scope ไม่นับเป็น Phase)
Blocked:               0
Tests:                 PHPUnit 325 passed / 1,849 assertions / 0 failed / 0 skipped
                       E2E 2/2 passed · build ✅ · view:cache ✅ · CI main ✅ (2026-09-17 16:51 UTC)
Known Critical Issues: ไม่มี Critical
                       High: dependency advisories 36 รายการ · User dashboard ล้นจอบนมือถือ (+108px ที่ 390px)
Main Remaining Work:   อัปเดต dependency · แก้ overflow หน้า User dashboard · ไฟล์ภาพ seed ของผลสแกน
                       · ชื่อแบรนด์ในอีเมล · rotate รหัสผ่าน DB ที่อยู่ในประวัติ git · rate limit AI scan
                       · อัปเดตตัวเลขในเอกสาร (272 → 325)
```
