# Smart Parking System — เอกสารโปรเจคฉบับสมบูรณ์

> ระบบจัดการที่จอดรถอัจฉริยะ พัฒนาด้วย Laravel 11 + Google Gemini AI
> สถาบัน: — | ปีการศึกษา: 2566–2567

---

## สารบัญ

1. [ภาพรวมโปรเจค](#1-ภาพรวมโปรเจค)
2. [Tech Stack](#2-tech-stack)
3. [โครงสร้างระบบ (Architecture)](#3-โครงสร้างระบบ)
4. [Flow งานทั้งระบบ](#4-flow-งานทั้งระบบ)
5. [ฐานข้อมูล (Database Schema)](#5-ฐานข้อมูล)
6. [Roles & Permissions](#6-roles--permissions)
7. [Routes & Controllers](#7-routes--controllers)
8. [ฟีเจอร์หลัก](#8-ฟีเจอร์หลัก)
9. [ระบบ AI Car Scan](#9-ระบบ-ai-car-scan)
10. [Blade Components & UI](#10-blade-components--ui)
11. [Scheduled Tasks](#11-scheduled-tasks)
12. [Audit Logging](#12-audit-logging)
13. [การติดตั้ง](#13-การติดตั้ง)
14. [ข้อมูลสำหรับสอบจบโปรเจค](#14-ข้อมูลสำหรับสอบจบโปรเจค)

---

## 1. ภาพรวมโปรเจค

**Smart Parking System** คือระบบบริหารจัดการที่จอดรถแบบครบวงจร รองรับ 2 บทบาทหลัก:

| บทบาท | สิทธิ์ |
|-------|--------|
| **Admin** | จัดการทุกอย่าง: รถเข้า-ออก, การจอง, ชำระเงิน, ผู้ใช้, ลานจอด, AI สแกน |
| **User** | จองที่จอด, ดูประวัติ, จัดการรถของตัวเอง, สแกนรถ |

### ความสามารถหลัก

- บันทึกรถเข้า-ออก พร้อมคำนวณค่าจอดอัตโนมัติ
- ระบบจองล่วงหน้า พร้อม workflow อนุมัติ
- **AI วิเคราะห์รูปรถ** — อ่านทะเบียน, สี, ยี่ห้อ ด้วย Google Gemini Vision API
- ตรวจสอบรถใน Blacklist อัตโนมัติ
- บันทึก Audit Log ทุก action ของ admin
- Dashboard แสดง KPI แบบ real-time

---

## 2. Tech Stack

### Backend
| Technology | Version | ใช้ทำอะไร |
|-----------|---------|----------|
| PHP | 8.3 | Backend language |
| Laravel | 11 | Web framework (MVC) |
| Laravel Breeze | 2.3 | Authentication scaffold |
| **PostgreSQL** | 15+ | Relational Database |
| Laravel HTTP Client | — | เรียก Gemini REST API (built-in, ไม่ต้องติดตั้งเพิ่ม) |

### Frontend
| Technology | ใช้ทำอะไร |
|-----------|----------|
| Blade Templates | Server-side HTML rendering |
| Tailwind CSS | Utility-first CSS framework |
| Alpine.js | Lightweight reactive UI (show/hide, toggle) |
| Vite | Asset bundling & hot reload |

### AI / Vision
| Technology | Version | ใช้ทำอะไร |
|-----------|---------|----------|
| **Google Gemini Vision** | **2.5 Flash** | **วิเคราะห์รูปรถ — อ่านทะเบียน, สี, ยี่ห้อ** |
| Gemini API | v1beta | REST API endpoint สำหรับ multimodal |

---

## 3. โครงสร้างระบบ

### Application Architecture
```
┌─────────────────────────────────────────────────────────────┐
│                         Browser                             │
│              Blade + Tailwind CSS + Alpine.js               │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS Request
┌────────────────────────▼────────────────────────────────────┐
│                   Laravel 11 Application                     │
│                                                             │
│  Route → Middleware → Controller → Service → Model          │
│                                        ↓                    │
│                               Eloquent ORM                  │
└──────────────┬──────────────────────────┬───────────────────┘
               │                          │
┌──────────────▼──────────┐   ┌───────────▼──────────────────┐
│   PostgreSQL Database    │   │   Google Gemini Vision API   │
│   (22 tables)            │   │   generativelanguage.google  │
│   Eloquent ORM Layer     │   │   apis.com/v1beta/models/    │
└─────────────────────────┘   │   gemini-2.5-flash           │
                              └──────────────────────────────┘
```

### Design Patterns ที่ใช้
| Pattern | ใช้ที่ไหน |
|---------|---------|
| **MVC** | Laravel framework structure |
| **Service Layer** | CarScanService — แยก business logic ออกจาก Controller |
| **Repository via Eloquent** | Model + ORM abstraction layer |
| **Middleware Pipeline** | Auth, Role, ForcePasswordReset |
| **Observer (implicit)** | admin_audit helper บันทึกทุก action |
| **Facade** | Laravel HTTP Client → Gemini API call |

### Directory Structure
```
smart-parking-system/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # 12 Admin controllers
│   │   │   ├── User/           # 3 User controllers
│   │   │   ├── Auth/           # 9 Auth controllers
│   │   │   ├── CarScanController.php   # shared admin+user
│   │   │   ├── DashboardController.php
│   │   │   ├── NotificationController.php
│   │   │   └── ProfileController.php
│   │   └── Middleware/
│   │       ├── AdminMiddleware.php
│   │       ├── RoleMiddleware.php
│   │       └── ForcePasswordReset.php
│   ├── Models/                 # 15 Eloquent models
│   ├── Services/
│   │   └── CarScanService.php  # AI scan business logic
│   └── Support/
│       └── admin_audit.php     # audit log helper function
├── config/
│   ├── app.php                 # timezone: Asia/Bangkok
│   ├── carscan.php             # Gemini API key + model config
│   └── page_titles.php         # per-route page titles
├── database/
│   ├── migrations/             # 22 migration files
│   └── seeders/
├── resources/views/            # 62 blade template files
├── routes/
│   ├── web.php                 # all HTTP routes
│   └── console.php             # scheduled artisan commands
└── docs/
    ├── project-documentation.md   # เอกสารโปรเจคฉบับสมบูรณ์
    └── ai-scan-documentation.md   # เอกสาร AI Car Scan
```

---

## 4. Flow งานทั้งระบบ

### 4.1 User Registration & Login Flow
```
[หน้า Register]
  ├── กรอก name / email / password
  ├── Laravel validate → bcrypt password
  ├── สร้าง users record (role = 'user')
  └── redirect → user.dashboard

[หน้า Login]
  ├── ตรวจ credentials ใน PostgreSQL
  ├── สร้าง session
  ├── ตรวจ role:
  │     admin → redirect admin.dashboard
  │     user  → redirect user.dashboard
  └── ตรวจ force_password_reset:
        true → redirect profile.edit ก่อน
```

---

### 4.2 Admin Check-In Flow (รถเข้า)
```
Admin กด "รถเข้า"
  │
  ▼
กรอกทะเบียน → ค้นหาใน vehicles table
  ├── พบ → แสดงข้อมูลรถ + เจ้าของ
  └── ไม่พบ → แสดงฟอร์มสร้างรถใหม่
  │
  ▼
เลือก: ลานจอด + ช่องจอด (optional)
  │
  ▼
กด Submit → CheckInController@store
  ├── สร้าง parking_logs record
  │     check_in_time = now()
  │     check_out_time = NULL
  ├── อัปเดต parking_slots.status = 'occupied'
  ├── ตรวจ reservation ที่ confirmed + ตรงรถ
  │     → อัปเดต reservation.status = 'reserved'
  └── redirect → check-out list
```

---

### 4.3 Admin Check-Out Flow (รถออก)
```
Admin เลือก log จาก Check-Out list
  │
  ▼
CheckOutController@store
  │
  ├── คำนวณเวลาจอด:
  │     minutes = check_out - check_in
  │     hours   = ceil(minutes / 60)   ← ปัดขึ้นเสมอ
  │
  ├── คำนวณค่าจอด:
  │     parking_fee  = hours × lot.hourly_rate
  │     discount     = reservation_fee (ถ้ามีการจอง)
  │     total_amount = max(0, parking_fee - discount)
  │
  ├── สร้าง payments record (payment_status = 'unpaid')
  │
  ├── อัปเดต parking_logs.check_out_time = now()
  │
  ├── อัปเดต parking_slots.status = 'available'
  │
  └── Expire reservations ที่เลยเวลา:
        vehicle_id = รถคันนี้
        status IN ('pending','confirmed')
        reserve_end <= now()
        → status = 'expired'
```

---

### 4.4 User Reservation Flow (จองล่วงหน้า)
```
User กด "จองที่จอด"
  │
  ▼
ตรวจสอบ: มีรถในระบบมั้ย?
  ├── ไม่มี → แสดงลิงก์ "+ เพิ่มรถ" (user.vehicles.create)
  └── มี → แสดงฟอร์มจอง
            ├── เลือกรถ (dropdown จาก vehicles ของตัวเอง)
            ├── เลือกลาน
            ├── เลือกช่องจอด (Alpine.js filter ตามลาน)
            ├── กรอกวันเวลา start / end
            └── กด Submit
              │
              ▼
        ReservationController@store
          ├── validate input
          ├── ตรวจสิทธิ์: vehicle.user_id === auth()->id()
          ├── DB::transaction + lockForUpdate():
          │     ตรวจ slot conflict (time overlap)
          │     → ถ้าซ้ำ: return error
          ├── สร้าง reservations record (status = 'pending')
          └── redirect → user.reservations.index

[Admin เห็น pending reservation]
  └── กด "✓ ยืนยัน" → status = 'confirmed'

[Cron ทุก 1 นาที]
  └── expire reservation ที่เลย reserve_end
```

---

### 4.5 Payment Flow (ชำระเงิน)
```
[หลัง Check-Out]
  └── สร้าง payment (payment_status = 'unpaid')

[Admin → หน้าชำระเงิน]
  ├── กรอง: ค้างชำระ / ชำระแล้ว / ทั้งหมด
  └── กด "✓ รับชำระแล้ว"
        ├── อัปเดต payment_status = 'paid'
        └── บันทึก admin_audit('payment.mark_paid', ...)
```

---

### 4.6 AI Car Scan Flow (สแกนรถ)
```
User/Admin อัปโหลดรูปรถ (JPG/PNG ≤5MB)
  │
  ▼
CarScanController@store
  ├── Validate: mimes:jpg,jpeg,png | max:5120
  └── CarScanService@scanAndSave()
        │
        ├── [1] Store file → storage/app/public/car-scans/
        │
        ├── [2] Laravel HTTP Client → Gemini Vision API (~3-5 วินาที):
        │       POST generativelanguage.googleapis.com/v1beta/
        │            models/gemini-2.5-flash:generateContent?key=...
        │       Body: { inlineData: base64(image), text: Thai prompt }
        │       Config: temperature=0.1, responseMimeType=application/json
        │
        ├── [3] Gemini วิเคราะห์รูป:
        │       ├── Thai OCR → license_plate
        │       ├── Color detection → สีภาษาไทย
        │       └── Brand recognition → ยี่ห้อ / null
        │       Response: {"license_plate":"5กก6285","color":"เงิน",...}
        │
        ├── [4] Parse JSON + ตรวจ blacklist:
        │       SuspiciousVehicle::where('license_plate',...)->exists()
        │
        ├── [5] Match กับ vehicles table:
        │       vehicle = Vehicle::where('license_plate',...)->first()
        │
        └── [6] บันทึก license_plate_scans record
              └── redirect back with scan_result (scan.id)

[หน้าผลลัพธ์]
  ├── แสดง: ทะเบียน / สี (พร้อม color swatch) / ยี่ห้อ
  ├── แสดงรูปที่อัปโหลด
  ├── แสดง "พบในระบบ" ถ้าทะเบียนตรงกับ vehicle ในระบบ
  └── แสดง Alert สีแดง ถ้า is_suspicious = true
```

---

### 4.7 Blacklist Alert Flow
```
[ระหว่าง AI Scan]
  └── ตรวจ suspicious_vehicles:
        license_plate = ผลจาก OCR
        is_active = true
        → พบ: is_suspicious = true

[หน้าผลลัพธ์]
  └── is_suspicious = true
        → แสดง Alert กระพริบสีแดง
           "รถอยู่ใน Blacklist — แจ้งเจ้าหน้าที่ทันที"
```

---

### 4.8 Auto-Expire Reservation Flow
```
[กลไก 1: Cron Job]
  routes/console.php:
    Schedule::command('reservations:expire')->everyMinute()
  
  ทุก 1 นาที:
    UPDATE reservations
    SET status = 'expired'
    WHERE status IN ('pending', 'confirmed')
      AND reserve_end <= NOW()

[กลไก 2: Check-Out Trigger]
  หลัง check-out สำเร็จ:
    Reservation::where('vehicle_id', $log->vehicle_id)
        ->whereIn('status', ['pending', 'confirmed'])
        ->where('reserve_end', '<=', now())
        ->update(['status' => 'expired'])
```

---

## 5. ฐานข้อมูล

> **Database:** PostgreSQL 15+

### ERD (Entity Relationship)
```
users ─────────────────────────────────┐
  │                                     │
  ├── vehicles ──────────────────────┐  │
  │     │                            │  │
  │     ├── parking_logs ────────────┼──┤── payments
  │     │        └── penalties       │  │
  │     └── reservations ────────────┘  │
  │           └── reservation_logs      │
  │                                     │
  ├── notifications                     │
  └── admin_actions ◄───────────────────┘

parking_lots
  ├── parking_slots ──── parking_logs
  ├── parking_rates
  └── entry_exit_devices
            └── license_plate_scans ── vehicles
                                    └── users

suspicious_vehicles   (standalone blacklist)
```

### ตารางทั้งหมด (22 ตาราง)

| ตาราง | คำอธิบาย | Key Fields |
|-------|---------|-----------|
| `users` | ผู้ใช้งานทั้งหมด | id, name, email, role (user\|admin), force_password_reset |
| `vehicles` | รถที่ลงทะเบียน | id, license_plate (unique), brand, color, user_id |
| `parking_lots` | ลานจอดรถ | id, name, location, total_slots, hourly_rate |
| `parking_slots` | ช่องจอดรายช่อง | id, parking_lot_id, slot_number, status (available\|reserved\|occupied) |
| `parking_logs` | ประวัติจอดรถ | id, vehicle_id, parking_lot_id, check_in_time, check_out_time |
| `reservations` | การจองล่วงหน้า | id, user_id, vehicle_id, reserve_start, reserve_end, status, reservation_fee |
| `reservation_logs` | ประวัติการเปลี่ยนสถานะจอง | id, reservation_id, old_status, new_status, changed_by |
| `payments` | ข้อมูลการชำระเงิน | id, parking_log_id (unique), total_hours, hourly_rate, total_amount, payment_status |
| `penalties` | ค่าปรับ | id, parking_log_id, reason, amount |
| `license_plate_scans` | ผล AI สแกนรถ | id, license_plate, color, brand, confidence, is_suspicious, source |
| `entry_exit_devices` | กล้อง/อุปกรณ์ทางเข้าออก | id, parking_lot_id, device_type (gate\|camera\|scanner), status |
| `suspicious_vehicles` | Blacklist รถ | id, license_plate (unique), reason, level, is_active |
| `admin_actions` | Audit log ของ admin | id, admin_id, action, subject_type, subject_id, meta (jsonb), ip_address |
| `notifications` | การแจ้งเตือน | id, user_id, title, message, is_read |
| `parking_rates` | อัตราค่าจอด | id, parking_lot_id |
| `roles` | บทบาท (reserved for RBAC) | id, name (unique) |
| `permissions` | สิทธิ์ (reserved for RBAC) | id |
| `sessions` | Laravel session store | — |
| `cache` | Laravel cache store | — |
| `jobs` | Queue jobs | — |
| `password_reset_tokens` | รีเซ็ตรหัสผ่าน | — |
| `posts` | (ตาราง demo) | — |

### Status Flows

**Parking Slot:**
```
available ──[จอง]──► reserved ──[check-in]──► occupied
    ▲                                              │
    └────────────────[check-out]───────────────────┘
```

**Reservation:**
```
          ┌──[admin cancel]──► cancelled
pending ──┤
          └──[admin confirm]──► confirmed ──[check-in]──► (active)
                    │
                    └──[expire time / check-out]──► expired
```

**Payment:**
```
unpaid ──[admin mark-paid]──► paid
```

### PostgreSQL Config (.env)
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=smart_parking
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

---

## 6. Roles & Permissions

### Middleware Stack
```php
// Admin routes (prefix: /admin)
['auth', 'verified', 'admin']

// User routes (prefix: /user)
['auth', 'verified', 'force.password.reset', 'role:user']

// Shared (profile, notifications)
['auth', 'verified', 'force.password.reset']
```

| Middleware | ไฟล์ | หน้าที่ |
|-----------|------|--------|
| `AdminMiddleware` | app/Http/Middleware/AdminMiddleware.php | ตรวจ role === 'admin' → 403 ถ้าไม่ใช่ |
| `RoleMiddleware` | app/Http/Middleware/RoleMiddleware.php | Parametrized: `role:user` / `role:admin` redirect ตาม role |
| `ForcePasswordReset` | app/Http/Middleware/ForcePasswordReset.php | force_password_reset = true → บังคับเปลี่ยนรหัสก่อน |

---

## 7. Routes & Controllers

### Admin Routes (`/admin`)

| Method | Path | Route Name | Controller |
|--------|------|-----------|-----------|
| GET | /admin/dashboard | admin.dashboard | DashboardController@admin |
| GET/POST | /admin/check-in | admin.check-in.* | CheckInController |
| GET/POST | /admin/check-out | admin.check-out.* | CheckOutController |
| CRUD | /admin/reservations | admin.reservations.* | ReservationController |
| POST | /admin/reservations/{id}/confirm | admin.reservations.confirm | ReservationController@confirm |
| GET | /admin/parking-logs | admin.parking-logs.index | ParkingLogController@index |
| GET/POST | /admin/payments | admin.payments.* | PaymentController |
| POST | /admin/payments/{id}/mark-paid | admin.payments.mark-paid | PaymentController@markPaid |
| CRUD | /admin/vehicles | admin.vehicles.* | VehicleController |
| CRUD | /admin/parking-lots | admin.parking-lots.* | ParkingLotController |
| CRUD | /admin/parking-slots | admin.parking-slots.* | ParkingSlotController |
| GET/POST | /admin/parking-slots/bulk | admin.parking-slots.bulk.* | ParkingSlotController@bulk |
| CRUD | /admin/devices | admin.devices.* | EntryExitDeviceController |
| GET/PATCH | /admin/users | admin.users.* | UserController |
| GET | /admin/reservation-logs | admin.reservation-logs.index | ReservationLogController@index |
| GET | /admin/admin-actions | admin.admin-actions.index | AdminActionController@index |
| GET/POST | /admin/scan | admin.scan.create/store | CarScanController |
| GET | /admin/scan/history | admin.scan.history | CarScanController@history |

### User Routes (`/user`)

| Method | Path | Route Name | Controller |
|--------|------|-----------|-----------|
| GET | /user/dashboard | user.dashboard | DashboardController@user |
| GET/POST | /user/reservations | user.reservations.* | ReservationController |
| GET/POST/DELETE | /user/vehicles | user.vehicles.* | VehicleController |
| GET | /user/parking-logs | user.parking-logs.index | ParkingLogController@index |
| GET/POST | /user/scan | user.scan.create/store | CarScanController |

---

## 8. ฟีเจอร์หลัก

### 8.1 Check-In / Check-Out

**การคำนวณค่าจอด:**
```php
$minutes     = $checkIn->diffInMinutes($checkOut);
$totalHours  = (int) ceil($minutes / 60);   // ปัดขึ้นเสมอ
$parkingFee  = $totalHours * $lot->hourly_rate;
$discount    = $reservation?->reservation_fee ?? 0;
$totalAmount = max(0, $parkingFee - $discount);
```

### 8.2 Race Condition Prevention (การจอง)
```php
DB::transaction(function () use ($data) {
    // Lock row เพื่อกัน concurrent booking
    $slot = ParkingSlot::lockForUpdate()->find($data['parking_slot_id']);

    // ตรวจ time overlap: start_a < end_b AND end_a > start_b
    if ($this->hasSlotConflict($slotId, $start, $end)) {
        return back()->withErrors(['ช่องจอดนี้ถูกจองแล้ว']);
    }
    Reservation::create([...]);
});
```

### 8.3 Admin Dashboard KPI
- รถในลานตอนนี้ (active vehicles)
- ช่องว่างทั้งระบบ (available slots)
- รายได้วันนี้ (today revenue)
- การจองรออนุมัติ (pending reservations)
- Slot utilization bar รายลาน
- Tabs: Live Parking / Reservations / History / Slots Preview

---

## 9. ระบบ AI Car Scan

### ภาพรวม

ระบบใช้ **Google Gemini Vision API** วิเคราะห์รูปรถผ่าน REST API — ส่งรูปเป็น base64 พร้อม prompt ภาษาไทย แล้วรับ JSON กลับมาโดยตรง

| งาน | Model | หลักการ |
|-----|-------|--------|
| **OCR ทะเบียน** | Gemini 2.5 Flash Vision | Multimodal Transformer อ่าน text ในรูป |
| **สีรถ** | Gemini 2.5 Flash Vision | วิเคราะห์สีตัวถังรถจากรูป |
| **ยี่ห้อรถ** | Gemini 2.5 Flash Vision | จดจำโลโก้/รูปทรงรถ |

### Gemini Vision API

**Model:** `gemini-2.5-flash` (Google DeepMind)
- Multimodal Transformer — รับ image + text พร้อมกัน
- รองรับภาษาไทยได้ดีมาก
- Free Tier: 1,500 requests/วัน

### Request ที่ส่งไป

```php
Http::post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=...', [
    'contents' => [[
        'parts' => [
            ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => base64($image)]],
            ['text'       => 'วิเคราะห์รูปรถ แล้วตอบ JSON: license_plate, color, brand, confidence']
        ]
    ]],
    'generationConfig' => [
        'maxOutputTokens'  => 1024,
        'temperature'      => 0.1,
        'responseMimeType' => 'application/json',
    ]
]);
```

### ผลการทดสอบ

| ภาพทดสอบ | ทะเบียนจริง | OCR ได้ | สีจริง | ระบบได้ | ยี่ห้อ | Confidence |
|---------|------------|---------|-------|---------|-------|-----------|
| Honda Accord สีเงิน | 5กก 6285 | ✓ 5กก 6285 | เงิน | ✓ เงิน | ✓ Honda | 95% |
| Honda สีเทา | 6ขน 4257 | ✓ 6ขน 4257 | เทา | ✓ เทา | ✓ Honda | 90% |
| รูปไม่ชัด | — | — | — | — | null | 0% |

---

## 10. Blade Components & UI

### Custom Blade Components

| Component | ไฟล์ | ใช้ทำอะไร |
|-----------|------|----------|
| `<x-sp-alert>` | sp-alert.blade.php | Alert dismissible (success/error/warning) พร้อม Alpine.js |
| `<x-sp-empty>` | sp-empty.blade.php | Empty state พร้อม icon และ action slot |
| `<x-password-input>` | password-input.blade.php | Input password + ปุ่มแสดง/ซ่อน (Alpine.js x-show) |

### Custom CSS Theme (`theme-dark-red.css`)
| Class | ใช้ทำอะไร |
|-------|----------|
| `.sp-bg` | Background gradient (dark red radial) |
| `.sp-card` | Glass-morphism card (backdrop-blur) |
| `.sp-btn`, `.sp-btn-primary/outline/danger/success` | Button variants |
| `.sp-badge-ok/bad/warn` | Colored status badges |
| `.sp-table` | Table + zebra stripe + hover |
| `.sp-glow-text`, `.sp-glow-btn` | Red glow box-shadow effects |
| `.sp-slot-available/occupied/reserved` | Slot card color variants |

### Navigation
**Admin:** `Dashboard | รถเข้า | รถออก | ชำระเงิน[N] | AI สแกน`

**User:** `หน้าหลัก | [+ จองที่จอด] | การจองของฉัน | รถของฉัน | ประวัติ`

---

## 11. Scheduled Tasks

```php
// routes/console.php
Schedule::command('reservations:expire')->everyMinute();
```

```
Artisan Command: reservations:expire
  ค้นหา reservations ที่:
    status IN ('pending', 'confirmed')
    AND reserve_end <= NOW()
  → UPDATE status = 'expired'

Options: --dry-run (แสดงจำนวนโดยไม่ update)
```

---

## 12. Audit Logging

```php
// app/Support/admin_audit.php
function admin_audit(string $action, $subject = null, array $meta = []): void

// ตัวอย่าง
admin_audit('reservation.confirm', $reservation, ['status' => 'confirmed']);
admin_audit('payment.mark_paid', $log, ['payment_id' => $id, 'amount' => 150]);
admin_audit('user.force_reset', $user, []);
```

**ข้อมูลที่บันทึกลง `admin_actions`:**

| Field | คำอธิบาย |
|-------|---------|
| admin_id | ID admin ที่กระทำ |
| action | ชื่อ action เช่น `reservation.confirm` |
| subject_type | Model class เช่น `App\Models\Reservation` |
| subject_id | ID ของ record ที่ถูกกระทำ |
| meta | JSONB ข้อมูลเพิ่มเติม |
| ip_address | IP ของ admin |
| user_agent | Browser ของ admin |

---

## 13. การติดตั้ง

### Requirements
- PHP 8.4+
- PostgreSQL 15+
- Node.js 18+
- Composer
- **Gemini API Key** (ฟรีที่ [aistudio.google.com/apikey](https://aistudio.google.com/apikey))

### Steps

```bash
# 1. Clone และ install
git clone <repo>
cd smart-parking-system
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. ตั้งค่าใน .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=smart-parking-system
DB_USERNAME=postgres
DB_PASSWORD=your_password

GEMINI_API_KEY=AIzaSy...          # ← สำคัญ
CARSCAN_MODEL=gemini-2.5-flash    # หรือ gemini-2.5-pro

# 4. Migrate
php artisan migrate

# 5. Cache config + Storage link + Build
php artisan config:cache
php artisan storage:link
npm run build

# 6. Start
php artisan serve
# Scheduler (แยก terminal)
php artisan schedule:work
```

### .env ที่สำคัญ

| Variable | คำอธิบาย |
|----------|---------|
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST/PORT/DATABASE` | PostgreSQL connection |
| `GEMINI_API_KEY` | Google Gemini API Key (ขอฟรีที่ aistudio.google.com) |
| `CARSCAN_MODEL` | โมเดล Gemini ที่ใช้ (default: `gemini-2.5-flash`) |

---

## 14. ข้อมูลสำหรับสอบจบโปรเจค

### วัตถุประสงค์โปรเจค
1. พัฒนาระบบจัดการที่จอดรถแบบครบวงจร รองรับการทำงานจริง
2. นำ AI/Computer Vision มาประยุกต์ใช้ในการตรวจรถ (OCR + Color + Brand)
3. ออกแบบระบบที่มี Role-Based Access Control และ Audit Trail

---

### คำถามที่อาจถูกถามในการสอบ

**ด้านระบบ:**

> **Q: ทำไมถึงเลือกใช้ Laravel?**
> A: Laravel เป็น PHP framework ที่มี Eloquent ORM, Middleware pipeline, Artisan CLI และ ecosystem ครบวงจร เหมาะกับโปรเจคที่ต้องการ MVC structure พร้อม built-in Auth ผ่าน Breeze

> **Q: PostgreSQL ดีกว่า MySQL อย่างไร?**
> A: PostgreSQL รองรับ JSONB (ใช้ใน admin_actions.meta), มี advanced indexing, ACID compliance เข้มกว่า, รองรับ concurrent transactions ได้ดีกว่าสำหรับ lockForUpdate() ในระบบจอง

> **Q: Race condition ในการจองแก้อย่างไร?**
> A: ใช้ `DB::transaction()` + `lockForUpdate()` เพื่อ lock row ระหว่างตรวจ conflict และ insert ทำให้ไม่มีการ double-booking ในช่วงเวลาเดียวกัน

> **Q: ทำไม reservation ต้อง expire อัตโนมัติ?**
> A: ป้องกันช่องจอดถูก lock ไว้โดยการจองที่ไม่มาใช้จริง ใช้ 2 กลไก: Cron ทุก 1 นาที + trigger ทันทีตอน check-out เพื่อ real-time accuracy

**ด้าน AI:**

> **Q: ระบบ AI ใช้เทคโนโลยีอะไร?**
> A: ใช้ Google Gemini Vision API (model: gemini-2.5-flash) ซึ่งเป็น Multimodal Large Language Model ของ Google DeepMind รับรูปภาพ + prompt ภาษาไทยพร้อมกัน แล้วตอบกลับเป็น JSON

> **Q: ทำไมเปลี่ยนจาก Python/EasyOCR มาใช้ Gemini?**
> A: Gemini Vision แม่นกว่ามากสำหรับป้ายทะเบียนไทย เร็วกว่า (~3-5 วินาที vs ~15-30 วินาที) ไม่ต้องติดตั้ง Python/PyTorch/OpenCV ไม่ต้องดูแล template รูปยี่ห้อ และใช้งานง่ายกว่ามาก

> **Q: Gemini Vision ทำงานอย่างไร?**
> A: Gemini เป็น Multimodal Transformer — Vision Encoder แปลงรูปเป็น image embeddings, Language Model รับ embeddings + text prompt แล้ว generate ผลลัพธ์ที่ระบุใน prompt เช่น ทะเบียน สี ยี่ห้อ ใน JSON format

> **Q: ถ้า confidence 0% แปลว่าอะไร?**
> A: Gemini ไม่เห็นหรืออ่านป้ายทะเบียนไม่ได้ เช่น รูปไม่ชัด ป้ายบังด้วยวัตถุ หรือมุมที่มองไม่เห็น ค่า 0 มาจาก prompt ที่บอกให้ระบุความมั่นใจ 0-100

> **Q: ทำไม brand บางคันแสดง "ไม่ระบุ"?**
> A: Gemini คืน `null` เมื่อไม่มั่นใจในยี่ห้อ ระบบ map null เป็น "ไม่ระบุ" ซึ่งดีกว่าการเดาผิด — ผู้ใช้รู้ว่าระบบไม่แน่ใจ ไม่ได้ข้อมูลผิดพลาด

**ด้าน Security:**

> **Q: ป้องกัน unauthorized access อย่างไร?**
> A: ใช้ Middleware stack: `auth` → ตรวจ login, `admin` → ตรวจ role, `ForcePasswordReset` → บังคับเปลี่ยนรหัส, `role:user` → กัน admin เข้า user routes

> **Q: SQL Injection ป้องกันอย่างไร?**
> A: ใช้ Eloquent ORM ทุก query ผ่าน PDO prepared statements อัตโนมัติ ไม่มี raw query ที่รับ input โดยตรง

> **Q: File upload ป้องกันอย่างไร?**
> A: Validate `mimes:jpg,jpeg,png` + `max:5120` (5MB) + `image` rule ของ Laravel ก่อน store ชื่อไฟล์ถูก random hash โดย Laravel storage

---

### สิ่งที่ต้อง Demo ในวันสอบ

```
1. Login ด้วย admin + user แสดง role redirect
2. Admin Check-In รถ → แสดงลานจอด
3. User จองล่วงหน้า → Admin ยืนยัน
4. Admin Check-Out → คำนวณค่าจอด → Payment
5. AI Scan รูปรถ → แสดง ทะเบียน + สี + ยี่ห้อ
6. Admin Dashboard → KPI cards
7. Blacklist → scan รถ suspicious → แสดง alert
8. Admin Audit Log → แสดง action ที่ทำไปแล้ว
```

---

### สถาปัตยกรรมที่ควรอธิบายได้

```
Browser
  └─ HTTP → Laravel Routes
              └─ Middleware (auth → role → force_reset)
                  └─ Controller (thin — เพียง orchestrate)
                      └─ Service (CarScanService — business logic)
                          └─ Model / Eloquent ORM
                              └─ PostgreSQL
                          └─ Google Gemini Vision API (AI)
                              └─ gemini-2.5-flash (Multimodal)
```

---

## ภาคผนวก: ไฟล์สำคัญ

| ไฟล์ | คำอธิบาย |
|------|---------|
| `app/Services/CarScanService.php` | Business logic — เรียก Gemini Vision API |
| `app/Http/Controllers/CarScanController.php` | HTTP layer สำหรับ scan |
| `app/Support/admin_audit.php` | Helper audit log |
| `app/Console/Commands/ExpireReservations.php` | Artisan cron command |
| `config/carscan.php` | Gemini API key + model config |
| `config/page_titles.php` | Title แต่ละหน้า |
| `resources/css/theme-dark-red.css` | Custom CSS theme |
| `routes/console.php` | Scheduled commands |

---

*Smart Parking System — เอกสารฉบับสมบูรณ์*
*อัปเดต: เมษายน 2568*
