# Smart Parking System

ระบบจัดการลานจอดรถอัจฉริยะ พัฒนาด้วย **Laravel 12** + **Claude Vision AI**

![Laravel](https://img.shields.io/badge/Laravel-12-red)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-blue)
![Claude](https://img.shields.io/badge/AI-Claude%20Vision-blueviolet)
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
   - [User Reservation Management](#user-reservation-management)
   - [Suspicious Vehicle Management](#suspicious-vehicle-management)
   - [Dashboard Analytics Charts](#dashboard-analytics-charts)
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
| **Dashboard Charts** | Pie: สถานะการจอง · Bar: สถานะช่องจอด · Horizontal Bar: Top 5 ลานจอด (Chart.js 4) |
| **Parking Lots** | CRUD ลานจอด — ชื่อ, ที่อยู่, จำนวนช่อง, อัตรา/ชม. |
| **Parking Slots** | CRUD ช่องจอดทีละตัว หรือ bulk create (สร้างหลายช่องพร้อมกัน) |
| **Users** | จัดการผู้ใช้ — แก้ไขชื่อ/อีเมล/role, force password reset |
| **Reservations** | CRUD การจองทั้งหมด — ยืนยัน, ค้นหา, กรองตามสถานะ/ลาน/วัน |
| **Reservation Logs** | Audit log ทุก status change พร้อม export CSV |
| **Admin Actions Log** | บันทึกทุก action ของ admin พร้อม export CSV |
| **Manual Check-In** | เช็คอินรถ — auto-detect reservation ที่ตรงกัน |
| **Manual Check-Out** | เช็คเอาท์รถ — คำนวณค่าจอด + สร้าง payment อัตโนมัติ |
| **Parking Logs** | ประวัติ check-in/out ทั้งระบบ กรองตามทะเบียน/วัน |
| **Vehicles** | CRUD ข้อมูลรถทั้งหมดในระบบ |
| **Payments** | ติดตาม unpaid/paid, mark as paid |
| **Suspicious Vehicles** | บัญชีดำทะเบียนรถ — เพิ่ม/แก้ไข/ลบ/toggle, ระดับความเสี่ยง (low/medium/high), เชื่อมกับ AI scan |
| **AI Car Scan** | อัปโหลดรูป → Claude Vision → ได้ทะเบียน/สี/ยี่ห้อ พร้อม history |
| **Notifications** | ดูและ mark read การแจ้งเตือน |

### Owner Features

| Feature | คำอธิบาย |
|---|---|
| **Owner Application** | สมัครเป็น owner ผ่าน `/owner/apply` — รอ admin อนุมัติ |
| **Dashboard Charts** | Line: รายได้ 12 เดือน · Pie: สถานะการจอง · Bar: สถานะช่องจอด (เฉพาะลานของตัวเอง) |
| **Parking Lots** | สร้าง/จัดการลานจอดของตัวเอง |
| **Parking Slots** | สร้างช่องจอดทีละตัว หรือ bulk create |
| **Reservations** | ดูการจองทั้งหมดของลานตัวเอง, ยืนยันการจอง |
| **Revenue** | ดูรายได้ของลานจอด |
| **Marketplace** | ลานจอดจะแสดงใน Marketplace เมื่อ active |
| **Notifications** | รับแจ้งเตือนการจอง |

### User Features

| Feature | คำอธิบาย |
|---|---|
| **Dashboard** | สถานะการจอดปัจจุบัน, การจองที่รออยู่, ประวัติล่าสุด, ลานที่มีช่องว่าง |
| **Reservations** | จองล่วงหน้า (สูงสุด 24 ชม.) โดยกรอกทะเบียนรถโดยตรง, แก้ไขทะเบียนก่อน Check-In, ยกเลิก (pending/confirmed เท่านั้น) |
| **Parking Logs** | ดูประวัติการจอดของตัวเอง |
| **AI Car Scan** | สแกนรถด้วย Claude Vision — ระบบจับคู่การจองอัตโนมัติ + auto check-in |
| **Notifications** | รับแจ้งเตือนทุก event: ยืนยัน, ยกเลิก, หมดอายุ, check-in, check-out |
| **Profile** | แก้ไขชื่อ, อีเมล, เปลี่ยนรหัสผ่าน |

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | Blade, Tailwind CSS, Alpine.js, Vite 7 |
| Database | PostgreSQL 18 |
| AI Vision | Anthropic Claude Vision (claude-opus-4-8) |
| Auth | Laravel Breeze |
| Scheduler | Laravel built-in scheduler (auto-expire reservations) |
| E2E Testing | Playwright (multi-browser, multi-viewport) |

---

## Requirements

- PHP 8.4+ (extensions: pdo_pgsql, gd, fileinfo, zip)
- PostgreSQL 15+
- Node.js 18+
- Composer
- Anthropic API Key — [console.anthropic.com](https://console.anthropic.com)

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

ANTHROPIC_API_KEY=sk-ant-...
CARSCAN_MODEL=claude-opus-4-8

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
| **admin** | จัดการทุกอย่าง: ลานจอด, ช่องจอด, ผู้ใช้, การจอง, check-in/out, payments, AI scan, owner applications, บัญชีดำ |
| **owner** | จัดการลานจอดของตัวเอง, ยืนยันการจอง, ดูรายได้, แสดงใน Marketplace, ส่งคำร้องลาออกกลับเป็น User ได้ |
| **user** | จองล่วงหน้า (สูงสุด 24 ชม.) โดยกรอกทะเบียนโดยตรง, ดูประวัติ, AI scan, สมัครเป็น Owner ได้ |

**Role Management Rules:**
- Admin ไม่สามารถเปลี่ยน role ตัวเองออกจาก admin ได้ (ป้องกัน lock-out)
- Admin ปลด Owner → User ต้องระบุเหตุผล (บันทึกใน Audit Log)
- Owner สามารถส่งคำร้องลาออกกลับเป็น User ได้เอง (ต้องระบุเหตุผล) ที่ `POST /owner/demote-self`

สำหรับรายละเอียดทั้งหมดว่าแต่ละ role ทำอะไรได้บ้าง ดูที่ [docs/project-plan.md](docs/project-plan.md)

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

**Slot Status Lifecycle (parking_slots.status):**

```
[Reservation created — pending]
  slot status: unchanged (available)

[Admin/Owner confirms — confirmed]
  available → reserved      ← slot held for this reservation

[Vehicle checks in — checked_in]
  reserved → occupied       ← slot actively in use

[Vehicle checks out — completed]
  occupied → available      ← slot freed

Cancellation paths:
  cancelled  (pending/confirmed → cancelled)   reserved → available
  expired    (scheduler)                       reserved → available
```

| ช่วงเวลา | `parking_slots.status` |
|---|---|
| ก่อน confirm (pending) | `available` |
| หลัง confirm | `reserved` |
| หลัง check-in | `occupied` |
| หลัง check-out | `available` |
| ยกเลิก / หมดอายุ | `available` |

---

### Check-In Logic

เมื่อ admin กด Check-In สำหรับรถ:

1. ตรวจสอบว่ารถยังไม่ได้อยู่ในระบบ (ไม่มี `parking_log` ที่ยังไม่ได้ check-out)
2. ค้นหา `confirmed` reservation สำหรับรถนี้ที่อยู่ในช่วง grace period:
   - `reserve_start ≤ now + 5 นาที` (รองรับ early check-in 5 นาที)
   - `reserve_start ≥ now - GRACE_PERIOD` (ยังไม่เลย grace period)
3. **ถ้าพบ reservation:**
   - ใช้ lot ที่ reserve ไว้ (override lot ที่ admin เลือก)
   - ถ้า reserve specific slot → ลอง slot นั้นก่อน (`reserved` หรือ `available`)
   - ถ้า slot ถูกใช้ไปแล้ว หรือ reserve แค่ lot → หา slot ว่างใน lot นั้น
   - สร้าง `parking_log` พร้อม `reservation_id`
   - อัปเดต reservation → `checked_in` + บันทึก `checked_in_at`
   - บันทึก `ReservationLog` (auto check-in)
4. **ถ้าไม่พบ reservation (walk-in):**
   - หา slot ว่าง (`available`) ใน lot ที่ admin เลือก
   - สร้าง `parking_log` โดยไม่มี `reservation_id`
5. อัปเดต slot status → `occupied` (`reserved`/`available` → `occupied`)

---

### Check-Out & Fee Calculation

เมื่อ admin กด Check-Out:

1. ตรวจสอบว่ายังไม่ได้ check-out และไม่มี payment ซ้ำ
2. คำนวณค่าจอด:
   ```
   minutes     = check_out_time - check_in_time
   hours       = max(1, ceil(minutes / 60))   ← ปัดขึ้นเสมอ, ขั้นต่ำ 1 ชม.
   parking_fee = hours × hourly_rate
   deposit     = min(reservation.reservation_fee, parking_fee)   ← ค่ามัดจำที่จ่ายตอนจอง
   total_amount = parking_fee - deposit   ← เงินที่ต้องชำระเพิ่ม
   ```
3. สร้าง `Payment`:
   - `reservation_discount` = deposit (ค่ามัดจำที่หักออก)
   - `total_amount` = ค่าจอดจริงหลังหักมัดจำ
   - `payment_status` = `paid` ถ้า total_amount = 0, ไม่งั้น `unpaid`
4. คืน slot → `available`
5. **ถ้า parking_log มี reservation_id:**
   - อัปเดต reservation → `completed` + บันทึก `completed_at`
   - บันทึก `ReservationLog` (auto completed)
6. Expire reservations ของรถนี้ที่ยังค้างอยู่และเลย grace period แล้ว

**ตัวอย่างคำนวณ (rate = 40 บ./ชม., มัดจำ = 40 บาท):**

| check-in | check-out | ชม. | ค่าจอด | มัดจำ | คงเหลือ |
|---|---|---|---|---|---|
| 10:00 | 10:30 | 1 | 40 บาท | 40 บาท | **0 บาท** (ชำระแล้ว) |
| 10:00 | 12:00 | 2 | 80 บาท | 40 บาท | **40 บาท** |
| 10:00 | 14:00 | 4 | 160 บาท | 40 บาท | **120 บาท** |

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

### User Reservation Management

User จองโดยกรอกทะเบียนรถโดยตรง — ไม่ต้องบันทึกรถในระบบก่อน

| Action | Route | เงื่อนไข |
|---|---|---|
| สร้างการจอง | `POST /user/reservations` | กรอก license_plate, เลือกลาน/ช่อง/เวลา, ล่วงหน้า ≤ 24 ชม. |
| ดูรายการจอง | `GET /user/reservations` | เฉพาะของตัวเอง |
| แก้ไขทะเบียน | `PATCH /user/reservations/{id}/plate` | เฉพาะ pending/confirmed, ยังไม่ check-in |
| ยกเลิกการจอง | `POST /user/reservations/{id}/cancel` | เฉพาะ pending/confirmed |

**Duplicate Guards (ตรวจก่อน create):**

| เงื่อนไข | Error |
|---|---|
| ทะเบียนนี้มี active reservation อยู่แล้ว | รถคันนี้มีการจองที่ยังดำเนินการอยู่ |
| ทะเบียนนี้กำลังจอดอยู่ในระบบ | รถคันนี้กำลังจอดอยู่แล้ว |
| User นี้มีการจองในช่วงเวลาเดียวกัน | คุณมีการจองอื่นในช่วงเวลานี้ |
| ช่องจอดนี้ถูกจองในช่วงเวลาเดียวกัน | ช่องจอดนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว |

**Business Rules — Cancel:**

- เฉพาะเจ้าของ reservation (403 ถ้าไม่ใช่)
- ยกเลิกได้เฉพาะ `pending` และ `confirmed`
- ช่องจอดที่ `reserved` → คืนเป็น `available` อัตโนมัติ
- บันทึก `ReservationLog` + ส่ง notification

---

### Suspicious Vehicle Management

Admin สามารถจัดการบัญชีดำทะเบียนรถ (Blacklist) ได้ผ่าน `/admin/suspicious-vehicles`:

| Action | Route | หมายเหตุ |
|---|---|---|
| ดูรายการทั้งหมด | `GET /admin/suspicious-vehicles` | ค้นหาด้วยทะเบียน/เหตุผล, paginate 15 |
| เพิ่มทะเบียน | `POST /admin/suspicious-vehicles` | license_plate unique |
| แก้ไขทะเบียน | `PATCH /admin/suspicious-vehicles/{id}` | unique ignore self |
| เปิด/ปิดใช้งาน | `POST /admin/suspicious-vehicles/{id}/toggle` | flip `is_active` |
| ลบ | `DELETE /admin/suspicious-vehicles/{id}` | hard delete |

**Business Rules:**

- `license_plate` ต้องไม่ซ้ำกันในระบบ
- `level` มี 3 ระดับ: `low`, `medium`, `high`
- `is_active = false` หมายความว่าระงับชั่วคราว — ยังอยู่ในระบบแต่ AI scan จะไม่ flag
- `added_by` บันทึก admin ที่เพิ่มอัตโนมัติ
- ทุก action บันทึกใน Admin Actions Log ผ่าน `admin_audit()`
- Dashboard KPI แสดงจำนวนบัญชีดำที่ใช้งานอยู่ (`is_active = true`)
- AI scan เชื่อมกับตารางนี้ผ่าน `license_plate` JOIN

---

### Grace Period & Auto-Expire

**Grace Period** = ช่วงเวลาหลัง `reserve_start` ที่รถยังมาเช็คอินได้

- Default: **30 นาที**
- ตั้งค่าได้ใน `.env`: `RESERVATION_GRACE_PERIOD=30`
- ตั้งค่าใน `config/parking.php`

**Scheduler** (`reservations:expire`) รันทุก 1 นาที — ใน DB transaction เดียว:
1. หาการจองที่ `pending` หรือ `confirmed` และ `reserve_start + grace_period ≤ now()`
2. อัปเดต → `expired` + บันทึก `ReservationLog`
3. **คืน slot อัตโนมัติ:** ช่องจอดที่มีสถานะ `reserved` → `available`
4. ส่ง notification แจ้ง user ทุกคนที่ถูก expire
- **ข้ามสถานะ `checked_in`** — รถเข้าแล้ว ไม่ expire ไม่คืน slot

```bash
# ทดสอบ scheduler แบบไม่แก้ข้อมูล
php artisan reservations:expire --dry-run

# รันจริง
php artisan reservations:expire
```

---

### Dashboard Analytics Charts

Charts are rendered client-side using **Chart.js 4** (CDN, loaded once via `@once`). All data is aggregated server-side with a single query per chart — no N+1 queries.

**Reusable component:** `resources/views/components/dashboard-chart.blade.php`

| Prop | Type | คำอธิบาย |
|---|---|---|
| `type` | string | `pie`, `doughnut`, `bar`, `line`, `horizontalBar` |
| `title` | string | หัวข้อแสดงเหนือ chart |
| `labels` | array | ป้ายกำกับแกน X / Pie slices |
| `datasets` | array | Chart.js datasets array |
| `height` | string? | CSS height (default: `280px` pie, `240px` bar/line) |

**Admin Dashboard charts:**

| Chart | Type | Data Source |
|---|---|---|
| สถานะการจอง | Pie | `reservations GROUP BY status` (all-time) |
| สถานะช่องจอด | Bar | `parking_slots COUNT by status` (current) |
| ลานจอดยอดนิยม | Horizontal Bar | `reservations JOIN parking_lots`, top 5 by count |

**Owner Dashboard charts:**

| Chart | Type | Data Source |
|---|---|---|
| รายได้ 12 เดือน | Line | `payments paid WHERE lot IN owner_lots`, grouped by `YYYY-MM` |
| สถานะการจอง | Pie | `reservations WHERE parking_lot_id IN owner_lots` |
| สถานะช่องจอด | Bar | `parking_slots WHERE parking_lot_id IN owner_lots` |

Revenue trend fills all 12 months with `0` if no data — ensuring the x-axis always shows a complete 12-month window.

---

### AI Car Scan + Reservation Matching + Auto Check-In

**ขั้นตอน:**

1. อัปโหลดรูปรถ (jpg/jpeg/png, max **5 MB**)
2. ส่งไปยัง **Claude Vision API** → ได้ทะเบียน/สี/ยี่ห้อ
3. ค้นหา `Vehicle` ที่ตรงกับทะเบียนในระบบ
4. **Reservation Matching:** หาการจองสถานะ `confirmed` หรือ `checked_in` ของรถนี้
5. **Auto Check-In:**
   - ถ้าพบการจอง `confirmed` + อยู่ในช่วง check-in (± grace period) → `CheckInService.checkIn()` อัตโนมัติ
   - ส่ง notification แจ้ง user เมื่อสำเร็จ
   - ถ้าอยู่นอกช่วงเวลา → แสดงข้อความ "อยู่นอกช่วงเวลาเช็คอิน"
6. แสดงผลบนหน้า scan: ทะเบียน, ยี่ห้อ, สี, รายละเอียดการจอง, ผลการ check-in
7. บันทึกลง `license_plate_scans` (source: `manual_upload`)

| Field | ตัวอย่าง |
|---|---|
| `license_plate` | `5กก 6285` |
| `color` | `เงิน` |
| `brand` | `Honda` |
| `confidence` | `95` |

**Models ที่รองรับ:**

| Model | ราคา Input/1M | ราคา Output/1M | หมายเหตุ |
|---|---|---|---|
| `claude-opus-4-8` | $5.00 | $25.00 | แม่นสุด (default) |
| `claude-haiku-4-5` | $1.00 | $5.00 | เร็วสุด ถูกสุด |
| `claude-sonnet-4-6` | $3.00 | $15.00 | สมดุลระหว่างราคาและความแม่น |

เปลี่ยน model ได้ใน `.env`: `CARSCAN_MODEL=claude-haiku-4-5`

---

### Notification System

ระบบส่ง in-app notification อัตโนมัติผ่าน `notify_user(int $userId, string $title, string $message)` helper ทุก event สำคัญ:

| Event | ผู้รับ | หัวข้อ |
|---|---|---|
| Admin/Owner ยืนยันการจอง | User | การจองได้รับการยืนยัน |
| Admin ยกเลิกการจอง | User | การจองถูกยกเลิก |
| Scheduler expire การจอง | User | การจองหมดอายุ |
| Auto check-in สำเร็จ (OCR) | User | เช็คอินสำเร็จ |
| Check-out สำเร็จ | เจ้าของรถ | เช็คเอาท์เรียบร้อย |

Notifications ดูได้ที่ `/notifications` (รองรับทุก role)

---

### Owner Application Workflow

1. User ธรรมดาสมัครที่ `/owner/apply` (กรอก ชื่อธุรกิจ, ที่อยู่, เบอร์โทร, เหตุผล)
2. Admin ตรวจสอบที่ `/admin/owner-applications`
3. Admin กด **Approve** → role เปลี่ยนเป็น `owner` อัตโนมัติ
4. Owner เข้า `/owner/dashboard` สร้างลานจอดและช่องจอด
5. ลานจอดปรากฎใน Marketplace เมื่อ active

---

### Payment System

- Payment สร้างอัตโนมัติเมื่อ check-out
- สถานะ: `unpaid` → `paid`
- Admin กด "Mark Paid" เพื่อยืนยันการรับเงิน
- `reservation_fee` (บน `reservations`) = ค่ามัดจำ 1 ชม. ตาม `hourly_rate` ของลานนั้น — เก็บตอน user สร้างการจอง
- `reservation_discount` (บน `payments`) = ค่ามัดจำที่นำมาหักเมื่อ check-out — ไม่เกิน `parking_fee`
- `total_amount` = `parking_fee - reservation_discount` — ถ้า = 0 ระบบ auto-mark `paid`

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
├── id, name, email, password
├── role (admin|owner|user)
├── owner_status (pending|approved|rejected|null) ← ใช้เฉพาะ role=owner
├── force_password_reset (boolean)
└── timestamps

vehicles ← ใช้สำหรับ Admin check-in เท่านั้น (User ไม่ต้องบันทึกรถก่อนจอง)
├── id, user_id → users
├── license_plate, brand, color
└── timestamps

parking_lots
├── id, name, location (nullable)
├── address, district, province, landmark (nullable) ← ที่อยู่ละเอียดสำหรับค้นหา
├── total_slots, hourly_rate (decimal)
├── is_active (boolean), reservations_enabled (boolean)
├── owner_id → users (nullable)
└── timestamps

parking_slots
├── id, parking_lot_id → parking_lots
├── slot_number, status (available|occupied|reserved)
└── timestamps

reservations ← LIFECYCLE: pending → confirmed → checked_in → completed / expired / cancelled
├── id, user_id → users
├── license_plate (string) ← ทะเบียนรถที่จะมาจอด (กรอกตอนจอง)
├── vehicle_id → vehicles (nullable) ← legacy / admin check-in
├── parking_lot_id → parking_lots, parking_slot_id → parking_slots (nullable)
├── reserve_start (timestamp)
├── checked_in_at (nullable) ← set เมื่อ check-in
├── completed_at  (nullable) ← set เมื่อ check-out
├── reservation_fee (decimal), status
└── timestamps

reservation_logs ← Audit trail ทุก status change
├── id, reservation_id → reservations
├── old_status (nullable), new_status
├── changed_by → users (nullable = auto/scheduler)
├── note
└── timestamps

parking_logs ← Check-in/out records
├── id, vehicle_id → vehicles
├── parking_lot_id → parking_lots, parking_slot_id → parking_slots (nullable)
├── reservation_id → reservations (nullable)
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
├── id, user_id → users (nullable)
├── vehicle_id → vehicles (nullable)
├── license_plate, color, brand, confidence
├── source (manual_upload)
├── scan_time
└── timestamps

suspicious_vehicles ← Blacklist
├── id, license_plate (unique), reason (nullable), level (low|medium|high)
├── is_active (boolean, default true)
├── added_by → users (nullable)
└── timestamps

owner_applications
├── id, user_id → users
├── business_name, contact_name, phone, email
├── parking_lot_name, address, estimated_slots
├── status (pending|approved|rejected), rejection_reason (nullable)
├── reviewed_by → users (nullable), reviewed_at (nullable)
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

### Route Structure

```
/                           → Welcome (สาธารณะ)
/marketplace                → แสดงลานจอดทั้งหมด (สาธารณะ)
/login, /register           → Authentication
/dashboard                  → Redirect ตาม Role อัตโนมัติ

/admin/...                  → Admin Panel (role: admin)
  dashboard                 → KPI + Charts
  check-in/create           → บันทึกรถเข้า
  check-out                 → รายการรถที่จอดอยู่ (Check-Out)
  payments                  → การชำระเงิน (Mark Paid)
  reservations              → CRUD การจองทั้งระบบ + ยืนยัน
  reservation-logs          → Audit log + Export CSV
  admin-actions             → Admin Audit Log + Export CSV
  parking-lots              → CRUD ลานจอด (พร้อมที่อยู่ละเอียด)
  parking-slots             → CRUD ช่องจอด + Bulk Create
  vehicles                  → CRUD รถในระบบ
  users                     → จัดการผู้ใช้ + Force Reset + เปลี่ยน role
  scan                      → AI Scan (อัปโหลดรูป → ทะเบียน)
  suspicious-vehicles       → บัญชีดำ CRUD + toggle
  owner-applications        → อนุมัติ/ปฏิเสธคำขอ Owner

/owner/...                  → Owner Portal (role: owner)
  dashboard                 → ภาพรวม + Analytics
  application               → ดูสถานะคำขอ
  parking-lots              → จัดการลานของตน
  parking-slots             → จัดการช่องจอดของตน
  reservations              → การจองในลานของตน
  revenue                   → รายงานรายได้
  demote-self               → POST ส่งคำร้องลาออกกลับเป็น User

/user/...                   → User Panel (role: user)
  dashboard                 → ภาพรวม
  reservations              → การจองของฉัน (สร้าง/ดู/แก้ไขทะเบียน/ยกเลิก)
  parking-logs              → ประวัติการจอดของฉัน
  scan                      → AI Scan

/owner/apply                → สมัครเป็น Owner (user ทุกคนเข้าได้)
/notifications              → การแจ้งเตือน (ทุก role)
/profile                    → แก้ไข Profile (ทุก role)
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
✓ ai-test.test.js    — route coverage, responsive, forms, nav integrity, loading speed
✓ feature-tests.test.js — OCR scan, notifications, owner application, reservation lifecycle
✓ 0 critical bugs found
✓ 29/29 routes — 100% coverage (รวม /admin/owner-applications, /owner/apply, /owner/application)
```

### PHPUnit Tests

```
✓ 120 tests, all passing
✓ ExpireReservationsTest (10), OcrCheckInTest (10), ReservationNotificationsTest (6)
✓ SlotReservationLifecycleTest (7), ReservationCheckInIntegrationTest (6), SuspiciousVehicleBlacklistTest (7)
✓ AdminSuspiciousVehicleTest (9), DashboardChartDataTest (7), UserCancelReservationTest (9)
✓ ReservationDepositTest (6), ReservationTest (7), AuthenticationTest (4), + others
```

---

## Project Structure

```
app/
├── Console/Commands/
│   └── ExpireReservations.php     ← scheduler: auto-expire + slot release + notifications
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── CheckInController.php        ← delegates to CheckInService
│   │   │   ├── CheckOutController.php       ← checkout + fee + notification
│   │   │   ├── OwnerApplicationController.php ← approve/reject owner applications
│   │   │   ├── ParkingLotController.php
│   │   │   ├── ParkingSlotController.php    ← single + bulk create
│   │   │   ├── ReservationController.php    ← CRUD + confirm + notification
│   │   │   ├── ReservationLogController.php ← audit log + CSV export
│   │   │   ├── AdminActionController.php    ← admin audit + CSV export
│   │   │   ├── PaymentController.php
│   │   │   ├── SuspiciousVehicleController.php ← blacklist CRUD + toggle
│   │   │   ├── UserController.php           ← user management + force reset
│   │   │   └── VehicleController.php
│   │   ├── Owner/
│   │   │   ├── ApplicationController.php    ← apply / edit / view status
│   │   │   ├── DashboardController.php
│   │   │   ├── ParkingLotController.php
│   │   │   ├── ParkingSlotController.php
│   │   │   ├── ReservationController.php    ← confirm + notification
│   │   │   └── RevenueController.php
│   │   ├── User/
│   │   │   ├── ReservationController.php    ← create + duplicate guards + 24h limit
│   │   │   ├── VehicleController.php
│   │   │   └── ParkingLogController.php
│   │   ├── CarScanController.php            ← OCR + reservation match + auto check-in
│   │   ├── DashboardController.php          ← admin + user dashboards
│   │   └── NotificationController.php
│   └── Middleware/
│       ├── AdminMiddleware.php
│       ├── RoleMiddleware.php
│       └── ForcePasswordReset.php
├── Models/
│   ├── Reservation.php    ← ACTIVE_STATUSES, gracePeriodMinutes(), scopeCheckable()
│   ├── ParkingLog.php     ← reservation() relation
│   └── ...
├── Services/
│   ├── CarScanService.php      ← Claude Vision API + findMatchingReservation()
│   └── CheckInService.php      ← reusable check-in transaction (lockForUpdate)
└── Support/
    └── notify_user.php         ← global helper: notify_user(userId, title, message)

config/
├── parking.php    ← grace_period (RESERVATION_GRACE_PERIOD)
└── carscan.php    ← Anthropic API key + model

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
| `ANTHROPIC_API_KEY` | — | **จำเป็น** — [console.anthropic.com](https://console.anthropic.com) |
| `CARSCAN_MODEL` | `claude-opus-4-8` | Claude model (`claude-haiku-4-5` ถ้าต้องการประหยัด) |
| `RESERVATION_GRACE_PERIOD` | `30` | Grace period (นาที) สำหรับ late check-in |
| `APP_KEY` | — | สร้างด้วย `php artisan key:generate` |

---

## Developer

Developed by [tpp72](https://github.com/tpp72)

## License

MIT License
