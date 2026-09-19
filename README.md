# Smart Parking

ระบบจัดการลานจอดรถอัจฉริยะ พัฒนาด้วย **Laravel 12** + **Claude Vision AI**

![Laravel](https://img.shields.io/badge/Laravel-12-red)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-blue)
![Claude](https://img.shields.io/badge/AI-Claude%20Vision-blueviolet)
![Tests](https://img.shields.io/badge/tests-326%20passed-green)
![License](https://img.shields.io/badge/License-MIT-gray)

> **Requirement ฉบับเต็มอยู่ที่ `docs/project-plan.md`** (Source of Truth) — README นี้สรุปเฉพาะสิ่งที่ระบบทำได้จริงในโค้ดปัจจุบัน

---

## สารบัญ

1. [ภาพรวมระบบ](#ภาพรวมระบบ)
2. [Tech Stack](#tech-stack)
3. [Installation](#installation)
4. [Demo Accounts](#demo-accounts)
5. [Dev Commands](#dev-commands)
6. [Roles & Permissions](#roles--permissions)
7. [Business Logic](#business-logic)
8. [Database Schema](#database-schema)
9. [Routes](#routes)
10. [Testing](#testing)
11. [Project Structure](#project-structure)
12. [Environment Variables](#environment-variables)

---

## ภาพรวมระบบ

รถเข้าลานได้ 2 ทาง: **จองล่วงหน้า** หรือ **Walk-in** (ขับเข้ามาเลย) ทั้งสองทางจบลงที่ Reservation หนึ่งรายการเสมอ
กล้องที่ลาน (จำลองด้วยการอัปโหลดรูป) ส่งภาพให้ AI อ่านป้ายทะเบียน ระบบจึงเช็คอิน/เช็คเอาท์และจัดช่องจอดให้อัตโนมัติ

| Role | ทำอะไรได้ |
|---|---|
| **User** | จองล่วงหน้าไม่เกิน 1 วันโดยกรอกทะเบียนเอง, แก้ไขข้อมูลรถก่อนเช็คอิน, ยกเลิก, ดูประวัติการจอด, สแกนรถ, รับการแจ้งเตือน, สมัครเป็น Owner |
| **Owner** | จัดการลาน/ช่องจอดของตัวเอง, ดูการจองและประวัติของลานตัวเอง, ยืนยันรับเงิน, Manual Check-in/out, ดูรายได้, ยื่นคำร้องลาออก |
| **Admin** | ดูภาพรวมทั้งระบบทุกลาน, จัดการลานของ Admin (`owner_id = NULL`), จัดการผู้ใช้, อนุมัติคำขอ/คำร้องลาออกของ Owner, บัญชีดำ, Audit Log, CSV Export |

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | Blade, Tailwind CSS 3, Alpine.js, Vite 7, flatpickr |
| Database | PostgreSQL 18 |
| AI Vision | Anthropic Claude Vision (`anthropic-ai/sdk`) |
| Scheduler | Laravel scheduler (หมดอายุการจองอัตโนมัติทุกนาที) |
| Testing | PHPUnit (Feature) + Playwright (E2E) |

---

## Installation

```bash
git clone https://github.com/tpp72/smart-parking-system.git
cd smart-parking-system

composer install
npm install

cp .env.example .env
php artisan key:generate
```

แก้ไข `.env` (ดู [Environment Variables](#environment-variables)) แล้ว:

```bash
php artisan migrate --seed     # --seed เพื่อสร้างข้อมูลตัวอย่าง
php artisan storage:link
npm run build
php artisan serve
```

---

## Demo Accounts

`php artisan migrate:fresh --seed` จะสร้างข้อมูลจำลองทั้งระบบ — ทุกบัญชีรหัสผ่าน **`password`**

| Email | Role | หมายเหตุ |
|---|---|---|
| `admin@demo.com` | admin | — |
| `owner@demo.com`, `owner2@demo.com`, `owner3@demo.com` | owner | อนุมัติแล้ว มีลานจอดของตัวเอง |
| `user@demo.com` | user | ผู้ใช้ทั่วไป |
| `pending.owner@demo.com` | user | ยื่นคำขอเป็น Owner รออนุมัติ |
| `rejected.owner@demo.com` | user | คำขอถูกปฏิเสธ (มีเหตุผล) |

มีผู้ใช้ทั่วไปอีก ~22 บัญชี (`testuser{n}@example.com`) พร้อมการจอง/ประวัติจอด/Payment แบบสุ่ม
บัญชีระบบ `Walkin User` ใช้ผูกกับ Walk-in — เข้าสู่ระบบไม่ได้และไม่แสดงในหน้าจัดการผู้ใช้

---

## Dev Commands

```bash
php artisan serve              # [KEEP RUNNING] web server
npm run dev                    # [KEEP RUNNING] frontend hot reload
php artisan schedule:work      # [KEEP RUNNING] หมดอายุการจองอัตโนมัติ

php artisan migrate:fresh --seed   # [AS NEEDED] ล้างและ seed ข้อมูลใหม่
php artisan reservations:expire --dry-run   # [AS NEEDED] ดูว่ามีการจองไหนจะหมดอายุ
php artisan test                   # [AS NEEDED] PHPUnit
npm run test:e2e                   # [AS NEEDED] Playwright (เปิด server + seed ให้เอง)
```

---

## Roles & Permissions

- ควบคุมสิทธิ์ด้วย middleware `role:user` / `role:owner` / `role:admin` (+ `owner.approved` สำหรับเครื่องมือจัดการของ Owner)
- ต้องยืนยันอีเมลก่อนใช้งานระบบหลัก · Force Password Reset ใช้กับทุก Role รวม Admin
- **Ownership แยกจากสิทธิ์ระบบ:** Owner จัดการเฉพาะลานของตัวเอง · Admin จัดการเฉพาะลานของ Admin (`owner_id = NULL`) แต่ *ดู* ข้อมูลระดับระบบได้
- การเป็น Owner ต้องผ่านคำขอสมัครเท่านั้น — Admin ตั้งผู้ใช้เป็น Owner โดยตรงไม่ได้
- Admin ปลด Owner ต้องระบุเหตุผล และระบบจะปิดลานของ Owner แบบเดียวกับการอนุมัติคำร้องลาออก
- Admin เปลี่ยน Role ตัวเองออกจาก admin หรือลบบัญชีตัวเองไม่ได้

---

## Business Logic

### Reservation Lifecycle

```text
pending ──[Mark as Paid เงินมัดจำ]──→ confirmed ──[Check-in]──→ checked_in ──[Check-out]──→ completed
   │                                      │
   └──────── cancelled / expired ─────────┘
```

| สถานะ | ความหมาย |
|---|---|
| `pending` | จองแล้ว รอเจ้าหน้าที่ยืนยันรับเงินมัดจำ (ยังไม่ถือครองช่องจอด) |
| `confirmed` | ยืนยันรับเงินมัดจำแล้ว ระบบ Lock ช่องจอดให้ |
| `checked_in` | รถเข้าจอดแล้ว ช่องจอดเป็น `occupied` |
| `completed` | เช็คเอาท์แล้ว |
| `cancelled` / `expired` | ยกเลิก / ไม่มาภายในเวลาที่กำหนด — คืนช่องจอด |

**กฎเวลา:** จองได้เฉพาะเวลาในอนาคต ไม่เกิน 1 วันล่วงหน้า · เช็คอินได้ตั้งแต่เวลาจองจนถึง +60 นาที (ค่าคงที่ใน `config/parking.php`) · เกินจากนั้น scheduler เปลี่ยนเป็น `expired`

### เงินมัดจำและค่าจอด

- **มัดจำ** = `hourly_rate × 1` สร้างเป็น Payment ตั้งแต่ตอนจอง (จำลองการชำระ — เจ้าหน้าที่กด **Mark as Paid** เมื่อรับเงินจริง)
- มัดจำที่ยังไม่ชำระเมื่อยกเลิก/หมดอายุ → `void` · ที่ชำระแล้วไม่คืน
- ถ้าตอนกด Mark as Paid ลานเต็ม → ยกเลิกการจอง, มัดจำเป็น `void`, แจ้งผู้จอง
- **ค่าจอด** = ปัดขึ้นรายชั่วโมง ขั้นต่ำ 1 ชม. × อัตรา ณ เวลาเช็คอิน แล้วหักมัดจำที่ชำระแล้ว ตามด้วยส่วนลด `reservation_fee` — ยอดสุทธิไม่ติดลบ และถ้าเป็น 0 ระบบยืนยันชำระให้เอง
- **Walk-in** ไม่มีมัดจำและไม่มีส่วนลด

### ช่องจอด

ระบบเป็นผู้จัดช่องจอดเสมอ (ผู้ใช้เลือกได้แค่ลาน) · Lock ด้วย `FOR UPDATE SKIP LOCKED` กันการจองซ้ำ
`available → reserved` เมื่อยืนยันมัดจำ · `→ occupied` เมื่อเช็คอิน · `→ available` เมื่อเช็คเอาท์/ยกเลิก/หมดอายุ
ลบช่องจอดที่ `reserved` หรือ `occupied` ไม่ได้ · ลบลานที่ยังมีการจองค้างไม่ได้

### AI Scan → Auto Check-in / Check-out

1. อัปโหลดรูปรถ (จำลองกล้องที่ลาน) → Claude Vision อ่าน ทะเบียน / จังหวัด / ยี่ห้อ / สี / ความมั่นใจ
2. ความแม่นยำต้อง **มากกว่า 85%** จึงจะใช้เช็คอิน/เช็คเอาท์อัตโนมัติ ไม่ผ่านเกณฑ์หรืออ่านไม่ได้ → บันทึกผลและแจ้ง Owner + Admin
3. ถ้ารถคันนั้นจอดอยู่ในลานนี้ → **เช็คเอาท์อัตโนมัติ** พร้อมคำนวณค่าจอด
4. ถ้าไม่ได้จอดอยู่ → หาการจอง `confirmed` ของ ทะเบียน + จังหวัด ในลานนี้
   - ถึงเวลาจองแล้ว → เช็คอินด้วยการจองนั้น (ยี่ห้อและสีไม่ตรงทั้งคู่ ยังเช็คอินแต่แจ้งผู้ดูแลลาน)
   - มาก่อนเวลาจอง → ไม่เช็คอินอัตโนมัติ แจ้งผู้ดูแลลานให้ Manual Check-in
   - ไม่มีการจองใช้ได้ → **Walk-in** (ลานเต็มจะแจ้ง "ลานเต็ม" และไม่บันทึกผลสแกน)
5. ทะเบียนที่อยู่ในบัญชีดำ **ไม่ถูกบล็อก** แต่แจ้งเตือน Owner + Admin และบันทึกเหตุการณ์

### การแจ้งเตือน

ผู้จองได้รับแจ้งทุกเหตุการณ์ของการจองตัวเอง (ยืนยัน, ยกเลิก, หมดอายุ, เช็คอิน, เช็คเอาท์)
ผู้ดูแลลานได้รับแจ้งเฉพาะเรื่องผิดปกติ: รถต้องสงสัย, AI อ่านไม่ได้/ความแม่นยำต่ำ, รถมาก่อนเวลาจอง, ยี่ห้อ-สีไม่ตรง, เช็คเอาท์อัตโนมัติไม่สำเร็จ

### Audit Log

ทุกการกระทำสำคัญของ user / owner / admin และของระบบเอง บันทึกลง `admin_actions` (`auth.*`, `reservation.*`, `payment.*`, `ai_scan.*`, `parking_lot.*`, `parking_slot.*`, `user.*`, `suspicious_vehicle.*`, `owner_application.*`, `owner_resignation.*`, `profile.*`) — Admin ดูและ Export ได้ทั้งระบบ

---

## Database Schema

| ตาราง | ใจความสำคัญ |
|---|---|
| `users` | `role` (user/owner/admin), `owner_status`, `force_password_reset`, `is_system` (บัญชี Walk-in) |
| `parking_lots` | `owner_id` (NULL = ลานของ Admin), `hourly_rate`, `reservations_enabled` |
| `parking_slots` | `slot_number` ไม่ซ้ำในลานเดียวกัน, `status` available/reserved/occupied |
| `reservations` | ทะเบียน + จังหวัด + ยี่ห้อ + สี, `reserve_start`, `deposit_amount`, `reservation_fee`, `is_walk_in`, `status` |
| `parking_logs` | เวลาเข้า-ออกจริง, `hourly_rate` ณ เวลาเช็คอิน, ผูก 1:1 กับ reservation |
| `payments` | `type` deposit/checkout, `payment_status` unpaid/paid/void, `paid_by`, `paid_at`, ยอดและส่วนหัก |
| `reservation_logs` | ประวัติการเปลี่ยนสถานะการจอง (`changed_by` ว่าง = ระบบ) |
| `license_plate_scans` | ผล AI: ทะเบียน/จังหวัด/สี/ยี่ห้อ/ความมั่นใจ, `result` passed/low_accuracy/unreadable, `is_suspicious` |
| `suspicious_vehicles` | บัญชีดำตาม ทะเบียน + จังหวัด, ระดับความเสี่ยง, เปิด/ปิดได้ |
| `notifications` | การแจ้งเตือนรายบุคคล + สถานะอ่าน |
| `admin_actions` | Audit Log (`actor_role` user/owner/admin/system) |
| `owner_applications` | คำขอเป็นเจ้าของลาน + เอกสารแนบ (ไฟล์ส่วนตัว) |
| `owner_resignations` | คำร้องลาออกของ Owner (รอพิจารณาได้ครั้งละ 1 รายการ) |

ข้อมูลที่ผูกกับลานจะถูกลบตามเมื่อลบลาน และลานของ Owner จะถูกลบตามเมื่อลบบัญชี Owner

---

## Routes

```text
/                           → หน้าแรก
/marketplace                → รายชื่อลานจอด (สาธารณะ)
/dashboard                  → ส่งต่อไป dashboard ตาม role
/notifications              → การแจ้งเตือน (ทุก role)
/profile                    → โปรไฟล์ (ทุก role)

/admin/...                  → role: admin
  dashboard                 → ภาพรวมทั้งระบบ + Analytics
  parking-lots · parking-slots → จัดการลานของ Admin (+ bulk create ช่องจอด)
  users                     → จัดการผู้ใช้ + Force reset + ลบบัญชี
  reservations              → ดู/ยกเลิก/Manual Check-in/Check-out
  payments                  → ยืนยันรับเงิน (มัดจำ + ค่าจอด)
  parking-logs · reservation-logs · admin-actions → ประวัติและ Audit Log (+ CSV)
  exports                   → Export CSV (การจอง / ประวัติจอด / รายได้)
  scan · scan/history       → AI Scan + ประวัติการสแกน
  suspicious-vehicles       → บัญชีดำ
  owner-applications        → อนุมัติ/ปฏิเสธคำขอเป็น Owner
  owner-resignations        → อนุมัติ/ปฏิเสธคำร้องลาออก

/owner/...                  → role: owner
  dashboard · resignation   → ภาพรวม + ยื่นคำร้องลาออก
  parking-lots · parking-slots · reservations · reservation-logs
  payments · parking-logs · revenue · scan · scan/history

/owner/apply · /owner/application → สมัคร/ดูสถานะคำขอเป็น Owner (ผู้ใช้ทั่วไป)

/user/...                   → role: user
  dashboard · reservations (สร้าง/แก้ทะเบียน/ยกเลิก) · parking-logs · scan
```

---

## Testing

```bash
php artisan test        # PHPUnit — 326 passed
npm run test:e2e        # Playwright — 2 flows ตาม project-plan §25.2
```

- PHPUnit ใช้ฐานข้อมูล `smart_parking_test` (ตั้งใน `phpunit.xml`) และอ่าน `DB_PASSWORD` จาก `.env` / `.env.testing` — **ไม่มีรหัสผ่านใน repository**
- E2E เปิด PHP server เอง, `migrate:fresh --seed` ฐานทดสอบ และใช้ AI โหมดจำลอง (`CARSCAN_FAKE`) โดยอ่านผลจากชื่อไฟล์รูป `ทะเบียน__จังหวัด__ยี่ห้อ__สี__Accuracy.png`
- CI: `.github/workflows/tests.yml` รัน PHPUnit บน `main` และทุก Pull Request (PHP 8.4 + PostgreSQL)
- ตาราง Requirement ↔ Test: `docs/TEST_COVERAGE.md` · รายการทดสอบด้วยมือ: `docs/UAT_CHECKLIST.md`

---

## Project Structure

```text
app/
├── Console/Commands/ExpireReservations.php   ← หมดอายุการจอง (scheduler ทุกนาที)
├── Http/
│   ├── Controllers/{Admin,Owner,User}/       ← แยกตาม Role
│   ├── Controllers/CarScanController.php     ← AI Scan → ScanGateService
│   └── Middleware/{RoleMiddleware,EnsureOwnerApproved,ForcePasswordReset,EnsureNotSystemUser}.php
├── Models/                                   ← Reservation, ParkingLot, ParkingSlot, ParkingLog,
│                                                Payment, LicensePlateScan, SuspiciousVehicle,
│                                                OwnerApplication, OwnerResignation, AdminAction, ...
├── Queries/                                  ← ReservationLogQuery, RevenueQuery, AdminExportQuery
├── Services/
│   ├── ReservationService.php                ← สร้าง/ยืนยัน/ยกเลิก/หมดอายุ
│   ├── SlotAllocator.php                     ← จัดและ Lock ช่องจอด
│   ├── CheckInService.php · CheckOutService.php
│   ├── ScanGateService.php · AutoCheckInService.php · CarScanService.php
│   ├── OwnerResignationService.php · OwnerLotClosureService.php · UserAccountService.php
└── Support/{audit_log.php,notify_user.php}   ← global helpers

config/{parking.php,carscan.php,page_titles.php,thai_provinces.php,car_colors.php}
database/{migrations,factories,seeders}
resources/views/{admin,owner,user,scan,notifications,profile,auth,layouts,components,partials}
e2e/                                          ← Playwright (reservation-flow, walk-in-flow)
tests/Feature/                                ← PHPUnit
docs/                                         ← project-plan.md (Source of Truth), TEST_COVERAGE, UAT_CHECKLIST
```

---

## Environment Variables

| Variable | ค่าแนะนำ | คำอธิบาย |
|---|---|---|
| `APP_ENV` | `local` | `local` / `testing` เท่านั้นที่เปิดโหมดจำลอง AI ได้ |
| `DB_CONNECTION` | `pgsql` | ระบบใช้ PostgreSQL (partial index / CHECK constraint) |
| `DB_DATABASE` | `smart-parking-system` | ฐานข้อมูลหลัก (ฐานทดสอบคือ `smart_parking_test`) |
| `DB_USERNAME` / `DB_PASSWORD` | — | บัญชี PostgreSQL ของโปรเจกต์ (แนะนำบัญชีเฉพาะที่มีสิทธิ์ CREATEDB ไม่ใช่ superuser `postgres`) |
| `MAIL_*` | SMTP | ใช้ส่งอีเมลยืนยันตัวตนและรีเซ็ตรหัสผ่าน |
| `ANTHROPIC_API_KEY` | — | API Key ของ Claude ([console.anthropic.com](https://console.anthropic.com)) |
| `CARSCAN_MODEL` | `claude-opus-4-8` | โมเดลที่ใช้อ่านภาพ |
| `CARSCAN_VERIFY_SSL` | `true` | ปิดได้เฉพาะเครื่อง dev ที่ยังไม่ได้ตั้ง CA bundle |
| `CARSCAN_FAKE` | `false` | `true` = จำลองผล AI จากชื่อไฟล์ (ใช้กับ E2E/เดโม) |
| `E2E_DB_DATABASE` | `smart_parking_test` | ฐานข้อมูลที่ E2E ล้างและ seed (ชื่อต้องมีคำว่า `test`) |

> Grace period ของการเช็คอินเป็นค่าคงที่ 60 นาทีใน `config/parking.php` (ไม่ตั้งผ่าน `.env`)
