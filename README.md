# Smart Parking System

ระบบจัดการลานจอดรถอัจฉริยะ พัฒนาด้วย **Laravel 11** + **Google Gemini Vision AI**

![Laravel](https://img.shields.io/badge/Laravel-11-red)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-blue)
![Gemini](https://img.shields.io/badge/AI-Gemini%202.5%20Flash-orange)
![Tests](https://img.shields.io/badge/E2E-Playwright-green)
![License](https://img.shields.io/badge/License-MIT-gray)

---

## สารบัญ

1. [Features Overview](#features-overview)
2. [Tech Stack](#tech-stack)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [Dev Commands](#dev-commands)
6. [Roles & Permissions](#roles--permissions)
7. [Business Logic](#business-logic)
   - [Reservation Lifecycle](#reservation-lifecycle)
   - [Check-In Logic](#check-in-logic)
   - [Check-Out & Fee Calculation](#check-out--fee-calculation)
   - [Duplicate Guards](#duplicate-guards)
   - [Grace Period & Auto-Expire](#grace-period--auto-expire)
   - [AI Car Scan](#ai-car-scan)
   - [Payment System](#payment-system)
   - [Force Password Reset](#force-password-reset)
8. [Database Schema](#database-schema)
9. [E2E Testing (Playwright)](#e2e-testing-playwright)
10. [Project Structure](#project-structure)
11. [Environment Variables](#environment-variables)

---

## Features Overview

### Admin Features

| Feature | คำอธิบาย |
|---|---|
| **Dashboard KPI** | Slots available/occupied/reserved, รายได้ (วันนี้/7 วัน/เดือน), รถที่กำลังจอด, ค้างชำระ, การจองใหม่, AI scans ล่าสุด |
| **Parking Lots** | CRUD ลานจอด — ชื่อ, ที่อยู่, จำนวนช่อง, อัตรา/ชม. |
| **Parking Slots** | CRUD ช่องจอดทีละตัว หรือ bulk create (สร้างหลายช่องพร้อมกัน) |
| **Entry/Exit Devices** | CRUD อุปกรณ์สแกน entry/exit ของแต่ละลาน |
| **Users** | จัดการผู้ใช้ — แก้ไขชื่อ/อีเมล/role, force password reset |
| **Reservations** | CRUD การจองทั้งหมด — ยืนยัน, ค้นหา, กรองตามสถานะ/ลาน/วัน |
| **Reservation Logs** | Audit log ทุก status change พร้อม export CSV |
| **Admin Actions Log** | บันทึกทุก action ของ admin พร้อม export CSV |
| **Manual Check-In** | เช็คอินรถ — auto-detect reservation ที่ตรงกัน |
| **Manual Check-Out** | เช็คเอาท์รถ — คำนวณค่าจอด + สร้าง payment อัตโนมัติ |
| **Parking Logs** | ประวัติ check-in/out ทั้งระบบ กรองตามทะเบียน/วัน |
| **Vehicles** | CRUD ข้อมูลรถทั้งหมดในระบบ |
| **Payments** | ติดตาม unpaid/paid, mark as paid |
| **AI Car Scan** | อัปโหลดรูป → Gemini Vision → ได้ทะเบียน/สี/ยี่ห้อ พร้อม history |
| **Notifications** | ดูและ mark read การแจ้งเตือน |

### User Features

| Feature | คำอธิบาย |
|---|---|
| **Dashboard** | สถานะการจอดปัจจุบัน, การจองที่รออยู่, ประวัติล่าสุด, ลานที่มีช่องว่าง |
| **Reservations** | จองล่วงหน้า, ดูรายการจองทั้งหมด |
| **Vehicles** | เพิ่ม/ลบรถของตัวเอง |
| **Parking Logs** | ดูประวัติการจอดของรถตัวเอง |
| **AI Car Scan** | สแกนรถด้วย Gemini Vision (ฟีเจอร์เดียวกับ admin) |
| **Notifications** | ดูและ mark read การแจ้งเตือน |
| **Profile** | แก้ไขชื่อ, อีเมล, เปลี่ยนรหัสผ่าน |

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Laravel 11 |
| Frontend | Blade, Tailwind CSS, Alpine.js, Vite 7 |
| Database | PostgreSQL 18 |
| AI Vision | Google Gemini 2.5 Flash |
| Auth | Laravel Breeze |
| Scheduler | Laravel built-in scheduler (auto-expire reservations) |
| E2E Testing | Playwright (multi-browser, multi-viewport) |

---

## Requirements

- PHP 8.4+ (extensions: pdo_pgsql, gd, fileinfo, zip)
- PostgreSQL 15+
- Node.js 18+
- Composer
- Google Gemini API Key — [aistudio.google.com/apikey](https://aistudio.google.com/apikey)

---

## Installation

```bash
# 1. Clone
git clone https://github.com/tpp72/smart-parking-system.git
cd smart-parking-system

# 2. Dependencies
composer install
npm install

# 3. Environment
cp .env.example .env
php artisan key:generate
```

แก้ไข `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=smart-parking-system
DB_USERNAME=postgres
DB_PASSWORD=your_password

GEMINI_API_KEY=AIzaSy...
CARSCAN_MODEL=gemini-2.5-flash

# Grace period สำหรับ late check-in (นาที, default 30)
RESERVATION_GRACE_PERIOD=30
```

```bash
# 4. Database + Build
php artisan migrate
php artisan storage:link
npm run build

# 5. Run
php artisan serve
```

---

## Dev Commands

> **Legend:**
> - `[SETUP]` — รันครั้งเดียวตอน clone/setup project ใหม่
> - `[DAILY]` — รันทุกครั้งที่เปิด dev session
> - `[KEEP RUNNING]` — รันทิ้งไว้ตลอด ห้ามปิด terminal
> - `[AS NEEDED]` — รันเมื่อต้องการเท่านั้น

---

### Setup ครั้งแรก (รันตามลำดับ)

```bash
composer install              # [SETUP] ติดตั้ง PHP dependencies
npm install                   # [SETUP] ติดตั้ง Node dependencies
cp .env.example .env          # [SETUP] สร้างไฟล์ config (แก้ไขค่าใน .env ก่อน)
php artisan key:generate      # [SETUP] สร้าง APP_KEY
php artisan storage:link      # [SETUP] สร้าง symlink สำหรับ file upload
php artisan migrate           # [SETUP] สร้างตาราง database ทั้งหมด
npm run build                 # [SETUP] build frontend assets ครั้งแรก
```

---

### เปิด Dev Server ทุกวัน (เปิด 3 terminal)

```bash
# Terminal 1 — [KEEP RUNNING] Web server
php artisan serve
# → http://127.0.0.1:8000
```

```bash
# Terminal 2 — [KEEP RUNNING] Frontend hot reload
npm run dev
# → ใช้แทน npm run build ตอน dev (CSS/JS อัปเดตอัตโนมัติ)
```

```bash
# Terminal 3 — [KEEP RUNNING] Scheduler (auto-expire reservations)
php artisan schedule:work
# → จำเป็นสำหรับ auto-expire reservation
# → ถ้าไม่ได้ทดสอบฟีเจอร์การจอง ไม่ต้องเปิดก็ได้
```

---

### Database

```bash
php artisan migrate                  # [AS NEEDED] รัน migration ใหม่
php artisan migrate:rollback         # [AS NEEDED] ย้อน migration ล่าสุด 1 ขั้น
php artisan migrate:fresh            # [AS NEEDED] ⚠️ ลบทุกตารางแล้วสร้างใหม่
php artisan migrate:fresh --seed     # [AS NEEDED] ⚠️ สร้างใหม่ + seed data
```

---

### Cache

```bash
php artisan config:cache    # [AS NEEDED] cache config (รันหลังแก้ .env)
php artisan config:clear    # [AS NEEDED] ล้าง config cache
php artisan cache:clear     # [AS NEEDED] ล้าง application cache
php artisan route:clear     # [AS NEEDED] ล้าง route cache
php artisan view:clear      # [AS NEEDED] ล้าง compiled view cache
```

---

### Build

```bash
npm run build    # [AS NEEDED] build สำหรับ production
npm run dev      # [KEEP RUNNING] hot reload ตอน dev
```

---

### Tinker / Debug

```bash
php artisan tinker    # [AS NEEDED] REPL สำหรับรัน PHP code ทดสอบ

# ตัวอย่าง: ทดสอบ auto-expire reservations
php artisan reservations:expire --dry-run
php artisan reservations:expire
```

---

### E2E Testing (Playwright)

```bash
# [SETUP] รันครั้งแรก หรือหลังเปลี่ยน password ของ test user
npm run test:e2e:setup

# ต้องรัน php artisan serve ก่อนเสมอ

npm run test:e2e             # [AS NEEDED] รัน test ทุก browser
npm run test:e2e:ui          # [AS NEEDED] รันพร้อม UI (เลือก test ได้)
npm run test:e2e:headed      # [AS NEEDED] รันแบบเห็น browser จริง
npm run test:e2e:debug       # [AS NEEDED] debug ทีละ step
npm run test:e2e:chromium    # [AS NEEDED] รันเฉพาะ Chrome
npm run test:e2e:firefox     # [AS NEEDED] รันเฉพาะ Firefox
npm run test:e2e:webkit      # [AS NEEDED] รันเฉพาะ Safari
npm run test:e2e:mobile      # [AS NEEDED] รันเฉพาะ mobile viewport
npm run test:e2e:report      # [AS NEEDED] เปิด HTML report ล่าสุด
```

---

## Roles & Permissions

| Role | สิทธิ์ |
|---|---|
| **admin** | จัดการทุกอย่าง: ลานจอด, ช่องจอด, อุปกรณ์, ผู้ใช้, การจอง, check-in/out, payments, AI scan |
| **user** | จองล่วงหน้า, ดูประวัติ, จัดการรถของตัวเอง, AI scan |

**หมายเหตุ:** Admin ไม่สามารถเปลี่ยน role ของตัวเองจาก admin → user ได้ (ป้องกัน lock-out ตัวเอง)

---

## Business Logic

### Reservation Lifecycle

```
[User สร้าง]
     ↓
  pending ──[Admin ยืนยัน]──→ confirmed
     │                            │
     │                    [รถ check-in ภายใน grace period]
     │                            ↓
     │                        checked_in  ← checked_in_at set
     │                            │
     │                    [รถ check-out]
     │                            ↓
     │                        completed   ← completed_at set
     │
     ├──[admin/user ยกเลิก]──→ cancelled
     │
     └──[scheduler, เกิน grace period]──→ expired
```

**สถานะทั้งหมด:**

| สถานะ | ความหมาย | trigger |
|---|---|---|
| `pending` | รอ admin ยืนยัน | user สร้างการจอง |
| `confirmed` | ยืนยันแล้ว รอรถมา | admin กด confirm |
| `checked_in` | รถเข้าจอดแล้ว | check-in อัตโนมัติเมื่อรถมาถึง |
| `completed` | เสร็จสมบูรณ์ | check-out อัตโนมัติ |
| `expired` | หมดอายุ ไม่มา | scheduler (เกิน grace period) |
| `cancelled` | ยกเลิก | admin หรือ user |

---

### Check-In Logic

เมื่อ admin กด Check-In สำหรับรถ:

1. ตรวจสอบว่ารถยังไม่ได้อยู่ในระบบ (ไม่มี `parking_log` ที่ยังไม่ได้ check-out)
2. ค้นหา `confirmed` reservation สำหรับรถนี้ที่อยู่ในช่วง grace period:
   - `reserve_start ≤ now + 5 นาที` (รองรับ early check-in 5 นาที)
   - `reserve_start ≥ now - GRACE_PERIOD` (ยังไม่เลย grace period)
3. **ถ้าพบ reservation:**
   - ใช้ lot ที่ reserve ไว้ (override lot ที่ admin เลือก)
   - ถ้า reserve specific slot → ลอง slot นั้นก่อน
   - ถ้า slot ถูกใช้ไปแล้ว หรือ reserve แค่ lot → หา slot ว่างในลานนั้น
   - สร้าง `parking_log` พร้อม `reservation_id`
   - อัปเดต reservation → `checked_in` + บันทึก `checked_in_at`
   - บันทึก `ReservationLog` (auto check-in)
4. **ถ้าไม่พบ reservation (walk-in):**
   - หา slot ว่างใน lot ที่ admin เลือก
   - สร้าง `parking_log` โดยไม่มี `reservation_id`
5. อัปเดต slot status → `occupied`

---

### Check-Out & Fee Calculation

เมื่อ admin กด Check-Out:

1. ตรวจสอบว่ายังไม่ได้ check-out และไม่มี payment ซ้ำ
2. คำนวณค่าจอด:
   ```
   minutes = check_out_time - check_in_time
   hours   = max(1, ceil(minutes / 60))   ← ปัดขึ้นเสมอ, ขั้นต่ำ 1 ชม.
   fee     = hours × hourly_rate
   ```
3. สร้าง `Payment` (status: `unpaid`)
4. คืน slot → `available`
5. **ถ้า parking_log มี reservation_id:**
   - อัปเดต reservation → `completed` + บันทึก `completed_at`
   - บันทึก `ReservationLog` (auto completed)
6. Expire reservations ของรถนี้ที่ยังค้างอยู่และเลย grace period แล้ว

**ตัวอย่างคำนวณ:**

| check-in | check-out | นาที | ชม. (ปัดขึ้น) | ค่าจอด (20 บ./ชม.) |
|---|---|---|---|---|
| 10:00 | 10:30 | 30 | 1 | 20 บาท |
| 10:00 | 11:01 | 61 | 2 | 40 บาท |
| 10:00 | 13:59 | 239 | 4 | 80 บาท |

---

### Duplicate Guards

**ฝั่ง User (ตรวจสอบตอนสร้างการจอง):**

| เงื่อนไข | ข้อความ error |
|---|---|
| รถคันนี้มี `pending`/`confirmed`/`checked_in` อยู่แล้ว | รถคันนี้มีการจองที่ยังดำเนินการอยู่ |
| User นี้มีการจองในช่วงเวลาเดียวกัน (ต่างรถ) | คุณมีการจองอื่นในช่วงเวลานี้อยู่แล้ว |
| รถคันนี้กำลังจอดอยู่ในระบบ | รถคันนี้กำลังจอดอยู่แล้ว |
| ช่องจอดนี้ถูกจองในช่วงเวลาเดียวกัน | ช่องจอดนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว |
| ช่องจอดไม่ได้อยู่ใน lot ที่เลือก | ช่องจอดนี้ไม่ได้อยู่ในลานที่เลือก |

**ฝั่ง Admin (ตรวจสอบตอนสร้าง/แก้ไขการจอง):**
- ตรวจ slot conflict เช่นเดียวกัน (slot + ช่วงเวลาทับซ้อน)
- ใช้ `reserve_start` window = `[reserve_start, reserve_start + 1 ชม.]`

**ฝั่ง Check-In:**
- ตรวจสอบว่ารถยังไม่ได้จอดอยู่ (`parking_log` ที่ยังไม่ check-out)
- `DB::lockForUpdate()` ป้องกัน race condition ตอนจอง slot

---

### Grace Period & Auto-Expire

**Grace Period** = ช่วงเวลาหลัง `reserve_start` ที่รถยังมาเช็คอินได้

- Default: **30 นาที**
- ตั้งค่าได้ใน `.env`: `RESERVATION_GRACE_PERIOD=30`
- ตั้งค่าใน `config/parking.php`

**Scheduler** (`reservations:expire`) รันทุก 1 นาที:
- หาการจองที่ `pending` หรือ `confirmed`
- และ `reserve_start + grace_period ≤ now()`
- อัปเดต → `expired` + บันทึก `ReservationLog` (old_status จริง ไม่ hardcode)
- **ข้ามสถานะ `checked_in`** — รถเข้าแล้ว ไม่ expire

```bash
# ทดสอบ scheduler แบบไม่แก้ข้อมูล
php artisan reservations:expire --dry-run

# รันจริง
php artisan reservations:expire
```

---

### AI Car Scan

1. อัปโหลดรูปรถ (jpg/jpeg/png, max **5 MB**)
2. ส่งไปยัง **Google Gemini Vision API**
3. ได้ผลลัพธ์:

| Field | ตัวอย่าง |
|---|---|
| `license_plate` | `5กก 6285` |
| `color` | `เงิน` |
| `brand` | `Honda` |
| `confidence` | `95` |

4. บันทึกลง `license_plate_scans` (source: `manual_upload`)
5. Admin: ดู scan history แบบ paginated + ค้นหาตามทะเบียน

**Models ที่รองรับ:**

| Model | Free Quota/วัน | หมายเหตุ |
|---|---|---|
| `gemini-2.5-flash` | 1,500 req | แนะนำ |
| `gemini-2.5-flash-lite` | 1,500 req | เร็วสุด |
| `gemini-2.5-pro` | น้อยกว่า | แม่นสุด |

เปลี่ยน model ได้ใน `.env`: `CARSCAN_MODEL=gemini-2.5-flash`

---

### Payment System

- Payment สร้างอัตโนมัติเมื่อ check-out
- สถานะ: `unpaid` → `paid`
- Admin กด "Mark Paid" เพื่อยืนยันการรับเงิน
- `reservation_discount` field มีอยู่ใน schema (รองรับส่วนลดในอนาคต, ปัจจุบัน = 0)

---

### Force Password Reset

Admin สามารถกำหนดรหัสผ่านชั่วคราวให้ user และบังคับเปลี่ยนรหัสผ่านก่อนเข้าใช้งาน:

1. Admin → Users → แก้ไข user → กำหนดรหัสผ่านชั่วคราว
2. User login ครั้งต่อไป → ถูก redirect ไปหน้า Profile ทันที
3. User เปลี่ยนรหัสผ่านแล้วจึงเข้า Dashboard ได้

---

## Database Schema

```
users
├── id, name, email, password, role (admin|user)
├── force_password_reset (boolean)
└── created_at, updated_at

vehicles
├── id, user_id → users
├── license_plate, brand, color
└── timestamps

parking_lots
├── id, name, location, total_slots
├── hourly_rate (decimal)
└── timestamps

parking_slots
├── id, parking_lot_id → parking_lots
├── slot_number, status (available|occupied|reserved)
└── timestamps

entry_exit_devices
├── id, parking_lot_id → parking_lots
├── device_type (entry|exit), location
└── timestamps

reservations ← LIFECYCLE: pending→confirmed→checked_in→completed / expired / cancelled
├── id, user_id → users, vehicle_id → vehicles
├── parking_lot_id → parking_lots, parking_slot_id → parking_slots (nullable)
├── reserve_start (timestamp)
├── checked_in_at (timestamp, nullable) ← set เมื่อ check-in
├── completed_at  (timestamp, nullable) ← set เมื่อ check-out
├── reservation_fee (decimal), status
└── timestamps

reservation_logs ← Audit trail ทุก status change
├── id, reservation_id → reservations
├── old_status (nullable), new_status
├── changed_by → users (nullable = auto)
├── note
└── timestamps

parking_logs ← Check-in/out records
├── id, vehicle_id → vehicles
├── parking_lot_id → parking_lots, parking_slot_id → parking_slots (nullable)
├── reservation_id → reservations (nullable) ← เชื่อม lifecycle
├── check_in_time, check_out_time (nullable)
└── timestamps

payments
├── id, parking_log_id → parking_logs
├── reservation_id → reservations (nullable)
├── total_hours, hourly_rate, parking_fee
├── reservation_discount (decimal, default 0)
├── total_amount, payment_status (unpaid|paid)
└── timestamps

license_plate_scans ← AI scan results
├── id, device_id → entry_exit_devices (nullable)
├── user_id → users (nullable)
├── vehicle_id → vehicles (nullable)
├── license_plate, color, brand, confidence
├── source (manual_upload|device)
├── scan_time
└── timestamps

suspicious_vehicles ← Blacklist
├── id, license_plate, added_by → users
└── timestamps

admin_actions ← Admin audit log
├── id, admin_id → users
├── action_type, target_type, target_id
├── details (json)
└── timestamps

notifications
├── id, user_id → users
├── title, message
├── read_at (nullable)
└── timestamps
```

---

## E2E Testing (Playwright)

### Test Users (ต้องมีในฐานข้อมูลก่อนรัน)

| Role | Email | Password |
|---|---|---|
| admin | admin@tester.com | Admin1234! |
| user | user@tester.com | User1234! |

### Test Suite Coverage

| Test | คำอธิบาย |
|---|---|
| Auth Setup | Login ทั้ง 2 roles + save session state |
| Crawl Dashboard | Crawl links จาก admin dashboard (max 60 หน้า) |
| All Admin Routes | ตรวจ 22 routes — HTTP status, JS errors, broken images |
| Guest Routes | ตรวจ 4 routes (/, /login, /register, /forgot-password) |
| Coverage Report | visitRoutes() ครบ 22 admin routes + สร้าง coverage-report.md |
| Responsive Layout | ตรวจ 5 key pages ที่ 375/768/1280px |
| Form Inventory | ตรวจ 5 create pages — มี form + submit button |
| Nav Integrity | ตรวจ nav links จาก dashboard |
| Loading Speed | ตรวจ 12 routes ต้องโหลด < 5 วินาที |

Reports สร้างที่ `e2e/reports/`:
- `bug-report.md` — issues found
- `coverage-report.md` — route coverage
- `e2e/logs/error-log.json` — console/network errors

### ผล E2E ล่าสุด

```
✓ 10/10 tests passed
✓ 0 bugs found
✓ 26/26 routes — 100% coverage
```

---

## Project Structure

```
app/
├── Console/Commands/
│   └── ExpireReservations.php     ← scheduler: auto-expire reservations
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── CheckInController.php      ← manual check-in + reservation link
│   │   │   ├── CheckOutController.php     ← checkout + fee + complete reservation
│   │   │   ├── ParkingLotController.php
│   │   │   ├── ParkingSlotController.php  ← single + bulk create
│   │   │   ├── ReservationController.php  ← CRUD + confirm
│   │   │   ├── ReservationLogController.php ← audit log + CSV export
│   │   │   ├── AdminActionController.php  ← admin audit + CSV export
│   │   │   ├── PaymentController.php
│   │   │   ├── UserController.php         ← user management + force reset
│   │   │   └── VehicleController.php
│   │   ├── User/
│   │   │   ├── ReservationController.php  ← create + duplicate guards
│   │   │   ├── VehicleController.php
│   │   │   └── ParkingLogController.php
│   │   ├── CarScanController.php          ← Gemini Vision AI
│   │   ├── DashboardController.php        ← admin + user dashboards
│   │   └── NotificationController.php
│   └── Middleware/
│       ├── AdminMiddleware.php
│       ├── RoleMiddleware.php
│       └── ForcePasswordReset.php
├── Models/
│   ├── Reservation.php    ← ACTIVE_STATUSES, gracePeriodMinutes(), scopes
│   ├── ParkingLog.php     ← reservation() relation
│   └── ...
└── Services/
    └── CarScanService.php ← Gemini Vision API logic

config/
├── parking.php    ← grace_period (RESERVATION_GRACE_PERIOD)
└── carscan.php    ← Gemini API key + model

e2e/
├── auth.setup.js          ← save auth sessions
├── ai-test.test.js        ← full test suite
├── utils/
│   ├── routes.js          ← all routes list
│   ├── crawler.js         ← link crawler
│   ├── errorMonitor.js    ← console/network error listener
│   ├── screenshot.js      ← screenshot helpers
│   ├── uiDetector.js      ← overflow/invisible text/broken images
│   ├── functional.js      ← form/button/nav collectors
│   └── reporter.js        ← generate bug/coverage reports
└── reports/               ← generated reports (gitignored)
```

---

## Environment Variables

| Variable | Default | คำอธิบาย |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | Database driver |
| `DB_HOST` | `127.0.0.1` | |
| `DB_PORT` | `5432` | |
| `DB_DATABASE` | `smart-parking-system` | |
| `DB_USERNAME` | `postgres` | |
| `DB_PASSWORD` | — | |
| `GEMINI_API_KEY` | — | **จำเป็น** — Google AI Studio |
| `CARSCAN_MODEL` | `gemini-2.5-flash` | Gemini model |
| `RESERVATION_GRACE_PERIOD` | `30` | Grace period (นาที) สำหรับ late check-in |
| `APP_KEY` | — | สร้างด้วย `php artisan key:generate` |

---

## Developer

Developed by [tpp72](https://github.com/tpp72)

## License

MIT License
