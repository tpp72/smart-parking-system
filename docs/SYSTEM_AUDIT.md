# SYSTEM AUDIT — Smart Parking System

> ⚠️ **SUPERSEDED (2026-09-16)** — เอกสารนี้เป็นผลตรวจก่อน Refactor Phase 1–16 ข้อมูลไม่ตรงกับโค้ดปัจจุบันแล้ว
> เก็บไว้เพื่อดูประวัติเท่านั้น · สถานะปัจจุบันดูที่ `docs/project-plan.md` (Source of Truth) และ `docs/TEST_COVERAGE.md`

> **วันที่ตรวจสอบ:** 2026-09-13
> **Branch:** `main` · **Commit ล่าสุด:** `f2e78e5`
> **ผู้ตรวจ:** System Auditor (read-only)
> **ขอบเขต:** ตรวจจาก source code จริง + ตรวจสอบกับฐานข้อมูล PostgreSQL ที่ใช้งานอยู่จริง
> **หมายเหตุ:** รายงานนี้ **ไม่มีการแก้ไข code / database / config ใดๆ** ทั้งสิ้น

---

## หมายเหตุสำคัญก่อนอ่าน

1. **`/docs` อยู่ใน `.gitignore`** — ไฟล์รายงานนี้จะไม่ถูก track โดย git ถ้าต้องการเก็บเข้า repo ต้องแก้ `.gitignore` หรือ `git add -f`
2. ข้อสรุปทุกข้อในรายงานนี้แบ่งเป็น 2 ระดับ:
   - **ยืนยันแล้ว (verified)** — รันจริงกับ DB/PHP แล้วเห็นผล
   - **อ่านจาก code (code-read)** — สรุปจากการอ่าน source อย่างเดียว
3. ระบบนี้ผ่านการเปลี่ยน requirement ครั้งใหญ่ช่วง **2026-07-17 ถึง 2026-07-21** จากโมเดล *"ผู้ใช้ต้องลงทะเบียนรถ (Vehicle) ก่อนจอง"* → *"ผู้ใช้พิมพ์ทะเบียน/ยี่ห้อ/สี ตอนจองได้เลย"* — **การเปลี่ยนครั้งนี้ทำไม่ครบ** และเป็นต้นตอของปัญหาส่วนใหญ่ในรายงานนี้

---

## สารบัญ

1. [Executive Summary](#1-executive-summary)
2. [Project Structure & Tech Stack](#2-project-structure--tech-stack)
3. [Authentication, Roles & Middleware](#3-authentication-roles--middleware)
4. [Route Inventory](#4-route-inventory)
5. [Database Inventory](#5-database-inventory)
6. [Feature-by-Feature Audit](#6-feature-by-feature-audit)
7. [Workflow จริงแยกตาม Role](#7-workflow-จริงแยกตาม-role)
8. [สรุปแยกตามหมวด](#8-สรุปแยกตามหมวด)
9. [Security Findings](#9-security-findings)
10. [Technical Debt](#10-technical-debt)
11. [สิ่งที่ยืนยันไม่ได้จาก source code](#11-สิ่งที่ยืนยันไม่ได้จาก-source-code)
12. [จุดที่ต้องตัดสินใจก่อนกำหนด Final Scope](#12-จุดที่ต้องตัดสินใจก่อนกำหนด-final-scope)
13. [สิ่งที่ควรตรวจสอบเพิ่มเติม](#13-สิ่งที่ควรตรวจสอบเพิ่มเติม-แนะนำ)

---

## 1. Executive Summary

### 1.1 ระบบนี้คืออะไร (ตามที่ code ทำจริง)

ระบบจอง + จัดการลานจอดรถ 3 role (`user` / `owner` / `admin`) บน Laravel 12 + PostgreSQL
โดยใช้ **Claude Vision API อ่านป้ายทะเบียนจากรูปถ่าย** เป็นกลไกหลักของการ check-in
มีระบบ marketplace, blacklist, payment (เงินสด/ยืนยันด้วยมือ), audit log และ owner application workflow

**ขนาดระบบ:** 37 controllers · 15 models · 5 middleware · 22 migrations · 13 business tables (+7 system tables) · **127 routes** · 85 blade views · 24 test files

### 1.2 Feature หลักและสถานะ

| # | Feature | สถานะ | ความเสี่ยง |
|---|---|---|---|
| 1 | Authentication (login/register/reset) | `[IMPLEMENTED]` | 🟢 ต่ำ |
| 2 | Email Verification | `[UNUSED]` — middleware เป็น no-op | 🔴 **สูง** |
| 3 | Role & Permission (3 roles) | `[IMPLEMENTED]` | 🟡 กลาง |
| 4 | User Reservation (จอง/แก้/ยกเลิก) | `[IMPLEMENTED]` | 🟡 กลาง |
| 5 | Admin Reservation Create | `[CONFLICT]` — สร้างแล้ว check-in ไม่ได้ (fatal) | 🔴 **สูง** |
| 6 | Parking Lot Management | `[IMPLEMENTED]` | 🟡 กลาง |
| 7 | Parking Slot Management (+bulk) | `[IMPLEMENTED]` | 🟢 ต่ำ |
| 8 | AI Car Scan (Claude Vision) | `[IMPLEMENTED]` | 🟡 กลาง |
| 9 | Auto Check-In (กรณีมีการจอง) | `[PARTIAL]` — ใช้ได้เฉพาะ booking ใหม่ | 🔴 **สูง** |
| 10 | Auto Check-In (Walk-in) | `[IMPLEMENTED]` | 🟡 กลาง |
| 11 | Check-Out + คำนวณค่าจอด | `[IMPLEMENTED]` | 🟢 ต่ำ |
| 12 | Payment (ยืนยันเงินสด) | `[PARTIAL]` — มัดจำไม่เคยถูกเก็บจริง | 🔴 **สูง** |
| 13 | Blacklist / Suspicious Vehicle | `[IMPLEMENTED]` | 🟢 ต่ำ |
| 14 | Notification (in-app) | `[IMPLEMENTED]` | 🟢 ต่ำ |
| 15 | Owner Application Workflow | `[IMPLEMENTED]` | 🟡 กลาง |
| 16 | Owner Revenue Report | `[PARTIAL]` — ตัวเลขไม่รวมมัดจำ | 🟡 กลาง |
| 17 | Admin Actions Log | `[PARTIAL]` — ช่องค้นหาพัง 500 | 🔴 **สูง** |
| 18 | Reservation Logs | `[PARTIAL]` — ซ่อนข้อมูล 62% | 🔴 **สูง** |
| 19 | Marketplace | `[UNUSED]` — ไม่มีลิงก์เข้าถึงเลย | 🟡 กลาง |
| 20 | User Vehicle Management | `[UNUSED]` — ไม่มี route (controller+view ยังอยู่) | 🟡 กลาง |
| 21 | Admin Vehicle CRUD | `[CONFLICT]` — ซ้อนกับ flow ใหม่ | 🟡 กลาง |
| 22 | Auto-Expire Reservation (scheduler) | `[IMPLEMENTED]` | 🟢 ต่ำ |
| 23 | Dashboard (admin/owner/user) | `[PARTIAL]` — ตัวเลขกับตารางไม่ตรงกัน | 🔴 **สูง** |
| 24 | Role/Permission table-based system | `[PLANNED]` — มี model ไม่มี table | 🟢 ต่ำ |
| 25 | CI (GitHub Actions) | `[UNUSED]` — trigger ผิด branch + ผิด DB | 🟡 กลาง |

### 1.3 Feature ที่ Stable (ใช้งานได้จริง เชื่อถือได้)

- Authentication + password reset + force password reset
- Parking Lot / Parking Slot CRUD (ทั้ง admin และ owner) + bulk create
- Blacklist (Suspicious Vehicle) CRUD + toggle + เชื่อมกับ AI scan
- Notification system (in-app)
- Owner Application (สมัคร → อนุมัติ/ปฏิเสธ → แก้ไขส่งใหม่ → ลาออกเอง)
- Check-Out + คำนวณค่าจอด + สร้าง payment + คืน slot
- Auto-expire reservation (scheduler ทุกนาที)
- AI Car Scan → เรียก Claude Vision → บันทึกผล → เทียบ blacklist

### 1.4 Feature ที่มีความเสี่ยงสูง (ต้องตัดสินใจก่อน)

| ปัญหา | ผลกระทบ | หลักฐาน |
|---|---|---|
| **Vehicle model ค้างครึ่งทาง** | Dashboard/log page ใช้ `INNER JOIN vehicles` ขณะที่ booking ใหม่ไม่สร้าง Vehicle → ข้อมูลหาย | ยืนยันแล้ว: reservation_logs ซ่อน 28/45 แถว |
| **Admin สร้างการจองแล้ว check-in ไม่ได้** | `TypeError` 500 | ยืนยันแล้วด้วย tinker |
| **มัดจำ (deposit) ไม่เคยเป็นรายได้** | บัญชีเงินผิด — หักส่วนลดโดยไม่เคยรับเงิน | ยืนยันแล้ว: ฿1,095 ค้างใน 46 รายการ |
| **ช่องค้นหา Admin Actions พัง** | 500 ทุกครั้งที่ค้นหา | ยืนยันแล้ว: SQLSTATE 22P02 |
| **Email verification เป็น no-op** | `verified` middleware ไม่ป้องกันอะไรเลย | ยืนยันแล้ว |
| **User ธรรมดา check-in เข้าลานคนอื่นได้** | ยึดช่องจอดในลานที่ไม่ใช่ของตัวเอง | code-read + ยืนยันขอบเขตลาน 8/8 |
| **เอกสารสมัคร owner เก็บบน public disk** | ดาวน์โหลดได้โดยไม่ต้อง login | code-read |
| **Test ของ booking พังหมด 7 ตัว** | Feature หลักไม่มี regression test | ยืนยันแล้ว: 7 failed / 119 passed |

---

## 2. Project Structure & Tech Stack

### 2.1 Structure

```
app/
├── Console/Commands/     1 command  (ExpireReservations)
├── Http/
│   ├── Controllers/
│   │   ├── Admin/        11 controllers
│   │   ├── Owner/         8 controllers
│   │   ├── User/          3 controllers (1 ตัวไม่มี route)
│   │   ├── Auth/          9 controllers (Breeze scaffold)
│   │   └── (root)         5 controllers
│   ├── Middleware/        5 custom middleware
│   └── Requests/          2 form requests
├── Models/               15 models (2 ตัวไม่มี table)
├── Providers/             1 (ว่างเปล่า)
├── Services/              3 services (CarScan, CheckIn, CheckOut)
├── Support/               2 global helper functions
└── View/Components/       2 layout components
```

**ข้อสังเกตเชิงสถาปัตยกรรม:** ไม่มี Repository layer · ไม่มี Policy / Gate (ใช้ `abort_if`/`abort_unless` ใน controller แทนทั้งหมด) · ไม่มี Form Request สำหรับ business form (validate ใน controller ตรงๆ) · ไม่มี Enum class (status เป็น string literal กระจายทั่ว codebase) · `AppServiceProvider` ว่างเปล่าทั้ง `register()` และ `boot()`

### 2.2 Tech Stack (จาก `composer.json` / `package.json` จริง)

| Layer | Technology | เวอร์ชัน |
|---|---|---|
| Runtime | PHP | `>=8.4` |
| Framework | Laravel | `^12.0` |
| Database | PostgreSQL | 18.4 (ยืนยันจาก `select version()`) |
| AI | `anthropic-ai/sdk` | `^0.30.0` |
| Auth scaffold | Laravel Breeze | `^2.3` (dev) |
| Frontend | Blade + Alpine.js `^3.4` + Tailwind `^3.1` | — |
| Build | Vite `^7.0` + `@tailwindcss/vite` `^4.0` | ⚠️ Tailwind 3 กับ plugin ของ v4 ปนกัน |
| Date picker | flatpickr `^4.6` | dependency เดียวที่เป็น runtime |
| Charts | Chart.js | โหลดผ่าน CDN/asset (ไม่อยู่ใน `package.json`) |
| Unit test | PHPUnit | `^11.5` |
| E2E test | Playwright | `^1.60` |

### 2.3 Configuration & Environment Dependencies

| Config | ที่มา | บังคับ? | ผลถ้าไม่ตั้ง |
|---|---|---|---|
| `DB_CONNECTION=pgsql` | `.env` | **บังคับ** | migration พังทันที (ใช้ raw SQL ของ Postgres) |
| `ANTHROPIC_API_KEY` | `.env` → `config/carscan.php` | บังคับถ้าใช้ AI scan | `RuntimeException` ตอนสแกน |
| `CARSCAN_MODEL` | `.env` (ปัจจุบัน `claude-opus-4-8`) | ไม่ | default `claude-opus-4-8` |
| `RESERVATION_GRACE_PERIOD` | `.env` → `config/parking.php` | ไม่ | default 30 นาที |
| `config/thai_provinces.php` | hardcoded | **บังคับ** | validation จังหวัดพัง |
| `config/car_colors.php` | hardcoded 13 สี | **บังคับ** | ต้องตรงกับ prompt ใน `CarScanService` เป๊ะๆ |
| `config/page_titles.php` | hardcoded 36 entries | ไม่ | **ครอบคลุมแค่ 36/65 GET routes** → 35 หน้าไม่มี title |

**PostgreSQL lock-in (code-read):** ระบบผูกกับ Postgres แน่นมาก ย้าย DB ไม่ได้โดยไม่แก้ code —
`ALTER TABLE ... ADD CONSTRAINT ... CHECK` (3 migrations) · `ALTER COLUMN ... DROP NOT NULL` · `UPDATE ... FROM` · `INTERVAL '1 hour'` (raw, 2 ที่) · `ilike` (7 ที่) · `TO_CHAR()` · `::text` cast

**⚠️ `.env` มี secret จริงอยู่ในเครื่อง** (`ANTHROPIC_API_KEY`, `MAIL_PASSWORD` ของ Gmail, `DB_PASSWORD`) — ถูก gitignore ถูกต้องแล้ว **แต่** `phpunit.xml` **ถูก commit เข้า repo พร้อม `DB_PASSWORD` แบบ plaintext** (ดูหัวข้อ 9)

---

## 3. Authentication, Roles & Middleware

### 3.1 Authentication `[IMPLEMENTED]`

Laravel Breeze มาตรฐาน: register / login / logout / forgot / reset / confirm password
มี rate limit 5 ครั้ง (`LoginRequest::ensureIsNotRateLimited`) · normalize email เป็น lowercase ทั้งตอน register และ login

**Redirect หลัง login** (`AuthenticatedSessionController::store`) — `admin` → `admin.dashboard`, **ที่เหลือทั้งหมด** → `user.dashboard`
→ owner จะถูกส่งไป `user.dashboard` ซึ่งติด `role:user` แล้ว `RoleMiddleware` เด้งกลับไป `owner.dashboard` อีกที
**ผลลัพธ์ถูกต้องแต่ผ่าน redirect 2 ต่อ** — `[PARTIAL]` (ทำงานได้ แต่ logic ไม่ตรงเจตนา)

### 3.2 Email Verification `[UNUSED]` — 🔴 ประเด็นสำคัญ

**หลักฐาน (ยืนยันแล้ว):**
```
User implements MustVerifyEmail: false
```
`app/Models/User.php` บรรทัด 5 — `// use Illuminate\Contracts\Auth\MustVerifyEmail;` **ถูก comment ทิ้งไว้**

Laravel `EnsureEmailIsVerified` จะ `return $next($request)` ทันทีถ้า user ไม่ implement interface นี้
→ **middleware `verified` ที่ติดอยู่บนเกือบทุก route ไม่ทำอะไรเลย**

สิ่งที่ยังมีอยู่แต่ใช้งานไม่ได้: route `verification.notice` / `verification.verify` / `verification.send` · view `auth/verify-email.blade.php` · `EmailVerificationPromptController` · `VerifyEmailController` · `EmailVerificationNotificationController` · test `EmailVerificationTest` (ผ่าน เพราะ test สร้าง user ที่ `email_verified_at` ถูก set อยู่แล้ว)

**สรุป:** โครงสร้าง email verification ครบทุกชิ้น แต่ถูกปิดที่จุดเดียวคือ model → ทั้ง feature เป็น dead weight

### 3.3 Roles

เก็บเป็น **string column** `users.role` + DB CHECK constraint `IN ('user','owner','admin')`
มี column เสริม `users.owner_status` CHECK `IN ('pending','approved','rejected')` หรือ NULL

| Role | สิทธิ์จริงตาม code |
|---|---|
| `user` | จอง/แก้/ยกเลิกการจองของตัวเอง · ดูประวัติจอด · AI scan · สมัคร owner · notification · profile |
| `owner` | ทุกอย่างของ lot ตัวเอง: lot/slot CRUD, ยืนยันการจอง, check-in/out, payment, revenue, AI scan, parking logs |
| `admin` | ทุกอย่างของ **lot ที่ไม่มีเจ้าของ (`owner_id IS NULL`) เท่านั้น** + user CRUD + blacklist + owner applications + audit logs |

**🔑 หลักการแบ่งขอบเขตที่สำคัญที่สุดของระบบ:**
> Admin จัดการได้เฉพาะลานที่ `owner_id IS NULL` — ลานที่มี owner เป็นของ owner คนนั้นเท่านั้น
> บังคับใช้ผ่าน `ParkingLot::unowned()` scope + `assertLotUnowned()` ในทุก controller ของ admin

**สถานะจริงใน DB (ยืนยันแล้ว):** ลานทั้งหมด 8 ลาน — **owner ถือ 6 ลาน, admin ดูแลแค่ 2 ลาน (lot#7, lot#8)**
→ Admin dashboard / reservations / payments / parking-logs แสดงข้อมูลแค่ 25% ของระบบเท่านั้น ซึ่งอาจไม่ตรงกับความคาดหวัง

### 3.4 Middleware Inventory

| Alias | Class | หน้าที่ | สถานะ |
|---|---|---|---|
| `admin` | `AdminMiddleware` | `role === 'admin'` ไม่งั้น 403 | `[IMPLEMENTED]` |
| `owner` | `OwnerMiddleware` | `role === 'owner'`; admin เด้งไป admin.dashboard | `[IMPLEMENTED]` |
| `owner.approved` | `OwnerApprovedMiddleware` | ต้อง `owner_status === 'approved'` | `[IMPLEMENTED]` |
| `role` | `RoleMiddleware` | `role:user` / `role:a,b` + redirect ตาม role | `[IMPLEMENTED]` — ใช้จริงแค่ `role:user` |
| `force.password.reset` | `ForcePasswordReset` | บังคับเปลี่ยนรหัสก่อนใช้งาน (whitelist 3 routes) | `[IMPLEMENTED]` |
| `verified` | Laravel builtin | — | `[UNUSED]` **no-op** (ดู 3.2) |

**ช่องโหว่ middleware ที่พบ (code-read):**
`force.password.reset` **ไม่ได้ติดอยู่บน route group ของ admin** (`routes/web.php:46`)
```php
Route::prefix('admin')->middleware(['auth', 'verified', 'admin'])  // ← ไม่มี force.password.reset
```
→ admin ที่โดน force reset ยังเข้าหน้า admin ได้ทุกหน้า ขณะที่ user/owner ถูกบล็อก — **ไม่สอดคล้องกัน**

**จุดดีที่ควรบันทึก:** `bootstrap/app.php` มี exception handler แปลง `ModelNotFoundException` → 403 บน `admin/*` เพื่อกัน **ID enumeration** (404 vs 403 oracle) — เป็นการป้องกันที่คิดมาดี

---

## 4. Route Inventory

**รวม 127 routes** (นับจาก `php artisan route:list --json`)

### 4.1 สรุปตามกลุ่ม

| กลุ่ม | จำนวน | Middleware | Role ที่เข้าถึงได้ |
|---|---|---|---|
| Public | 4 | `web` | ทุกคน (`/`, `/marketplace`, `/up`, `storage/{path}`) |
| Guest auth | 8 | `guest` | ยังไม่ login |
| Auth ทั่วไป | 6 | `auth` | ทุก role |
| Profile | 3 | `auth`,`force.password.reset` | ทุก role |
| Notifications | 3 | `auth`,`verified`,`force.password.reset` | ทุก role |
| Owner application | 5 | `auth`,`verified`,`force.password.reset` | ทุก role ที่ login |
| Admin | 47 | `auth`,`verified`,`admin` | admin |
| Owner dashboard | 2 | +`owner` | owner |
| Owner management | 28 | +`owner`,`owner.approved` | approved owner |
| User | 9 | +`role:user` | user |
| System (storage) | 2 | **ไม่มี middleware** | ทุกคน |

### 4.2 ผลตรวจสอบความถูกต้องของ Routes

**✅ ทุก route มี controller + method จริง** — ตรวจครบ 127 routes ไม่พบ method ที่หายไป
**✅ ไม่มี route ซ้ำซ้อน/ชนกัน** — ตรวจ `parking-slots/bulk` vs `parking-slots/{id}` แล้ว ไม่ชน เพราะ resource `except(['show'])` ทำให้ไม่มี `GET parking-slots/{id}`

**❌ `route()` ที่ถูกเรียกใน view แต่ route ไม่มีจริง** (ยืนยันด้วย script cross-check):

| Route name ที่ถูกเรียก | เรียกจาก | ผล |
|---|---|---|
| `user.vehicles.index` | `user/vehicles/create.blade.php`, `User/VehicleController.php` | **`RouteNotFoundException` ถ้าถูก render** |
| `user.vehicles.store` | `user/vehicles/create.blade.php` | เหมือนกัน |
| `user.vehicles.create` | `user/vehicles/index.blade.php` | เหมือนกัน |
| `user.vehicles.destroy` | `user/vehicles/index.blade.php` | เหมือนกัน |

> ⚠️ ปัจจุบัน **ไม่ระเบิด** เพราะไม่มี route ไหนชี้มาที่ view เหล่านี้ แต่เป็น landmine ที่รออยู่

**⚠️ Route ที่มีอยู่จริงแต่ไม่มีทางเข้าถึงจาก UI:**

| Route | สถานะ | หมายเหตุ |
|---|---|---|
| `marketplace.index` | `[UNUSED]` | **ไม่มีลิงก์จากที่ไหนเลยทั้งระบบ** (ยืนยันด้วย grep) — เข้าได้ด้วยการพิมพ์ URL เท่านั้น |
| `admin.reservations.create` / `.store` | `[UNUSED]` + `[CONFLICT]` | ไม่มีปุ่มใน UI + ถ้าใช้จะสร้าง data ที่ check-in ไม่ได้ (ดู 6.5) |
| `verification.notice` / `.verify` / `.send` | `[UNUSED]` | middleware เป็น no-op |
| `storage.local` / `storage.local.upload` | `[UNKNOWN]` | Laravel 12 auto-register, **ไม่มี middleware ใดๆ** |
| `password.reset` | ปกติ | ถูกเรียกจากอีเมล ไม่ใช่จาก view |

**⚠️ หน้า admin ที่ไม่อยู่ใน navigation bar** — เข้าถึงได้จาก admin dashboard เท่านั้น:
`admin.users.*`, `admin.parking-lots.*`, `admin.reservation-logs.*`, `admin.admin-actions.*`, `admin.owner-applications.*`
(navigation desktop มี 5 เมนู · mobile มี 8 เมนู · dashboard มีครบ 10 ลิงก์)

---

## 5. Database Inventory

### 5.1 ภาพรวม (row count ยืนยันจาก DB จริง 2026-09-13)

| Table | Rows | สถานะการใช้งาน |
|---|---|---|
| `users` | 32 | ใช้งานจริง |
| `parking_lots` | 8 | ใช้งานจริง |
| `parking_slots` | 305 | ใช้งานจริง |
| `vehicles` | 40 | **`[CONFLICT]` — legacy, flow ใหม่ไม่สร้าง** |
| `reservations` | 84 | ใช้งานจริง |
| `parking_logs` | 57 | ใช้งานจริง |
| `payments` | 44 | ใช้งานจริง |
| `license_plate_scans` | 46 | ใช้งานจริง |
| `suspicious_vehicles` | 6 | ใช้งานจริง |
| `notifications` | 104 | ใช้งานจริง |
| `reservation_logs` | 186 | ใช้งานจริง (แต่หน้าแสดงผลซ่อน 62%) |
| `admin_actions` | 66 | ใช้งานจริง (แต่ค้นหาพัง) |
| `owner_applications` | 5 | ใช้งานจริง |
| `sessions` / `cache` / `cache_locks` / `jobs` / `job_batches` / `failed_jobs` / `password_reset_tokens` | — | Laravel system tables |

**Table ที่ควรมีแต่ไม่มี:** `roles`, `permissions` — มี **model** `App\Models\Role` และ `App\Models\Permission` แต่ **ไม่มี migration ไม่มี table** และ **ไม่มี code อ้างอิงเลยแม้แต่ที่เดียว** (ยืนยันด้วย grep ทั่ว `app/ resources/ routes/ database/ tests/`) → `[PLANNED]` / dead code

### 5.2 รายละเอียดแต่ละ Table

#### `users`
| Column | Type | Null | หมายเหตุ |
|---|---|---|---|
| `id` | bigserial | PK | |
| `name` | string | ✗ | |
| `email` | string | ✗ | **UNIQUE** |
| `email_verified_at` | timestamp(0) | ✓ | มี column แต่ระบบไม่ใช้ (ดู 3.2) |
| `password` | string | ✗ | bcrypt |
| `role` | string | ✗ | default `user` · **CHECK** `IN ('user','owner','admin')` |
| `owner_status` | string | ✓ | **CHECK** `NULL OR IN ('pending','approved','rejected')` |
| `force_password_reset` | boolean | ✗ | default `false` |
| `remember_token`, `created_at`, `updated_at` | | | timestamps ความละเอียด 0 วินาที |

**Relationships:** `hasMany` vehicles, reservations, notifications, adminActions(`admin_id`), reservationChanges(`changed_by`), suspiciousVehiclesAdded(`added_by`), ownedParkingLots(`owner_id`) · `hasOne` ownerApplication

#### `parking_lots`
| Column | Type | Null | หมายเหตุ |
|---|---|---|---|
| `id` | bigserial | PK | |
| `owner_id` | FK→users | ✓ | **`nullOnDelete`** · `NULL` = admin ดูแล |
| `name` | string | ✗ | |
| `location` | text | ✓ | ซ้ำซ้อนกับ `address` |
| `address` / `district` / `province` / `landmark` | string | ✓ | |
| `total_slots` | integer | ✗ | **ไม่มีการ validate เทียบกับจำนวน `parking_slots` จริง** |
| `hourly_rate` | decimal(8,2) | ✗ | |
| `is_active` | boolean | ✗ | default `true` |
| `reservations_enabled` | boolean | ✗ | default `true` |

**⚠️ ไม่มี index บน `owner_id`** ทั้งที่ทุก query ของ owner/admin filter ด้วย column นี้
**⚠️ `total_slots` เป็นตัวเลขลอยๆ** ที่ผู้ใช้กรอกเอง ไม่ผูกกับ `parking_slots` จริง (ตอนนี้บังเอิญตรงกันทั้ง 8 ลาน เพราะ seeder ทำให้ตรง)
**⚠️ `location` กับ `address` ทำหน้าที่ทับกัน** — ทั้งคู่ nullable ไม่มีกฎว่าจะใช้ตัวไหน

#### `parking_slots`
| Column | Type | Null | หมายเหตุ |
|---|---|---|---|
| `id` | bigserial | PK | |
| `parking_lot_id` | FK→parking_lots | ✗ | **`cascadeOnDelete`** |
| `slot_number` | string | ✗ | unique เฉพาะระดับ application ไม่ใช่ DB |
| `status` | string | ✗ | default `available` · **CHECK** `IN ('available','reserved','occupied')` |

**⚠️ ไม่มี UNIQUE constraint `(parking_lot_id, slot_number)` ใน DB** — บังคับแค่ใน `Rule::unique()` ของ controller → race condition สร้างเลขซ้ำได้
**⚠️ ไม่มี index บน `parking_lot_id`** และ `status`

#### `vehicles` — `[CONFLICT]` legacy
| Column | Type | Null | หมายเหตุ |
|---|---|---|---|
| `id` | bigserial | PK | |
| `license_plate` | string | ✗ | **UNIQUE** |
| `brand` / `color` | string | ✗ | **NOT NULL** ระดับ DB |
| `user_id` | FK→users | ✗ | **`cascadeOnDelete`** |

**สถานะ:** ไม่มี route ให้ user สร้าง/ลบรถของตัวเองแล้ว (controller + view เหลืออยู่แต่ route ถูกถอด)
**เหลือทางเข้าเดียว:** `admin.vehicles.*` (CRUD ครบ) — admin ใส่รถให้ user ได้
**ยังถูกใช้โดย:** `CarScanService::scanAndSave()` (จับคู่ทะเบียน→vehicle_id), dashboard queries ทั้งหมด, `admin.reservations.create`

#### `reservations` — ตารางที่สะท้อนการเปลี่ยน requirement ชัดที่สุด
| Column | Type | Null | เพิ่มเมื่อ | หมายเหตุ |
|---|---|---|---|---|
| `id` | bigserial | PK | เริ่มแรก | |
| `user_id` | FK→users | ✗ | เริ่มแรก | `cascadeOnDelete` |
| `license_plate` | string | ✓ | เริ่มแรก | เก็บ **"ทะเบียน + จังหวัด"** รวมกัน |
| `brand` | string(60) | ✓ | **2026-07-20** | สำหรับเทียบกับผล AI |
| `color` | string(40) | ✓ | **2026-07-20** | สำหรับเทียบกับผล AI |
| `plate_province` | string(60) | ✓ | **2026-07-20** | แยกจังหวัดออกมาเพื่อไม่ต้อง parse |
| `vehicle_id` | FK→vehicles | ✓ | เริ่มแรก | `nullOnDelete` · **flow ใหม่ใส่ `null` เสมอ** |
| `parking_lot_id` | FK→parking_lots | ✗ | เริ่มแรก | **`cascadeOnDelete`** |
| `parking_slot_id` | FK→parking_slots | ✓ | เริ่มแรก | `nullOnDelete` |
| `reserve_start` | timestamp(0) | ✗ | เริ่มแรก | |
| `checked_in_at` / `completed_at` | timestamp(0) | ✓ | เริ่มแรก | |
| `reservation_fee` | decimal(8,2) | ✗ | เริ่มแรก | default 0 · **"มัดจำ" ที่ไม่เคยถูกเก็บจริง** |
| `status` | string | ✗ | เริ่มแรก | default `pending` · **INDEX** · **ไม่มี CHECK constraint** |

**Status ที่ใช้จริง (6 ค่า):** `pending` → `confirmed` → `checked_in` → `completed` / `cancelled` / `expired`
**⚠️ ไม่มี DB CHECK constraint บน `status`** ต่างจาก `users.role` และ `parking_slots.status` ที่มี → ไม่สอดคล้องกัน
**⚠️ ไม่มี index บน `parking_lot_id`, `user_id`, `reserve_start`** ทั้งที่ทุกหน้า query ด้วย column เหล่านี้

**🔴 หลักฐานการแยกยุคของข้อมูล (ยืนยันจาก DB):**
```
vehicle_id NOT NULL : 74 rows | 2026-06-21 .. 2026-07-19   ← ยุคเก่า (Vehicle-based)
vehicle_id IS NULL  : 10 rows | 2026-07-17 .. 2026-08-13   ← ยุคใหม่ (plate-based)

plate_province NULL : 78 / 84   |  brand NULL : 78 / 84  |  color NULL : 78 / 84
```
→ **มีแค่ 6 จาก 84 รายการเท่านั้นที่มีข้อมูลครบพอให้ AI auto check-in ทำงานได้**

#### `parking_logs`
| Column | Type | Null | เพิ่มเมื่อ | หมายเหตุ |
|---|---|---|---|---|
| `id` | bigserial | PK | | |
| `vehicle_id` | FK→vehicles | ✓ | เดิม NOT NULL → **เปลี่ยนเป็น nullable 2026-07-21** | ⚠️ **ไม่มี onDelete → RESTRICT** |
| `license_plate` | string(30) | ✓ | **2026-07-21** | migration backfill จาก vehicles แล้ว |
| `brand` (60) / `color` (40) | string | ✓ | **2026-07-21** | |
| `parking_lot_id` | FK→parking_lots | ✗ | | ⚠️ **ไม่มี onDelete → RESTRICT** |
| `parking_slot_id` | FK→parking_slots | ✓ | | `nullOnDelete` |
| `reservation_id` | FK→reservations | ✓ | | `nullOnDelete` · `NULL` = walk-in |
| `check_in_time` | timestamp(0) | ✗ | | |
| `check_out_time` | timestamp(0) | ✓ | | `NULL` = ยังจอดอยู่ |

**⚠️ `parking_lot_id` เป็น RESTRICT** → ลบลานจอดที่มีประวัติการจอดไม่ได้ (จะได้ FK violation 500)
`Admin\UserController::destroy` รู้ปัญหานี้และลบ `ParkingLog` ทิ้งก่อน — แต่ `Owner\ParkingLotController::destroy` **ไม่ได้ทำ** (ดู 6.6)
**⚠️ ไม่มี index บน `license_plate`** ทั้งที่เป็น key หลักของ duplicate guard และช่องค้นหา
**⚠️ ไม่มี index บน `check_out_time`** ทั้งที่ทุก query ใช้ `whereNull('check_out_time')`

#### `payments`
| Column | Type | Null | หมายเหตุ |
|---|---|---|---|
| `parking_log_id` | FK→parking_logs | ✗ | `cascadeOnDelete` · **UNIQUE** (1 log = 1 payment) |
| `reservation_id` | FK→reservations | ✓ | `nullOnDelete` |
| `total_hours` | decimal(8,2) | ✗ | ปัดขึ้นเป็นชั่วโมงเต็ม ขั้นต่ำ 1 |
| `hourly_rate` | decimal(8,2) | ✗ | snapshot เรทตอน checkout |
| `parking_fee` | decimal(10,2) | ✗ | `total_hours × hourly_rate` |
| `reservation_discount` | decimal(10,2) | ✗ | default 0 — **"มัดจำ" ที่หักออก** |
| `total_amount` | decimal(10,2) | ✗ | `parking_fee − reservation_discount` |
| `payment_status` | string | ✗ | default `unpaid` · **ไม่มี CHECK constraint** (ใช้จริง: `unpaid`/`paid`) |

**✅ ออกแบบดี:** snapshot `hourly_rate` ไว้ → ประวัติไม่เพี้ยนเมื่อ owner เปลี่ยนราคา

#### `license_plate_scans`
| Column | Type | Null | เพิ่มเมื่อ |
|---|---|---|---|
| `user_id` / `vehicle_id` | FK | ✓ | เริ่มแรก · `nullOnDelete` |
| `parking_lot_id` | FK→parking_lots | ✓ | **2026-08-02** · จำลอง "กล้องอยู่ลานไหน" |
| `license_plate` | string | ✗ | เก็บ **เฉพาะเลขทะเบียน** (ไม่รวมจังหวัด) |
| `province` | string(60) | ✓ | **2026-07-20** |
| `color` (60) / `brand` (60) | string | ✓ | |
| `confidence` | float | ✓ | 0–100 จาก Claude |
| `is_suspicious` | boolean | ✗ | snapshot ผลเทียบ blacklist ตอนสแกน |
| `source` | string(20) | ✗ | default `manual_upload` — **มีค่าเดียวทั้งระบบ** |
| `image_path` | string | ✓ | path บน public disk |
| `scan_time` | timestamp(0) | ✗ | |

**⚠️ `source` เป็น column ที่เตรียมไว้สำหรับกล้องอัตโนมัติแต่ไม่เคยมีค่าอื่น** — `history()` filter `where('source','manual_upload')` ตายตัว → `[PLANNED]` (เผื่ออนาคต)
**⚠️ ความไม่สอดคล้องเรื่องรูปแบบทะเบียน:** `license_plate_scans.license_plate` = เลขอย่างเดียว แต่ `reservations.license_plate` / `vehicles.license_plate` / `suspicious_vehicles.license_plate` = **เลข + จังหวัด** → ต้องใช้ `platePrefixMatch()` (`LIKE 'xxx %'`) ชดเชยตลอด

#### `suspicious_vehicles`
`license_plate` (**UNIQUE**) · `reason` ✓ · `level` (default `medium`, validate `low/medium/high` แค่ที่ controller **ไม่มี CHECK**) · `is_active` (default true) · `added_by` FK ✓ `nullOnDelete`

#### `reservation_logs`
`reservation_id` FK ✗ **cascadeOnDelete** · `old_status` ✓ · `new_status` ✗ · `changed_by` FK→users ✓ `nullOnDelete` (**`NULL` = ระบบทำเอง**) · `note` ✓
**⚠️ ไม่มี index เลย** แม้แต่บน `reservation_id`

#### `admin_actions`
`admin_id` FK ✓ · `action` ✗ · `subject_type` ✓ (class_basename) · `subject_id` bigint ✓ · `meta` **json** ✓ · `ip_address`(64) ✓ · `user_agent` text ✓
**✅ ตารางเดียวที่มี index ครบ:** `(action, created_at)`, `(subject_type, subject_id)`, `(admin_id, created_at)`

#### `owner_applications`
`user_id` FK ✗ cascadeOnDelete · `applicant_type` (individual/company) · `business_name` ✓ · `contact_name` ✗ · `phone`(20) ✗ · `email` ✗ · `parking_lot_name` ✗ · `address`/`district`/`province` ✓ · `description` text ✓ · `estimated_slots` uint · `document_path` ✓ · `status` ✗ · `rejection_reason` text ✓ · `reviewed_by` FK ✓ · `reviewed_at` ✓

**⚠️ ตารางเดียวที่ใช้ `timestamps()` ธรรมดา** (ความละเอียด microsecond) ขณะที่ตารางอื่นใช้ `timestamps(0)` → ไม่สอดคล้องกัน
**⚠️ ไม่มี CHECK constraint บน `status` และ `applicant_type`**
**⚠️ `parking_lot_name` / `estimated_slots` ที่กรอกตอนสมัคร ไม่เคยถูกใช้สร้างลานจอดจริง** — ตอนอนุมัติแค่เปลี่ยน role ไม่ได้สร้าง `parking_lots` ให้ → owner ต้องไปสร้างเองใหม่ทั้งหมด `[PARTIAL]`

### 5.3 สรุปปัญหาระดับ Schema

| # | ปัญหา | ระดับ |
|---|---|---|
| 1 | `vehicles` เป็น legacy แต่ยังถูก INNER JOIN ในหน้าสำคัญหลายหน้า | 🔴 |
| 2 | ขาด index เกือบทั้งระบบ (มีแค่ `admin_actions` + `reservations.status`) | 🟡 |
| 3 | CHECK constraint ใช้บ้างไม่ใช้บ้าง (`users.role` ✓, `reservations.status` ✗) | 🟡 |
| 4 | ไม่มี UNIQUE `(parking_lot_id, slot_number)` ระดับ DB | 🟡 |
| 5 | `parking_logs` FK เป็น RESTRICT → ลบลานพัง | 🔴 |
| 6 | รูปแบบ `license_plate` ไม่ตรงกันระหว่างตาราง | 🟡 |
| 7 | `total_slots` ไม่ผูกกับจำนวน slot จริง | 🟡 |
| 8 | `location` vs `address` ซ้ำซ้อน | 🟢 |
| 9 | `timestamps(0)` vs `timestamps()` ปนกัน | 🟢 |
| 10 | ไม่มี soft delete ที่ใดเลย — ลบแล้วหายถาวร | 🟡 |

---

## 6. Feature-by-Feature Audit

### 6.1 User Reservation (จองที่จอด) — `[IMPLEMENTED]` 🟡

| หัวข้อ | รายละเอียด |
|---|---|
| **Purpose** | ให้ user จองช่องจอดล่วงหน้าโดยกรอกทะเบียน/จังหวัด/ยี่ห้อ/สีเอง ไม่ต้องลงทะเบียนรถก่อน |
| **Routes** | `user.reservations.index/create/store/edit/update-plate/cancel` (6) |
| **Controller** | `User\ReservationController` (277 บรรทัด) |
| **Models** | `Reservation`, `ParkingLot`, `ParkingSlot`, `ParkingLog`, `ReservationLog` |
| **Tables** | `reservations`, `reservation_logs`, `parking_slots`, `parking_lots` |
| **Views** | `user/reservations/{index,create,edit}.blade.php` |

**Workflow จริง:**
1. `create()` → แสดงลานจาก `ParkingLot::reservable()` + slot ที่ `status='available'`
2. `store()` → validate 7 fields → ประกอบ `license_plate = "เลขทะเบียน + จังหวัด"` → เช็ค duplicate → เช็ค slot อยู่ในลานที่เลือก → เช็ค time overlap 1 ชม. → เช็ค `reservations_enabled` → สร้าง `status='pending'` + `reservation_fee = lot.hourly_rate` → เขียน `ReservationLog`
3. `edit/update` → แก้ทะเบียน/ยี่ห้อ/สีได้เฉพาะ `pending`/`confirmed`
4. `cancel` → เฉพาะ `pending`/`confirmed` → คืน slot ถ้าเป็น `reserved` → log + notify

**Evidence — ทำงานจริง:** `abort_unless($reservation->user_id === Auth::id(), 403)` ทุก method · duplicate guard · overlap check ด้วย raw SQL · เขียน audit log ทุกครั้ง

**❌ ปัญหาที่พบ:**

1. **Duplicate guard ตัวที่สองเป็น dead code** (`User/ReservationController.php:85-87`)
```php
$isParked = ParkingLog::whereNull('check_out_time')
    ->whereHas('vehicle', fn ($q) => $q->where('license_plate', $plate))  // ← vehicle_id = null เสมอ
    ->exists();
```
flow ใหม่ไม่มี `vehicle_id` → `whereHas('vehicle')` ไม่เคยเป็นจริง → **guard นี้ไม่ทำงานเลย**
(ยังพอมีตาข่ายรับที่ `CheckInService` ซึ่งเช็คจาก `parking_logs.license_plate` ตรงๆ)

2. **`reservable()` ไม่กรอง `is_active`** — `ParkingLot::scopeReservable()` เช็คแค่ `reservations_enabled`
→ ลานที่ owner กด **"ปิดใช้งาน"** (`is_active=false`) ยังโผล่ในฟอร์มจองและจองได้
→ ขัดกับ Marketplace ที่กรอง `is_active=true` **`[CONFLICT]`**

3. **`reservation_fee` = `hourly_rate` เสมอ** ไม่มีทางกำหนดเอง และ **ไม่เคยมีการเก็บเงินจริง** (ดู 6.8)

4. **Slot overlap สมมติว่าจอง 1 ชั่วโมงตายตัว** — hardcode `INTERVAL '1 hour'` ระบบไม่มีแนวคิด "จองถึงกี่โมง"

5. **จองได้โดยไม่เลือก slot** (`parking_slot_id` nullable) → `confirm()` จะไม่จอง slot ไว้ → ตอน check-in ไปหยิบ slot ว่างตัวไหนก็ได้

### 6.2 AI Car Scan — `[IMPLEMENTED]` 🟡

| หัวข้อ | รายละเอียด |
|---|---|
| **Purpose** | อัปโหลดรูปรถ → Claude Vision อ่านทะเบียน/จังหวัด/ยี่ห้อ/สี → จับคู่การจอง → auto check-in |
| **Routes** | `{admin,owner,user}.scan.create/store` + `{admin,owner}.scan.history` (8) |
| **Controller** | `CarScanController` (254 บรรทัด) — ใช้ร่วมกันทั้ง 3 role |
| **Services** | `CarScanService` (Claude Vision), `CheckInService` |
| **Tables** | `license_plate_scans`, `parking_logs`, `reservations`, `vehicles`, `suspicious_vehicles`, `notifications` |
| **Views** | `scan/index.blade.php` (ใช้ร่วม), `{admin,owner}/scan/history.blade.php` |

**Workflow จริง:**
1. เลือกลาน (จำลองตำแหน่งกล้อง) + อัปโหลดรูป (jpg/png ≤5MB)
2. `scanAndSave()` → เก็บไฟล์ลง `public/car-scans` → เรียก Claude → parse JSON (มี fallback ลอก markdown fence) → จับคู่ `Vehicle` ด้วย prefix match → เทียบ blacklist → บันทึก `license_plate_scans`
3. `findMatchingReservation()` → หา reservation `confirmed`/`checked_in`
4. ถ้า `confirmed` + อยู่ในช่วง `checkable()` → `matchScanAgainstReservation()` → ผ่านแล้วค่อย `CheckInService::checkIn()`
5. ถ้าไม่เจอ reservation → `attemptWalkInCheckIn()`

**Evidence — ทำงานจริง:** ใช้ SDK จริง (`anthropic-ai/sdk`) ไม่ใช่ mock · มี scan จริง 46 รายการใน DB · prompt บังคับเลือกสีจาก 13 สีที่ตรงกับ `config/car_colors.php` และจังหวัดจาก `config/thai_provinces.php`

**❌ ปัญหาที่พบ:**

1. **🔴 `matchScanAgainstReservation()` ผ่านไม่ได้สำหรับ 78/84 รายการ** (ยืนยันจาก DB)
```php
$provinceOk = $scannedProvince && trim($province) === trim($scannedProvince);  // บังคับ
return ['passed' => $provinceOk && ($brandOk || $colorOk)];
```
comment ใน code อ้างว่า *"จองใหม่ทุกรายการบังคับกรอกจังหวัด/ยี่ห้อ/สีอยู่แล้ว จึงไม่ต้องมี fallback"*
→ จริงเฉพาะ booking ที่สร้างผ่าน `User\ReservationController` หลัง 2026-07-20
→ **reservation จาก seeder / admin / ข้อมูลเก่า มี `plate_province`/`brand`/`color` = NULL ทั้งหมด → auto check-in ล้มเหลวถาวร** `[CONFLICT]`

2. **🔴 กิ่ง reservation ข้าม lot scope ทั้งหมด** (`CarScanController.php:139`)
```php
$result = $this->checkInService->checkIn(..., $reservation->parking_lot_id, null, $vehicleId);
//                                                                           ↑ allowedLotIds = null
```
- ลานที่เลือกตอนสแกน (จำลองกล้อง) **ถูกละทิ้ง** — check-in ไปที่ `$reservation->parking_lot_id` แทน
- `findMatchingReservation()` **ไม่ผูกกับ user ที่สแกน และไม่ผูกกับลานที่สแกน**
→ user คนใดก็ได้ อัปโหลดรูปรถของคนอื่น แล้วทำให้รถคันนั้น check-in เข้าลานใดก็ได้ในระบบ 🔴

3. **🔴 user ธรรมดาสแกน walk-in เข้าลานของ owner ได้** — `authorizedLots()` ของ role `user` = `ParkingLot::reservable()` = **ทั้ง 8 ลาน** (ยืนยันจาก DB รวมลานของ owner ทั้ง 6) → ยึดช่องจอดในลานคนอื่นได้

4. **`verify => false` ปิด SSL verification** (`CarScanService.php:23`)
```php
$guzzle = new GuzzleClient(['verify' => false]);   // Windows local dev
```
hardcode ไม่ผูกกับ `APP_ENV` → **ขึ้น production ก็ยังปิดอยู่** → MITM ได้ 🔴

5. **`user` ไม่มีหน้า scan history** — มีแค่ `admin`/`owner`

6. **ไม่มี rate limit / cost control** — เรียก Claude API ได้ไม่จำกัด ทุก role

7. **ไม่ลบไฟล์รูปที่สแกนทิ้ง** — สะสมใน `storage/app/public/car-scans` ไม่มีวันหมดอายุ

8. **`confidence` ถูกเก็บแต่ไม่เคยถูกใช้ตัดสินใจ** — AI อ่านผิดด้วยความมั่นใจ 20% ก็ผ่านเท่ากับ 99%

### 6.3 Auto Check-In — `[PARTIAL]` 🔴

| หัวข้อ | รายละเอียด |
|---|---|
| **Service** | `CheckInService::checkIn()` — จุดรวมของทุก check-in path |
| **ผู้เรียก** | `CarScanController` (2 จุด), `Admin\ReservationController::checkIn`, `Owner\ReservationController::checkIn` |

**Logic จริง:**
1. เช็คสิทธิ์ลาน (`$allowedLotIds`) — **ยกเว้นตอนถูกเรียกจากกิ่ง reservation ของ scan ที่ส่ง `null`**
2. Guard: ทะเบียนนี้มี `parking_logs` ที่ยังไม่ check-out อยู่หรือไม่
3. หา `Reservation::checkable()` — `confirmed` + `reserve_start` อยู่ในช่วง `[now−30นาที, now+5นาที]`
4. `DB::transaction` + `lockForUpdate()` — หยิบ slot ที่จองไว้ก่อน (`available`/`reserved`) ไม่งั้นหยิบ slot ว่างตัวใดก็ได้ในลาน
5. สร้าง `ParkingLog` → set slot = `occupied` → update reservation = `checked_in` → เขียน `ReservationLog` (`changed_by = null`)

**Evidence — ทำงานจริง:** มี `lockForUpdate()` กัน race condition · อยู่ใน transaction · duplicate guard ทำงานจาก `license_plate` ตรงๆ (ไม่พึ่ง Vehicle)

**❌ ปัญหา:**

1. **🔴 `checkIn(string $licensePlate, ...)` ไม่รับ `null` — ยืนยันแล้ว:**
```
TypeError: App\Services\CheckInService::checkIn(): Argument #1 ($licensePlate)
must be of type string, null given
```
`reservations.license_plate` เป็น nullable และ **`Admin\ReservationController::store()` ไม่ set ค่านี้เลย** → การจองที่ admin สร้าง กด check-in = **500 fatal**

2. **Grace period ±5 นาทีล่วงหน้า hardcode** (`Reservation::scopeCheckable`) ขณะที่ 30 นาทีย้อนหลังอยู่ใน config → ไม่สอดคล้อง

3. **`allowedLotIds = null` = ไม่ตรวจสิทธิ์เลย** — API design ที่อันตราย (ดู 6.2 ข้อ 2)

### 6.4 Check-Out + คำนวณค่าจอด — `[IMPLEMENTED]` 🟢

| หัวข้อ | รายละเอียด |
|---|---|
| **Service** | `CheckOutService::checkOut()` |
| **Routes** | `{admin,owner}.reservations.check-out` (รถที่จอง) + `{admin,owner}.parking-logs.check-out` (walk-in) |

**Logic:** คำนวณนาที → ปัดขึ้นชั่วโมง (ขั้นต่ำ 1) → `parking_fee = hours × rate` → `deposit = min(reservation_fee, parking_fee)` → `total = parking_fee − deposit` → สร้าง `Payment` (`paid` ถ้า total ≤ 0) → คืน slot → ปิด reservation เป็น `completed` → expire reservation ค้างของทะเบียนนั้น → notify

**✅ จุดแข็ง:** อยู่ใน transaction · guard ซ้ำซ้อน (`check_out_time` + `payment()->exists()`) · `min()` กันมัดจำเกินค่าจอด · snapshot `hourly_rate`

**⚠️ ข้อสังเกต:** `$log->parkingLot->hourly_rate` ไม่ได้ eager load → N+1 · ถ้า `parkingLot` ถูกลบไปแล้วจะ fatal (ไม่มี null check)

### 6.5 Admin Reservation Management — `[CONFLICT]` 🔴

| หัวข้อ | รายละเอียด |
|---|---|
| **Routes** | `admin.reservations.index/create/store/edit/update/destroy/confirm/check-in/check-out` (9) |
| **Controller** | `Admin\ReservationController` (419 บรรทัด — controller ใหญ่สุดในระบบ) |

**✅ ส่วนที่ทำงานได้:** `index` (ค้นหา/กรอง/paginate + คำนวณ `checkableIds`) · `confirm` (lock slot + notify + audit) · `checkIn`/`checkOut` (เรียก service) · `edit`/`update` · `destroy`

**🔴 `create()` + `store()` — ขัดแย้งกับ flow ปัจจุบันทั้งหมด:**

```php
// Admin\ReservationController::store()  — บรรทัด 126-160
$data = $request->validate([
    'vehicle_id' => ['required', 'exists:vehicles,id'],   // ← ยังบังคับใช้ Vehicle
    ...
]);
$reservation = Reservation::create([
    'vehicle_id' => $data['vehicle_id'],
    // ❌ ไม่มี license_plate
    // ❌ ไม่มี plate_province
    // ❌ ไม่มี brand
    // ❌ ไม่มี color
]);
```

**ผลลัพธ์ที่ยืนยันแล้ว:**
1. การจองที่ admin สร้างจะมี `license_plate = NULL`
2. กดปุ่ม **Check-In** → `CheckInService::checkIn(null, ...)` → **`TypeError` 500**
3. ต่อให้แก้ให้ผ่านไปได้ `matchScanAgainstReservation()` ก็ล้มเหลวเพราะ `brand`/`color`/`plate_province` เป็น NULL → AI auto check-in ใช้ไม่ได้
4. **ไม่มีปุ่มไหนใน UI ชี้มาที่ `admin.reservations.create`** (ยืนยันด้วย grep) → ปัจจุบันยังไม่เกิดปัญหาจริง แต่ route เปิดอยู่

**สรุป:** นี่คือ **ซากของ flow เก่า** ที่ยังไม่ถูกอัปเดตหรือถอดออก — `[CONFLICT]` + `[UNUSED]`

### 6.6 Parking Lot Management — `[IMPLEMENTED]` 🟡

| | Admin | Owner |
|---|---|---|
| Controller | `Admin\ParkingLotController` | `Owner\ParkingLotController` |
| ขอบเขต | `owner_id IS NULL` เท่านั้น | `owner_id = Auth::id()` |
| Actions | index/create/store/edit/update/destroy | + **toggle** (เปิด/ปิดลาน) |
| Guard | `abort_if($lot->owner_id !== null, 403)` | `abort_if($lot->owner_id !== Auth::id(), 403)` |

**❌ ปัญหา:**

1. **🔴 `Owner::destroy()` จะ 500 ถ้าลานมีประวัติการจอด**
```php
if ($lot->slots()->whereIn('status', ['occupied','reserved'])->exists()) { return back()->withErrors(...); }
$lot->delete();   // ← parking_logs.parking_lot_id เป็น RESTRICT
```
guard เช็คแค่ slot status ปัจจุบัน แต่ `parking_logs` ที่ check-out ไปแล้วยังอ้าง `parking_lot_id` อยู่
เทียบกับ `Admin\UserController::destroy` ที่รู้ปัญหานี้และลบ `ParkingLog` ก่อน → **ความรู้นี้ไม่ถูกนำมาใช้ที่นี่** `[CONFLICT]`

2. **Admin ตั้ง `owner_id` ตอน create ได้** แต่ `index()` แสดงเฉพาะ `unowned()` → ลานหายจากหน้าจอทันทีที่สร้าง และแก้ไขต่อไม่ได้ (`edit` จะ 403)

3. **Admin ตั้ง `owner_id` ตอน update ได้** → ยกลานให้ owner แล้วตัวเองเข้าไม่ได้อีก (one-way door ไม่มีคำเตือน)

4. **`toggle` (ปิดลาน) ไม่ส่งผลกับการจอง** — ดู 6.1 ข้อ 2

5. **Owner ไม่มี audit log** — admin ทุก action เขียน `admin_actions` แต่ owner ไม่เขียนอะไรเลย

### 6.7 Parking Slot Management — `[IMPLEMENTED]` 🟢

Admin/Owner มี CRUD + **bulk create 2 โหมด** (range `A001–A050` / list คั่นด้วย comma หรือขึ้นบรรทัดใหม่)
มี pre-check ชื่อซ้ำในลานเดียวกัน + `DB::transaction` + `ParkingSlot::insert()` แบบ batch

**⚠️ `store`/`update` ให้ตั้ง `status` เองได้ทั้ง 3 ค่า** → admin/owner ตั้งเป็น `occupied` ได้โดยไม่มี `ParkingLog` รองรับ → **slot status drift** (ตอนนี้ยังไม่ drift: `occupied=13` = `active logs=13`)
**⚠️ ไม่ตรวจว่าลบ slot ที่กำลัง `occupied` อยู่หรือไม่** → ลบได้เลย ทำให้ `parking_logs.parking_slot_id` กลายเป็น NULL

### 6.8 Payment — `[PARTIAL]` 🔴

| หัวข้อ | รายละเอียด |
|---|---|
| **Routes** | `{admin,owner}.payments.index/mark-paid` (4) |
| **Purpose** | ยืนยันการรับเงินสด (ไม่มี payment gateway — ตั้งใจตามที่ README ระบุ) |

**✅ ทำงานจริง:** สร้าง Payment อัตโนมัติตอน check-out · กรองตาม `unpaid`/`paid`/`all` · `markPaid` มี guard สิทธิ์ลาน + กันกดซ้ำ · admin เขียน audit log

**🔴 ปัญหาใหญ่: "ค่ามัดจำ" ไม่เคยมีอยู่จริงในเชิงบัญชี**

ยืนยันจาก DB:
```
payments: 44 rows
  sum(parking_fee)           = 18,790.00
  sum(reservation_discount)  =    680.00     ← "มัดจำ" ที่ถูกหักออก
  sum(total_amount)          = 18,110.00
payment rows ที่ไม่มี parking_log = 0        ← ไม่มี payment record ของมัดจำเลย

reservations ที่ expired/cancelled + fee > 0 : 46 รายการ รวม ฿1,095.00
  → payment record ที่เกิดจากรายการเหล่านี้ : 0
```

**ความหมาย:**
1. `reservation_fee` ถูกตั้งค่าตอนจอง (= `hourly_rate`) แต่ **ไม่เคยมี flow ไหนเก็บเงินก้อนนี้**
2. ตอน check-out มันถูกใช้เป็น **ส่วนลดล้วนๆ** — ลดยอดที่ลูกค้าต้องจ่ายลง ฿680 โดยที่เจ้าของลานไม่เคยได้รับเงินก้อนนั้นมาก่อน
3. → **เจ้าของลานขาดรายได้ ฿680** จากข้อมูลชุดปัจจุบัน
4. การจองที่ `expired`/`cancelled` มี "มัดจำ" ฿1,095 ที่หายไปเฉยๆ ไม่มีทั้งรายรับและการคืนเงิน
5. README อ้างว่า *"แสดงค่ามัดจำและค่าจอดเป็นช่องแยกกัน"* — ✅ แสดงจริง **แต่ตัวเลขมัดจำสื่อความหมายว่าเก็บเงินไปแล้ว ซึ่งไม่จริง**

**นี่เป็นช่องว่างเชิง business logic ที่ต้องตัดสินใจก่อนกำหนด scope** (ดูหัวข้อ 12)

### 6.9 Owner Revenue Report — `[PARTIAL]` 🟡

`Owner\RevenueController` (109 บรรทัด) — 8 metrics: revenue/unpaid total, transaction count, reservation count, revenue by lot, revenue by day, top lot, occupancy rate
กรองตาม `today`/`month`/`year` + เลือกลาน

**⚠️ ปัญหา:**
1. **รายได้นับจาก `total_amount` (ยอดหลังหักมัดจำ)** → ตัวเลขต่ำกว่าความจริงตามข้อ 6.8
2. **ไม่มีตัวเลือก `week`** ทั้งที่ dashboard admin มี `7d`
3. **`occupancyRate` คำนวณจาก slot status ณ ขณะนี้** ไม่ใช่ค่าเฉลี่ยตามช่วงเวลาที่เลือก → ตัวเลขไม่สัมพันธ์กับ filter
4. **8 queries แยกกัน** ไม่มี cache

### 6.10 Dashboard — `[PARTIAL]` 🔴

#### ปัญหาร่วมของทั้ง 3 dashboard: **INNER JOIN กับ `vehicles`**

**Admin dashboard** (`DashboardController::admin`) — 4 ตารางใช้ `->join('vehicles as v', 'v.id','=','pl.vehicle_id')`:
`activeNow`, `unpaidPayments`, `reservations`, `recentHistory`

**User dashboard** (`DashboardController::user`) — 5 query ใช้ INNER JOIN:
`activeLog`, `activeReservation`, `activeNowList`, `recentReservations`, `recentHistory`

**Owner dashboard** (`Owner\DashboardController::index`) — 2 query:
`recentReservations`, `activeNow`

**ขณะที่ stat card ด้านบน** นับจาก `parking_logs` / `reservations` **โดยตรง ไม่ join**

**ผลที่ยืนยันแล้ว:**
```
Admin dashboard:  stat card "active_now" = 5  |  ตาราง activeNow (JOIN) = 5   ← ตรงกันตอนนี้
User parking-logs page:  แสดง 56 / มีจริง 57   ← หาย 1 แถวแล้ว
Reservation logs page:   แสดง 17 / มีจริง 45   ← หาย 28 แถว (62%)
```

**ทำไมตอนนี้ยังไม่พังหนัก:**
- `migration 2026_07_21_010000` **backfill** `parking_logs.license_plate` จาก `vehicles` ให้แล้ว
- `CheckInService` ส่ง `vehicle_id` ต่อจาก `reservation->vehicle_id ?? scan->vehicle_id` → ถ้า `CarScanService` จับคู่ `Vehicle` จากทะเบียนได้ ก็ยังมี `vehicle_id`
- DB ปัจจุบันมี `vehicles` 40 คัน (จาก seeder) ที่บังเอิญ match กับทะเบียนที่ใช้ทดสอบ

**ทำไมมันจะพังแน่นอน:**
- **ไม่มี route ให้ user สร้าง Vehicle อีกแล้ว** → user ใหม่ทุกคนมี 0 vehicles
- user ใหม่จอง → `vehicle_id = null` → check-in → `parking_logs.vehicle_id = null` → **หายจากทุก dashboard และจากหน้าประวัติของตัวเอง**
- ปัจจุบันมี `parking_logs` แบบนี้แล้ว **1 แถว** และจะเพิ่มขึ้นเรื่อยๆ ทุกครั้งที่มีคนใช้งานจริง

#### ปัญหาเฉพาะจุด

- **`DashboardController::user` — `lotsAvailable` ไม่กรอง `is_active` และไม่กรอง `owner_id`** → แนะนำลานที่ปิดอยู่
- **`DashboardController::user` — `slotStats` นับ slot ทั้งระบบ** รวมลานของ owner ทุกคน → user เห็นตัวเลขที่ไม่เกี่ยวกับตัวเอง
- **`DashboardController::admin` — `latestScans` ไม่กรอง lot ownership** → admin เห็นผลสแกนของลาน owner ด้วย (ขัดกับหลักการแบ่งขอบเขตในข้อ 3.3)
- **`admin` มี `$q` (ค้นหาทะเบียน) แต่ค้นจาก `v.license_plate`** (ตาราง vehicles) ไม่ใช่ `pl.license_plate` → ค้นหา walk-in ไม่เจอ
- **`DashboardController::user` มี dead code:** `$userId = Auth::id();` แล้ว `$userId = $user->id;` ซ้ำอีกครั้ง (บรรทัด 14 และ 24)
- **`stats['my_vehicles']`** ยังแสดงจำนวนรถที่ลงทะเบียน ทั้งที่ user สร้างรถเองไม่ได้แล้ว → แสดง 0 เสมอสำหรับ user ใหม่

### 6.11 Reservation Logs (Admin) — `[PARTIAL]` 🔴

**🔴 ยืนยันแล้วว่าซ่อนข้อมูล 62%**
```
reservation_logs (ลาน unowned) ทั้งหมด = 45 แถว
หน้าเว็บแสดงจริง                        = 17 แถว
หายไป                                   = 28 แถว (62%)

สาเหตุ:
  changed_by IS NULL   → หาย 27 แถว
  r.vehicle_id IS NULL → หาย  2 แถว
```

**สาเหตุใน code** (`Admin\ReservationLogController::index` + `export`):
```php
->join('users as u', 'u.id', '=', 'rl.changed_by')      // ❌ INNER JOIN แต่ changed_by nullable
->join('vehicles as v', 'v.id', '=', 'r.vehicle_id')    // ❌ INNER JOIN แต่ vehicle_id nullable
```

**ความรุนแรง:** `changed_by = NULL` คือ **action ที่ระบบทำเอง** ซึ่งเป็น event สำคัญที่สุดทั้งหมด:
- `Auto check-in: รถเข้าจอดที่ช่อง XX` (จาก `CheckInService`)
- `Auto completed: รถออกจากลานแล้ว` (จาก `CheckOutService`)
- `Auto-expired: เกินเวลาเช็คอิน 30 นาที` (จาก `ExpireReservations`)

→ **Audit log ของระบบ AI check-in ทั้งหมดมองไม่เห็นในหน้า Audit Log** และ CSV export ก็ขาดเหมือนกัน
→ feature นี้ **ไม่สามารถใช้เป็นหลักฐานตรวจสอบได้** ซึ่งขัดกับวัตถุประสงค์ของมันเอง

### 6.12 Admin Actions Log — `[PARTIAL]` 🔴

**✅ การบันทึกทำงานดี** — `admin_audit()` helper, 66 rows, index ครบ, มี `meta` JSON + IP + user agent, wrap ด้วย try/catch ไม่ให้ล่มเว็บ

**🔴 ช่องค้นหาพัง 500 — ยืนยันแล้ว:**
```php
// Admin\AdminActionController::index บรรทัด 33
->orWhere('aa.subject_id', '::text', 'like', "%{$q}%");   // ❌ '::text' ไม่ใช่ SQL operator
```
Laravel เห็น `'::text'` เป็น operator ที่ไม่ถูกต้อง → สลับเป็น `subject_id = '::text'` → Postgres ปฏิเสธ:
```
SQLSTATE[22P02]: Invalid text representation: 7
ERROR: invalid input syntax for type bigint: "::text"
```
**ผล: พิมพ์อะไรก็ตามในช่องค้นหาของหน้า Admin Actions Log = 500 ทันที**

**⚠️ ปัญหารอง:**
- `index()` กับ `export()` มี filter logic **ไม่เหมือนกัน** — `index` ครอบ `where(function(){...})` แต่ `export` ไม่ครอบ → เงื่อนไข `orWhere` รั่วออกนอกวงเล็บ ทำให้ CSV ได้ผลต่างจากที่เห็นบนหน้าจอ
- `SuspiciousVehicleController::index()` เขียน audit ทุกครั้งที่ **เปิดดูหน้า** → log บวมด้วย event ที่ไม่มีความหมาย
- **Owner ไม่มี audit log เลย** ทั้งที่ทำ action สำคัญได้ (confirm, check-in/out, mark paid, ลบลาน)

### 6.13 Blacklist / Suspicious Vehicle — `[IMPLEMENTED]` 🟢

CRUD ครบ + toggle + ค้นหา (`ilike`) + ระดับ low/medium/high + audit ทุก action
**เชื่อมกับ AI scan จริง:** `CarScanService::scanAndSave()` เทียบ `SuspiciousVehicle::active()` ด้วย prefix match → บันทึก `is_suspicious` → `CarScanController::notifySuspiciousVehicle()` แจ้ง owner ของลาน + admin ทุกคน **โดยไม่บล็อกการเช็คอิน** (ตรงตามเจตนาที่ระบุใน README)

**⚠️ Blacklist เป็น global** — admin เป็นคนจัดการทั้งหมด **owner เพิ่มทะเบียนเข้า blacklist เองไม่ได้** (มีแต่รับ notification)

### 6.14 Notification — `[IMPLEMENTED]` 🟢

ตารางของตัวเอง (ไม่ใช่ Laravel Notifications) · helper `notify_user()` global · 104 rows · unread ขึ้นก่อน · markRead/markAllRead + guard ownership

**Event ที่แจ้งจริง:** ยืนยันการจอง · ยกเลิก (user/admin) · หมดอายุ · check-in (manual + auto + walk-in) · check-out พร้อมยอดเงิน · พบรถ blacklist · owner application (ส่ง/อนุมัติ/ปฏิเสธ/ลาออก) · ลานถูกลบ

**⚠️ ไม่มีอีเมล/push** — in-app อย่างเดียว (ตั้ง SMTP ไว้ใน `.env` แต่ใช้แค่ password reset)
**⚠️ ไม่มีการลบ notification เก่า** — สะสมไปเรื่อยๆ

### 6.15 Owner Application Workflow — `[IMPLEMENTED]` 🟡

**Flow:** user สมัคร (`owner.application.store`) → `owner_status='pending'` (role ยังเป็น `user`) → admin ดู/อนุมัติ/ปฏิเสธ → อนุมัติ: `role='owner'` + `owner_status='approved'` / ปฏิเสธ: `owner_status='rejected'` + เหตุผล → user แก้ไขส่งใหม่ได้ → owner ลาออกเองได้ (`demote-self`, ต้องระบุเหตุผล)

**✅ จุดแข็ง:** transaction ครบ · แจ้ง admin ทุกคน · เก็บเหตุผลปฏิเสธ · resubmit ได้ · `orderByRaw` ให้ pending ขึ้นก่อน

**❌ ปัญหา:**

1. **ข้อมูลที่กรอกตอนสมัครไม่ถูกใช้งานต่อ** — `parking_lot_name`, `estimated_slots`, `address`, `district`, `province` เก็บไว้เฉยๆ ตอนอนุมัติไม่ได้สร้าง `ParkingLot` ให้ → owner ต้องกรอกใหม่ทั้งหมด `[PARTIAL]`

2. **🔴 `Admin\UserController::update()` เปลี่ยน role ได้โดยไม่แตะ `owner_status`**
```php
$user->update(['name'=>..., 'email'=>..., 'role'=>$data['role']]);   // ❌ ไม่ set owner_status
```
- `user` → `owner` ผ่านหน้า edit: `owner_status` ยังเป็น `NULL` → `OwnerApprovedMiddleware` บล็อกทุกหน้า management → **owner ที่ใช้งานอะไรไม่ได้เลย**
- `owner` → `user`: `owner_status` ค้างเป็น `approved`

เทียบกับ `store()` ที่ set `'owner_status' => $data['role']==='owner' ? 'approved' : null` ถูกต้อง → **`store` กับ `update` ไม่สอดคล้องกัน** `[CONFLICT]`

3. **เอกสารแนบเก็บบน public disk** (ดู 9)

### 6.16 Marketplace — `[UNUSED]` 🟡

`MarketplaceController` (44 บรรทัด) + `marketplace/index.blade.php` — public route ไม่ต้อง login
แสดงลาน `is_active=true` + จำนวนช่องว่าง/ใช้งาน + ชื่อเจ้าของ + ค้นหา + เรียงตามช่องว่าง/ราคา + paginate 12

**🔴 ไม่มีลิงก์ชี้มาที่หน้านี้จากที่ใดเลยในระบบ** (ยืนยันด้วย grep ทั่ว `resources/views`)
- `welcome.blade.php` มีแค่ `dashboard` / `login` / `register`
- `navigation.blade.php` ไม่มี
- dashboard ทุก role ไม่มี

**→ feature ที่สมบูรณ์ ทำงานได้ แต่ผู้ใช้เข้าถึงไม่ได้เลย** นอกจากพิมพ์ `/marketplace` เอง

**⚠️ Marketplace ไม่มีปุ่ม "จอง"** — ดูอย่างเดียว ไม่เชื่อมกับ `user.reservations.create`
**⚠️ เปิดเผยชื่อเจ้าของลาน (`u.name`) ต่อสาธารณะ** โดยไม่ได้ขออนุญาต

### 6.17 User Vehicle Management — `[UNUSED]` 🟡

`User\VehicleController` (56 บรรทัด: index/create/store/destroy) + view 2 ไฟล์ **ยังอยู่ครบ**
`routes/web.php:6` ยัง `use App\Http\Controllers\User\VehicleController as UserVehicleController;` **แต่ไม่มี route สักตัว**

**→ dead code สมบูรณ์แบบ** — และ view ทั้ง 2 ไฟล์อ้าง route ที่ไม่มีอยู่จริง (จะ `RouteNotFoundException` ถ้าถูก render)

**หลักฐานว่าเคยมี:** `e2e/screenshots/audit/` มี `3_1_vehicles_list.png`, `7_7_user_vehicles.png`, `16_2_user_vehicles.png`, `user_vehicles_{desktop,mobile,tablet}.png` — **screenshot ของ feature ที่ถูกถอดออกไปแล้ว**

### 6.18 Admin Vehicle CRUD — `[CONFLICT]` 🟡

`Admin\VehicleController` CRUD ครบ + audit log — **ยังใช้งานได้จริง** มีลิงก์จาก dashboard และ mobile nav

**ความขัดแย้ง:** เป็นทางเดียวที่จะสร้าง `Vehicle` ได้แล้ว แต่ flow การจองปัจจุบันไม่ใช้ `Vehicle` เลย
→ **บทบาทของ feature นี้คลุมเครือ:** เป็น master data ที่ไม่มีใครใช้? หรือเป็นตัวช่วยให้ dashboard join ติด?
→ ปัจจุบันมันทำหน้าที่ **"พยุงไม่ให้ dashboard ว่างเปล่า"** โดยบังเอิญ ไม่ใช่โดยเจตนา

**⚠️ `destroy()` ไม่เช็ค `parking_logs`** — `parking_logs.vehicle_id` เป็น `nullOnDelete` จึงไม่ FK error แต่จะทำให้ log กลายเป็น orphan และหายจากทุก dashboard ทันที

### 6.19 Auto-Expire Reservation — `[IMPLEMENTED]` 🟢

`ExpireReservations` command + `Schedule::command('reservations:expire')->everyMinute()`
มี `--dry-run` · transaction · bulk update + bulk insert log · คืน slot เฉพาะที่ `reserved` (ไม่แตะ `occupied`) · notify นอก transaction

**✅ เขียนได้ดีที่สุดในระบบ** — logic ชัด ปลอดภัย มี test (`ExpireReservationsTest` ผ่าน)
**⚠️ ต้องมี `php artisan schedule:work` รันค้างไว้** ไม่งั้น reservation ไม่หมดอายุ (ตาข่ายรับ: `CheckOutService` expire ทะเบียนเดียวกันตอน check-out)

### 6.20 Error Handling — `[PARTIAL]` 🟡

**มี:** exception handler กัน ID enumeration (`bootstrap/app.php`) · try/catch ใน `admin_audit()` และ `notify_user()` · `RuntimeException` handling ตอนสแกน · JSON parse fallback

**ไม่มี:** custom error pages (404/403/500) · error logging แบบมีโครงสร้าง (`Log::info` แค่ที่เดียวใน `CarScanService`) · handling ของ Anthropic API timeout/rate limit/network error (จับแค่ `RuntimeException` — `ConnectException` จาก Guzzle จะหลุดเป็น 500)
**⚠️ `APP_DEBUG=true` ใน `.env` ปัจจุบัน** — เหมาะกับ dev แต่ห้ามขึ้น production

### 6.21 Tests — `[PARTIAL]` 🔴

**ผลรันจริง (ยืนยันแล้ว): `7 failed, 119 passed (324 assertions)` ใน 33.46s**

| Test file | ผล |
|---|---|
| `ReservationTest` | **5 failed** |
| `ReservationDepositTest` | **1 failed** |
| `LotReservationsEnabledTest` | **1 failed** |
| อีก 21 ไฟล์ | ผ่านหมด |

**สาเหตุที่พัง (ยืนยันจาก error output):**
```
กรุณากรอกเลขทะเบียนรถ / กรุณาเลือกจังหวัด / กรุณากรอกยี่ห้อรถ / กรุณาเลือกสีรถ
```
test ยังส่ง `vehicle_id` ตาม API เก่า แต่ controller ต้องการ `plate_number` + `plate_province` + `brand` + `color`
→ **test ไม่ถูกอัปเดตตอนเปลี่ยน requirement** → **feature การจองซึ่งเป็นหัวใจของระบบ ไม่มี regression test ที่ใช้งานได้**

**🔴 Factory ก็ยังเป็นของเก่า:**
```php
// ReservationFactory — ไม่มี license_plate, plate_province, brand, color
'vehicle_id' => Vehicle::factory(),

// ParkingLogFactory — ไม่มี license_plate, brand, color
'vehicle_id' => Vehicle::factory(),
```
→ test ที่ "ผ่าน" (เช่น `CheckInTest`, `OcrCheckInTest`) กำลังทดสอบกับ **data shape ที่ระบบจริงไม่ได้ผลิตแล้ว** → **false confidence**

**🔴 Seeder ก็เป็นของเก่า และมันปิดบังบั๊ก:**
```
DatabaseSeeder สร้าง reservations โดยใส่ทั้ง license_plate และ vehicle_id  → ข้อมูลทรง "ยุคเก่า"
DatabaseSeeder สร้าง parking_logs โดย "ไม่ใส่ license_plate เลย" (ยืนยัน: 0 ครั้งใน 4 จุด)
DatabaseSeeder ไม่เคยใส่ plate_province / brand / color ในทุก reservation
```
ผลกระทบ:
1. `migrate:fresh --seed` ใหม่ → `parking_logs.license_plate` เป็น **NULL ทั้งหมด** → หน้า parking-logs แสดงทะเบียนว่าง + ค้นหาไม่เจอ + duplicate guard ของ `CheckInService` ใช้ไม่ได้
2. reservation ที่ seed มา **auto check-in ด้วย AI ไม่ได้เลยสักรายการ** (ไม่มี province/brand/color)
3. **เพราะ seeder ใส่ `vehicle_id` ให้ทุกแถว → dashboard ดูปกติดี** → บั๊ก INNER JOIN ถูกซ่อนไว้จนกว่าจะมีคนใช้งานจริง

**E2E (Playwright):** มี config 5 projects + utils ครบ + `qa:audit` script + report/screenshot เก่าจำนวนมาก
**⚠️ `e2e/.auth/*.json` (session token) ถูก commit เข้า repo**
**⚠️ screenshot อ้างถึง feature ที่ไม่มีแล้ว** — `admin_devices_*.png` (**ไม่เคยมี code ของ "devices" ในระบบเลย**), `user_vehicles_*.png`

**⚠️ ไม่มี test สำหรับ:** Owner ทุก controller · Marketplace · Notification · Owner Application · Payment · Admin Actions · Dashboard queries · Middleware

### 6.22 CI/CD — `[UNUSED]` 🟡

`.github/workflows/tests.yml` เป็น **Laravel skeleton ดั้งเดิม ไม่เคยถูกแก้:**
```yaml
on:
  push:
    branches: [master, '*.x']       # ❌ repo ใช้ branch "main" → ไม่เคย trigger
...
    extensions: ..., sqlite, pdo_sqlite    # ❌ ระบบใช้ PostgreSQL
    run: cp .env.example .env              # ❌ ไม่มี postgres service container
```
**→ CI ไม่เคยรัน และถ้ารันก็จะพังทันทีที่ migration** (raw Postgres SQL)
workflow อีก 3 ตัว (`issues.yml`, `pull-requests.yml`, `update-changelog.yml`) ก็เป็นของ skeleton เช่นกัน

---

## 7. Workflow จริงแยกตาม Role

### 7.1 User (ผู้จอง)

```
สมัคร (register)
   └─ role='user', owner_status=NULL, email_verified_at=NULL
   └─ ⚠️ ไม่มีการยืนยันอีเมลจริง (verified middleware เป็น no-op) → ใช้งานได้ทันที
   ↓
login → redirect user.dashboard
   ↓
┌─ จองที่จอด ──────────────────────────────────────────────────────┐
│ user.reservations.create                                          │
│   เลือกลาน (จาก reservations_enabled=true)                         │
│      ⚠️ ไม่กรอง is_active → ลานที่ปิดอยู่ก็ยังเลือกได้                │
│   กรอก: เลขทะเบียน + จังหวัด + ยี่ห้อ + สี + เวลา (≤24 ชม.)          │
│   เลือก slot (optional)                                            │
│   ↓                                                                │
│ store() → validate → duplicate guard → overlap check               │
│   → reservations(status='pending', fee=hourly_rate, vehicle_id=NULL)│
│   → reservation_logs(new_status='pending', changed_by=me)          │
└───────────────────────────────────────────────────────────────────┘
   ↓
รอ Admin/Owner กด "ยืนยัน"   ← ⚠️ ไม่มี auto-confirm
   ↓ (confirmed + notification)
┌─ ถึงเวลาเช็คอิน [reserve_start−5นาที , reserve_start+30นาที] ───────┐
│ ทางที่ 1: AI Scan (user/owner/admin สแกนได้)                        │
│   → เทียบทะเบียน+จังหวัด (บังคับ) + (ยี่ห้อ หรือ สี อย่างน้อย 1)      │
│   → ผ่าน → auto check-in                                           │
│   → ⚠️ ไม่ผ่านถ้า reservation ขาด province/brand/color (78/84 รายการ)│
│ ทางที่ 2: Admin/Owner กดปุ่ม "เช็คอิน" ในหน้าการจอง                   │
└───────────────────────────────────────────────────────────────────┘
   ↓
parking_logs(check_in_time) + slot='occupied' + reservation.status='checked_in'
   ↓
[ถ้าไม่มาภายใน 30 นาที] → scheduler → status='expired' + คืน slot + notify
   ↓
┌─ เช็คเอาท์ (Admin/Owner กดให้) ───────────────────────────────────┐
│ คำนวณ: ชม. (ปัดขึ้น, ขั้นต่ำ 1) × hourly_rate = parking_fee          │
│         − min(reservation_fee, parking_fee)  = deposit             │
│         =                                      total_amount        │
│ → payments(unpaid) + slot='available' + status='completed'         │
│ → notify user พร้อมยอดเงิน                                          │
└───────────────────────────────────────────────────────────────────┘
   ↓
Admin/Owner กด "ชำระแล้ว" → payment_status='paid'
   ⚠️ ไม่มีการเก็บเงินมัดจำจริงที่จุดใดเลยตลอด flow นี้
   ↓
user ดูประวัติ: user.parking-logs.index
   ⚠️ INNER JOIN vehicles → ถ้า user ไม่มี Vehicle record จะไม่เห็นอะไรเลย
```

### 7.2 Owner (เจ้าของลาน)

```
เริ่มจาก role='user' → owner.application.create
   กรอก: ประเภท/ชื่อธุรกิจ/ผู้ติดต่อ/ชื่อลานจอด/ที่อยู่/จำนวนช่องโดยประมาณ/เอกสารแนบ
   ↓
owner_applications(status='pending') + users.owner_status='pending'
   (role ยังเป็น 'user') + notify admin ทุกคน
   ↓
   ├─ Admin ปฏิเสธ → owner_status='rejected' + เหตุผล
   │     → owner.application.edit → แก้ไข → ส่งใหม่ (กลับเป็น pending)
   └─ Admin อนุมัติ → role='owner' + owner_status='approved'
         ⚠️ ไม่มีการสร้าง ParkingLot จากข้อมูลที่กรอกไว้ตอนสมัคร
   ↓
owner.dashboard (ผ่าน owner middleware)
   ↓ (ต้อง owner_status='approved' จึงผ่าน owner.approved)
┌─ งานประจำวันของ Owner ──────────────────────────────────┐
│  1. สร้างลานจอด (owner.parking-lots.create)               │
│  2. สร้างช่องจอด — ทีละช่อง หรือ bulk (range/list)         │
│  3. เปิด/ปิดลาน (toggle) ⚠️ ปิดแล้วยังมีคนจองได้อยู่         │
│  4. ดูการจอง → กด "ยืนยัน" (pending→confirmed)            │
│  5. กด "เช็คอิน" เมื่อถึงเวลา / หรือให้ AI ทำอัตโนมัติ        │
│  6. กด "เช็คเอาท์" (มี popup ยืนยัน)                       │
│  7. Walk-in → เช็คเอาท์ผ่านหน้า "ประวัติการจอด"             │
│  8. หน้าชำระเงิน → กด "ชำระแล้ว"                          │
│  9. ดูรายได้ (today/month/year)                          │
│ 10. AI Scan + ดูประวัติการสแกนของลานตัวเอง                │
└─────────────────────────────────────────────────────────┘
   ⚠️ Owner ไม่มี audit log — action ทั้งหมดไม่ถูกบันทึก
   ⚠️ Owner เพิ่ม blacklist เองไม่ได้ (รับแต่ notification)
   ⚠️ Owner แก้ไข/ลบการจองไม่ได้ (ทำได้แค่ confirm / check-in / check-out)
   ↓
[ลาออก] owner.demote-self (ต้องระบุเหตุผล) → role='user', owner_status=NULL
   ⚠️ ลานจอดที่เคยเป็นเจ้าของยังมี owner_id ชี้มาที่ user คนนี้
      → ไม่กลายเป็น unowned → admin ก็เข้าไปจัดการไม่ได้ → ลานกำพร้า
```

### 7.3 Admin

```
login → admin.dashboard   (⚠️ route group นี้ไม่มี force.password.reset)
   ↓
ขอบเขต: จัดการได้เฉพาะลานที่ owner_id IS NULL
        → ปัจจุบัน 2 จาก 8 ลาน (lot#7, lot#8) = 25%
   ↓
┌─ งานของ Admin ────────────────────────────────────────────────────┐
│ [ลานจอด/ช่องจอด]  CRUD เฉพาะลาน unowned + bulk create               │
│ [การจอง]          ดู/ยืนยัน/เช็คอิน/เช็คเอาท์/แก้ไข/ลบ                 │
│                   ⚠️ "สร้างการจอง" มี route แต่ไม่มีปุ่ม + สร้างแล้วพัง  │
│ [ผู้ใช้]           CRUD + ตั้งรหัสชั่วคราว + force reset + ลบ           │
│                   ⚠️ เปลี่ยน role แล้ว owner_status ไม่ตาม             │
│ [รถ (Vehicles)]   CRUD ⚠️ บทบาทคลุมเครือ ไม่เชื่อมกับ flow การจอง      │
│ [ชำระเงิน]         ดู unpaid/paid + กด "ชำระแล้ว"                     │
│ [ประวัติการจอด]    ดู + เช็คเอาท์ walk-in                             │
│ [AI Scan]         สแกน + ประวัติ (เฉพาะลาน unowned)                  │
│ [บัญชีดำ]          CRUD + toggle (global ทั้งระบบ)                    │
│ [คำขอ Owner]      ดู/อนุมัติ/ปฏิเสธพร้อมเหตุผล                        │
│ [Reservation Log] ⚠️ ซ่อนข้อมูล 62% + CSV ขาดเหมือนกัน                │
│ [Admin Actions]   ⚠️ ค้นหา = 500 · CSV filter ต่างจากหน้าจอ            │
└───────────────────────────────────────────────────────────────────┘
   ⚠️ หน้าเหล่านี้เข้าได้จาก dashboard เท่านั้น (ไม่อยู่ใน navbar):
      users · parking-lots · reservation-logs · admin-actions · owner-applications
```

---

## 8. สรุปแยกตามหมวด

### 8.1 ✅ สิ่งที่ระบบทำได้จริง

| Feature | หลักฐาน |
|---|---|
| Authentication ครบวงจร + rate limit + force password reset | test ผ่าน 6 ไฟล์, 32 users ใน DB |
| Role-based access 3 ระดับ + ขอบเขตตาม lot ownership | middleware 5 ตัว + guard ในทุก controller |
| User จอง/แก้/ยกเลิกการจอง (plate-based) | 10 reservations ยุคใหม่ใน DB |
| Parking Lot CRUD (admin + owner แยกขอบเขต) | 8 lots ใน DB |
| Parking Slot CRUD + bulk create 2 โหมด | 305 slots ใน DB |
| AI Car Scan ด้วย Claude Vision (ของจริง ไม่ใช่ mock) | 46 scans ใน DB, ใช้ SDK จริง |
| Auto check-in (walk-in) | มี log ที่ `vehicle_id=NULL` |
| Check-out + คำนวณค่าจอด + สร้าง payment + คืน slot | 44 payments, slot status ตรงกับ active logs |
| Blacklist CRUD + toggle + เชื่อม AI scan + แจ้ง owner/admin | 6 entries |
| Notification in-app ทุก event สำคัญ | 104 rows |
| Owner Application ครบวงจร (สมัคร/อนุมัติ/ปฏิเสธ/แก้/ลาออก) | 5 applications |
| Auto-expire reservation (scheduler) | test ผ่าน |
| Admin audit log (ส่วนการบันทึก) | 66 rows + index ครบ |
| CSV export (admin actions + reservation logs) | streamDownload + UTF-8 BOM |
| Dashboard charts (Chart.js) | test `DashboardChartDataTest` ผ่าน |
| ป้องกัน ID enumeration ใน admin routes | exception handler |
| Payment mark-paid + guard สิทธิ์ + กันกดซ้ำ | 44 payments |

### 8.2 🟡 สิ่งที่ทำได้บางส่วน `[PARTIAL]`

| Feature | ทำได้ | ขาด/พัง |
|---|---|---|
| Dashboard ทั้ง 3 role | stat card ถูกต้อง | ตารางใช้ INNER JOIN vehicles → ข้อมูลหาย |
| User parking log | หน้าแสดงผลได้ | INNER JOIN → user ที่ไม่มี Vehicle เห็นเปล่า (หาย 1/57 แล้ว) |
| Reservation Logs | แสดง 17 แถว | ซ่อน 28/45 (62%) — auto event หายหมด |
| Admin Actions Log | บันทึกครบ, แสดงได้ | ค้นหา = 500 · CSV filter ต่างจากหน้าจอ |
| Auto check-in (มีการจอง) | ทำงานกับ booking ใหม่ | 78/84 reservation ไม่มี province/brand/color → ผ่านไม่ได้ |
| Payment | คำนวณ+แสดง+mark paid ถูกต้อง | มัดจำไม่เคยถูกเก็บจริง (฿680 หาย + ฿1,095 ค้าง) |
| Owner Revenue | 8 metrics ครบ | ตัวเลขไม่รวมมัดจำ · occupancy ไม่ตาม filter · ไม่มี week |
| Owner Application | flow ครบ | ข้อมูลที่กรอกไม่ถูกใช้สร้างลานจอด |
| Error handling | มี handler สำคัญ | ไม่มี custom error page · ไม่จับ API network error |
| Tests | 119 ผ่าน | 7 พัง (booking ทั้งหมด) + factory/seeder เป็น schema เก่า |
| Login redirect | ผลลัพธ์ถูก | owner ต้องเด้ง 2 ต่อ |
| page_titles | 36 entries | ครอบคลุมแค่ 36/65 GET routes |

### 8.3 🎭 สิ่งที่เป็น Mock / Demo / จำลอง

| สิ่งที่จำลอง | รายละเอียด | ตั้งใจ? |
|---|---|---|
| **Payment gateway** | ไม่มีจริง — เป็นการกดยืนยันรับเงินสด | ✅ ตั้งใจ (README ระบุชัด) |
| **ตำแหน่งกล้อง** | ผู้ใช้เลือก `parking_lot_id` เอง ("จำลองว่ากล้องติดที่ลานนี้") | ✅ ตั้งใจ (comment ใน migration) |
| **แหล่งที่มาของ scan** | `source` มีค่าเดียว `manual_upload` — ไม่มีกล้องจริงส่งข้อมูลเข้ามา | ✅ ตั้งใจ (เตรียมไว้อนาคต) |
| **ระยะเวลาจอง** | สมมติ 1 ชั่วโมงตายตัว (`INTERVAL '1 hour'` hardcode) | ⚠️ น่าจะเป็นการลัด |
| **ค่ามัดจำ** | มี column + แสดงผล + หักลด แต่ **ไม่มีการเก็บเงินจริง** | ❌ ไม่น่าตั้งใจ |

**✅ ยืนยัน: ไม่พบ mock/fake/stub ใน production code** — ไม่มี hardcoded fake response, ไม่มี `dd()`/`dump()`, ไม่มี TODO/FIXME (grep ทั่ว `app/`, `routes/`, `database/`, `resources/views/`)
**AI ไม่ใช่ mock** — เรียก Claude Vision API จริงผ่าน `anthropic-ai/sdk`

### 8.4 📦 สิ่งที่พบใน code แต่ไม่ได้ถูกใช้งาน `[UNUSED]`

| # | รายการ | ประเภท | หลักฐาน |
|---|---|---|---|
| 1 | `App\Models\Role` | Model | ไม่มี table ไม่มี migration **ไม่มี code อ้างอิงเลย** |
| 2 | `App\Models\Permission` | Model | เหมือนกัน |
| 3 | `User\VehicleController` | Controller | import ใน routes แต่ **ไม่มี route** |
| 4 | `user/vehicles/index.blade.php` | View | ไม่มี route ชี้มา + อ้าง route ที่ไม่มี |
| 5 | `user/vehicles/create.blade.php` | View | เหมือนกัน |
| 6 | Email Verification (6 ชิ้น) | Feature | middleware `verified` เป็น no-op — **ยืนยันแล้ว** |
| 7 | `marketplace.index` | Route+View+Controller | **ไม่มีลิงก์จากที่ใดเลย** — ยืนยันด้วย grep |
| 8 | `admin.reservations.create` / `.store` | Route | ไม่มีปุ่มใน UI |
| 9 | `.github/workflows/tests.yml` | CI | trigger `master` แต่ repo ใช้ `main` |
| 10 | `.github/workflows/{issues,pull-requests,update-changelog}.yml` | CI | skeleton ดั้งเดิม |
| 11 | `AppServiceProvider` | Provider | `register()` + `boot()` ว่างทั้งคู่ |
| 12 | `RoleMiddleware` แบบหลาย role | Middleware | รองรับ `role:a,b` แต่ใช้จริงแค่ `role:user` |
| 13 | `license_plate_scans.source` | Column | มีค่าเดียวทั้งระบบ |
| 14 | `users.email_verified_at` | Column | ระบบไม่ตรวจสอบ |
| 15 | `parking_lots.location` | Column | ซ้ำซ้อนกับ `address` |
| 16 | `Vehicle::reservations()` relation | Relation | flow ใหม่ไม่ set `vehicle_id` |
| 17 | duplicate guard `whereHas('vehicle')` | Business logic | dead — `vehicle_id` เป็น null เสมอ |
| 18 | `e2e/screenshots/.../admin_devices_*.png` | Asset | **ไม่เคยมี code ของ "devices" ในระบบ** |
| 19 | `e2e/screenshots/.../user_vehicles_*.png` | Asset | feature ที่ถูกถอดแล้ว |
| 20 | `CHANGELOG.md` | Doc | ของ Laravel skeleton ไม่ใช่ของโปรเจกต์ |
| 21 | `config/page_titles.php` (29 routes ที่ขาด) | Config | ครอบคลุมไม่ครบ |
| 22 | `storage.local` / `storage.local.upload` | Route | auto-register **ไม่มี middleware** |

### 8.5 ⚔️ สิ่งที่ขัดแย้งกัน `[CONFLICT]`

| # | ความขัดแย้ง | รายละเอียด | ระดับ |
|---|---|---|---|
| 1 | **2 โมเดลข้อมูลรถอยู่ร่วมกัน** | Vehicle-based (เก่า) vs plate-based (ใหม่) — DB มีข้อมูลทั้ง 2 ยุค, code อ่านแบบเก่า แต่เขียนแบบใหม่ | 🔴 |
| 2 | **Admin create reservation vs check-in** | `store()` ไม่ set `license_plate` → `checkIn()` ต้องการ string → `TypeError` 500 (ยืนยันแล้ว) | 🔴 |
| 3 | **Dashboard: ตัวเลข vs ตาราง** | stat card นับตรง, ตาราง INNER JOIN → ตัวเลขไม่ตรงกับรายการที่แสดง | 🔴 |
| 4 | **`is_active` vs `reservations_enabled`** | Marketplace กรอง `is_active`, ฟอร์มจองกรอง `reservations_enabled` → ปิดลานแล้วยังจองได้ | 🔴 |
| 5 | **`UserController::store` vs `update`** | `store` set `owner_status`, `update` ไม่ set → เปลี่ยน role ผ่าน edit ได้ owner ที่ใช้งานไม่ได้ | 🔴 |
| 6 | **`Owner::destroy` vs `Admin\UserController::destroy`** | ตัวหลังลบ `ParkingLog` ก่อน ตัวแรกไม่ลบ → owner ลบลาน = FK error 500 | 🔴 |
| 7 | **`matchScanAgainstReservation` vs ข้อมูลจริง** | code สมมติว่าทุก reservation มี province/brand/color แต่ 78/84 ไม่มี | 🔴 |
| 8 | **Test/Factory/Seeder vs Controller** | ทั้ง 3 ยังเป็น schema เก่า → test ที่ผ่านให้ false confidence | 🔴 |
| 9 | **`force.password.reset` ติดไม่ครบ** | user/owner ถูกบังคับ แต่ admin ไม่ถูกบังคับ | 🟡 |
| 10 | **Admin lot: `create` set owner_id vs `index` แสดง unowned** | สร้างลานพร้อม owner → หายจากหน้าจอทันที | 🟡 |
| 11 | **รูปแบบ `license_plate` ไม่ตรงกัน** | scans = เลขอย่างเดียว · ตารางอื่น = เลข+จังหวัด | 🟡 |
| 12 | **CHECK constraint ใช้ไม่สม่ำเสมอ** | `users.role` ✓ `parking_slots.status` ✓ แต่ `reservations.status` ✗ `payments.payment_status` ✗ | 🟡 |
| 13 | **AdminAction `index` vs `export` filter ต่างกัน** | วงเล็บ `orWhere` ไม่เหมือนกัน → CSV ≠ หน้าจอ | 🟡 |
| 14 | **README vs code** | README อ้าง "Manual Check-In/Out" เป็นเมนู admin แต่ถูกยุบรวมไปหน้าการจองแล้ว | 🟢 |
| 15 | **Tailwind 3 + `@tailwindcss/vite` v4** | version ปนกันใน `package.json` | 🟢 |
| 16 | **`timestamps(0)` vs `timestamps()`** | `owner_applications` ต่างจากทุกตาราง | 🟢 |

### 8.6 🚧 สิ่งที่ยังไม่เสร็จ `[PARTIAL]` / `[PLANNED]`

| # | รายการ | สถานะปัจจุบัน |
|---|---|---|
| 1 | การย้ายจาก Vehicle-based → plate-based | **ทำไปประมาณ 60%** — controller ใหม่เสร็จ แต่ dashboard/log/test/factory/seeder/admin-create ยังไม่ตาม |
| 2 | ระบบเก็บเงินมัดจำ | มี column + แสดงผล + หักลด **แต่ไม่มีขั้นตอนเก็บเงิน** |
| 3 | Role/Permission แบบ table-based | มีแต่ model 2 ตัว |
| 4 | กล้องอัตโนมัติส่งข้อมูล | มี `source` column + `parking_lot_id` เตรียมไว้ |
| 5 | สร้าง ParkingLot อัตโนมัติจาก owner application | เก็บข้อมูลไว้แต่ไม่ใช้ |
| 6 | Email verification | โครงครบ ปิดอยู่ที่ model |
| 7 | Marketplace → จอง | หน้ามีแต่ไม่มีปุ่มจอง + ไม่มีลิงก์เข้า |
| 8 | Owner audit log | ไม่มีเลย |
| 9 | ระยะเวลาจองแบบยืดหยุ่น | hardcode 1 ชม. |
| 10 | Custom error pages | ไม่มี |
| 11 | page_titles ครบทุกหน้า | 36/65 |
| 12 | CI ที่ใช้งานได้ | skeleton |
| 13 | Test ของ Owner/Marketplace/Notification/Payment | ไม่มี |

### 8.7 🐛 สิ่งที่มีปัญหา (Known Bugs — เรียงตามความรุนแรง)

| # | Bug | ไฟล์ | สถานะยืนยัน |
|---|---|---|---|
| 1 | ช่องค้นหา Admin Actions = 500 | `Admin/AdminActionController.php:33` | ✅ **ยืนยันแล้ว** SQLSTATE 22P02 |
| 2 | Admin-created reservation check-in = TypeError 500 | `Admin/ReservationController.php:148` + `Services/CheckInService.php:33` | ✅ **ยืนยันแล้ว** |
| 3 | Reservation Logs ซ่อนข้อมูล 62% | `Admin/ReservationLogController.php:21-22, 76-77` | ✅ **ยืนยันแล้ว** 17/45 |
| 4 | มัดจำไม่เคยถูกเก็บ (฿680 หาย, ฿1,095 ค้าง) | `Services/CheckOutService.php:38` | ✅ **ยืนยันแล้ว** |
| 5 | Dashboard/parking-log INNER JOIN vehicles ทำข้อมูลหาย | `DashboardController` (9 จุด), `Owner/DashboardController` (2), `User/ParkingLogController.php:15` | ✅ **ยืนยันแล้ว** 56/57 |
| 6 | Test ของการจองพัง 7 ตัว | `tests/Feature/Reservation*.php` | ✅ **ยืนยันแล้ว** |
| 7 | Seeder สร้าง `parking_logs` ไม่มี `license_plate` | `DatabaseSeeder.php:448,490,659,689` | ✅ **ยืนยันแล้ว** 0/4 จุด |
| 8 | Seeder ไม่ใส่ province/brand/color → AI check-in ใช้ไม่ได้ | `DatabaseSeeder.php` | ✅ **ยืนยันแล้ว** 78/84 NULL |
| 9 | `verified` middleware เป็น no-op | `app/Models/User.php:5` | ✅ **ยืนยันแล้ว** |
| 10 | Owner ลบลานที่มีประวัติ = FK error 500 | `Owner/ParkingLotController.php:112-118` | 📖 code-read (FK RESTRICT) |
| 11 | เปลี่ยน role ผ่าน admin edit ไม่ set `owner_status` | `Admin/UserController.php:88` | 📖 code-read |
| 12 | ปิดลาน (`is_active=false`) แล้วยังจองได้ | `Models/ParkingLot.php:39` | 📖 code-read |
| 13 | User ลบบัญชีตัวเองที่มี parking log = FK error | `ProfileController.php:46` | 📖 code-read |
| 14 | Owner ลาออกแล้วลานกลายเป็นกำพร้า | `Owner/ApplicationController.php:158` | 📖 code-read |
| 15 | duplicate guard ตอนจองเป็น dead code | `User/ReservationController.php:85` | 📖 code-read |
| 16 | AdminAction CSV filter ≠ หน้าจอ | `Admin/AdminActionController.php:80-88` | 📖 code-read |
| 17 | Admin dashboard `latestScans` ไม่กรอง lot ownership | `DashboardController.php:246` | 📖 code-read |
| 18 | Admin dashboard ค้นทะเบียนจาก `vehicles` ไม่ใช่ `parking_logs` | `DashboardController.php` | 📖 code-read |
| 19 | User dashboard นับ slot ทั้งระบบ (รวมลานคนอื่น) | `DashboardController.php:27` | 📖 code-read |
| 20 | `lotsAvailable` แนะนำลานที่ปิดอยู่ | `DashboardController.php:110` | 📖 code-read |
| 21 | slot status ตั้งเป็น `occupied` เองได้โดยไม่มี log | `{Admin,Owner}/ParkingSlotController` | 📖 code-read |
| 22 | ลบ slot ที่กำลัง occupied ได้ | `{Admin,Owner}/ParkingSlotController::destroy` | 📖 code-read |
| 23 | `CheckOutService` ไม่เช็ค `parkingLot` null | `Services/CheckOutService.php:36` | 📖 code-read |
| 24 | `SuspiciousVehicleController::index` เขียน audit ทุกครั้งที่เปิดหน้า | `Admin/SuspiciousVehicleController.php:27` | 📖 code-read |
| 25 | dead code `$userId` ซ้ำ | `DashboardController.php:14,24` | 📖 code-read |

---

## 9. Security Findings

| # | ประเด็น | ระดับ | รายละเอียด |
|---|---|---|---|
| 1 | **SSL verification ปิดถาวร** | 🔴 สูง | `CarScanService.php:23` — `new GuzzleClient(['verify' => false])` hardcode ไม่ผูกกับ `APP_ENV` → MITM ได้ทุก environment |
| 2 | **`DB_PASSWORD` plaintext ใน repo** | 🔴 สูง | `phpunit.xml:27` — `<env name="DB_PASSWORD" value="***"/>` ถูก commit เข้า git |
| 3 | **เอกสารสมัคร owner เป็น public** | 🔴 สูง | `ApplicationController` เก็บลง disk `public` (`visibility: public`) → เอกสารยืนยันตัวตน/ธุรกิจ ดาวน์โหลดได้โดยไม่ต้อง login (ป้องกันด้วย random filename เท่านั้น = security by obscurity) |
| 4 | **User check-in เข้าลานคนอื่นได้** | 🔴 สูง | `CarScanController::authorizedLots()` role `user` = `reservable()` = ทุกลานในระบบ (ยืนยัน 8/8) → ยึดช่องจอดในลานของ owner ได้ |
| 5 | **AI check-in ข้าม lot scope** | 🔴 สูง | `CarScanController.php:139` ส่ง `allowedLotIds = null` + `findMatchingReservation()` ไม่ผูกกับผู้สแกน → ใครก็ได้ทำให้รถของคนอื่น check-in ที่ลานไหนก็ได้ |
| 6 | **Email verification ไม่ทำงาน** | 🔴 สูง | สมัครด้วยอีเมลของคนอื่นได้ทันที |
| 7 | **รูปสแกนเป็น public** | 🟡 กลาง | `car-scans` บน public disk — รูปป้ายทะเบียนเข้าถึงได้โดยไม่ต้อง auth |
| 8 | **`e2e/.auth/*.json` ถูก commit** | 🟡 กลาง | session token ของ admin/owner/user อยู่ใน repo |
| 9 | **`storage/{path}` route ไม่มี middleware** | 🟡 กลาง | auto-register โดย Laravel 12 — ควรตรวจว่าเข้าถึงอะไรได้บ้าง |
| 10 | **`APP_DEBUG=true`** | 🟡 กลาง | เหมาะกับ dev — ต้องปิดก่อน production (stack trace รั่ว) |
| 11 | **ไม่มี rate limit บน AI scan** | 🟡 กลาง | ทุก role ยิง Claude API ได้ไม่จำกัด → ค่าใช้จ่ายบานปลาย |
| 12 | **Marketplace เปิดเผยชื่อเจ้าของลาน** | 🟡 กลาง | `u.name` แสดงต่อสาธารณะโดยไม่ได้ขออนุญาต |
| 13 | **`force.password.reset` ไม่ครอบ admin** | 🟡 กลาง | admin ที่โดน force reset ยังใช้หน้า admin ได้ |
| 14 | **ไม่มี Policy/Gate** | 🟢 ต่ำ | ใช้ `abort_if` กระจายทุก controller → พลาดที่เดียวคือรั่ว |
| 15 | **ไม่มี soft delete** | 🟢 ต่ำ | ลบแล้วกู้ไม่ได้ |

**✅ จุดแข็งด้าน security ที่พบ:**
- CSRF protection ทุกฟอร์ม (Laravel default)
- SQL injection: ใช้ parameter binding ทุกที่ (รวม `whereRaw` ที่ใช้ `?` placeholder ถูกต้อง)
- XSS: Blade `{{ }}` escape อัตโนมัติ
- ป้องกัน ID enumeration ใน admin routes (exception handler)
- Login rate limit 5 ครั้ง
- Password hashing bcrypt 12 rounds
- Ownership guard ครบทุก resource ที่ตรวจสอบ (`abort_unless($x->user_id === Auth::id(), 403)`)
- Admin เปลี่ยน role ตัวเองออกจาก admin ไม่ได้ / ลบบัญชีตัวเองไม่ได้
- File upload validate `mimes` + `max:5120` + ใช้ `store()` (random filename)

---

## 10. Technical Debt

| # | รายการ | ผลกระทบ |
|---|---|---|
| 1 | **การย้าย Vehicle→plate ค้างครึ่งทาง** | **หนี้ก้อนใหญ่ที่สุด** — เป็นต้นตอของบั๊ก #2–#8 |
| 2 | **ไม่มี Policy/Gate** | authorization logic ซ้ำ 40+ จุด |
| 3 | **Status เป็น string literal กระจาย** | `'pending'`, `'confirmed'`, … ปรากฏหลายสิบจุด ไม่มี Enum |
| 4 | **Controller ปนกับ business logic** | `Admin\ReservationController` 419 บรรทัด, `DashboardController` 398 บรรทัด |
| 5 | **Query Builder ปนกับ Eloquent** | dashboard/log ใช้ `DB::table()` raw ส่วน controller อื่นใช้ Eloquent |
| 6 | **Admin/Owner controller ซ้ำกันเกือบทั้งหมด** | ParkingLot, ParkingSlot, Reservation, Payment, ParkingLog — logic เดียวกัน ต่างแค่ scope |
| 7 | **`protected $guarded = []` ทุก model** | mass assignment เปิดหมด |
| 8 | **ขาด index เกือบทั้ง DB** | ช้าเมื่อข้อมูลโต |
| 9 | **PostgreSQL lock-in** | ย้าย DB ไม่ได้โดยไม่แก้ code |
| 10 | **Test/Factory/Seeder ล้าสมัย** | false confidence |
| 11 | **ไม่มี API layer** | ต่อ mobile app ไม่ได้ |
| 12 | **N+1 หลายจุด** | `CheckOutService` (`$log->parkingLot`), `PaymentController::markPaid` |
| 13 | **ไม่มี caching** | dashboard ยิง 10+ query ทุกครั้ง |
| 14 | **Global helper functions** | `admin_audit()`, `notify_user()` — test/mock ยาก |
| 15 | **ไฟล์อัปโหลดไม่มีวันหมดอายุ** | สะสมไม่จำกัด |
| 16 | **`e2e/reports` + screenshot ถูก commit** | repo บวม ข้อมูลล้าสมัย |

---

## 11. สิ่งที่ยืนยันไม่ได้จาก source code

`[UNKNOWN]` — ต้องทดสอบ runtime หรือถามเจ้าของโปรเจกต์

| # | ประเด็น | เหตุผล |
|---|---|---|
| 1 | **ความแม่นยำจริงของ Claude Vision กับป้ายทะเบียนไทย** | ต้องทดสอบกับรูปจริงหลากหลาย — code เก็บ `confidence` แต่ **ไม่มีที่ไหนใช้ค่านี้ตัดสินใจเลย** |
| 2 | **`CARSCAN_MODEL=claude-opus-4-8` ยังใช้ได้หรือไม่** | ต้องเรียก API จริงจึงจะรู้ว่า model id ยัง valid |
| 3 | **ต้นทุนต่อการสแกน 1 ครั้ง** | ขึ้นกับ model + ขนาดรูป |
| 4 | **scheduler ถูกรันจริงหรือไม่ใน environment ปัจจุบัน** | ต้องดูว่ามี `schedule:work` / cron รันอยู่ |
| 5 | **queue worker ถูกรันหรือไม่** | `QUEUE_CONNECTION=database` แต่ **ไม่พบการ dispatch job ที่ไหนเลย** — น่าจะไม่จำเป็น |
| 6 | **SMTP ใช้งานได้จริงหรือไม่** | ตั้ง Gmail + app password ไว้ ต้องทดสอบส่งจริง |
| 7 | **`storage/{path}` route เข้าถึงอะไรได้บ้าง** | เป็น auto-register ของ Laravel 12 ไม่มี middleware |
| 8 | **ผลการรัน E2E ปัจจุบัน** | report ที่มีเป็นของเก่า (อ้าง feature ที่ถูกถอดแล้ว) ต้องรันใหม่ |
| 9 | **พฤติกรรมบน browser จริง (Safari/mobile)** | commit ล่าสุด 3 ตัวเป็นการแก้ CSS เฉพาะ Safari/macOS → บ่งชี้ว่ามีปัญหา cross-browser ที่ตรวจจาก code ไม่ได้ |
| 10 | **ข้อมูล production จริง** | ตรวจจาก DB dev เท่านั้น (32 users / 8 lots) |
| 11 | **ประสิทธิภาพเมื่อข้อมูลโต** | ปัจจุบันข้อมูลน้อยมาก + ขาด index |
| 12 | **เจตนาที่แท้จริงของ `total_slots`** | เป็นแค่ metadata หรือควรบังคับให้ตรงกับจำนวน slot จริง |
| 13 | **เจตนาเรื่องมัดจำ** | ตั้งใจให้เป็นส่วนลด หรือควรเก็บเงินจริง — **ต้องถามเจ้าของโปรเจกต์** |
| 14 | **ขอบเขต admin ที่ต้องการ** | ตั้งใจให้ admin เห็นแค่ลาน unowned จริงหรือ (ปัจจุบันเห็นแค่ 25%) |

---

## 12. จุดที่ต้องตัดสินใจก่อนกำหนด Final Scope

> เรียงตามลำดับความสำคัญ — **ข้อ 1 ต้องตอบก่อน เพราะคำตอบกำหนดข้ออื่นเกือบทั้งหมด**

### 🔴 ตัดสินใจที่ 1 — โมเดลข้อมูลรถ: จะเอา `Vehicle` ไว้หรือตัดทิ้ง?

**นี่คือรากของปัญหาประมาณ 60% ในรายงานนี้**

| ทางเลือก | สิ่งที่ต้องทำ | ผลกระทบ |
|---|---|---|
| **A. ตัด Vehicle ทิ้งทั้งหมด** | แก้ INNER JOIN 11 จุด · ลบ `User\VehicleController` + 2 views · ตัดสินใจเรื่อง `admin.vehicles.*` · แก้ factory/seeder/test · แก้หรือถอด `admin.reservations.create` | สะอาดที่สุด สอดคล้องกับทิศทางที่เลือกไว้แล้ว |
| **B. เอา Vehicle กลับมาเป็นหลัก** | คืน route `user.vehicles.*` · บังคับเลือกรถตอนจอง · ถอย migration 2026-07-21 | ขัดกับงานที่ทำไป 3 เดือน |
| **C. อยู่ร่วมกัน (สถานะปัจจุบัน)** | ต้องแก้ทุก INNER JOIN เป็น LEFT JOIN + ใช้ `COALESCE(pl.license_plate, v.license_plate)` | ซับซ้อนถาวร |

**ข้อสังเกตจากหลักฐาน:** ทิศทางของ code ตั้งแต่ 2026-07-17 ชี้ไปที่ **A** ชัดเจน (comment ใน migration เขียนเหตุผลไว้ละเอียด) — แต่งานยังไม่จบ

---

### 🔴 ตัดสินใจที่ 2 — "ค่ามัดจำ" คืออะไรกันแน่?

ปัจจุบัน: ตั้งค่า = `hourly_rate` ตอนจอง → **ไม่เคยเก็บเงิน** → หักเป็นส่วนลดตอน check-out

| ทางเลือก | ผลที่ตามมา |
|---|---|
| **A. เก็บเงินมัดจำจริง** | ต้องเพิ่ม payment record ตอน confirm + นโยบายคืนเงินเมื่อ cancel/expire + แก้หน้า revenue |
| **B. เป็นส่วนลดจริงๆ** | ต้องเปลี่ยนคำเรียกจาก "มัดจำ" เป็น "ส่วนลดการจองล่วงหน้า" ทั้งระบบ |
| **C. ยกเลิกไปเลย** | ตั้ง `reservation_fee = 0` เสมอ — ง่ายที่สุด |

**ข้อมูลประกอบ:** ปัจจุบันมีเงิน "หาย" ฿680 + ค้างในสถานะ expired/cancelled ฿1,095

---

### 🔴 ตัดสินใจที่ 3 — ขอบเขตของ Admin

ปัจจุบัน admin เห็นและจัดการได้เฉพาะลาน `owner_id IS NULL` = **2 จาก 8 ลาน (25%)**

- **A.** คงไว้ — admin เป็นแค่ "เจ้าของลานสาธารณะ" อีกคนหนึ่ง
- **B.** admin เป็น super-admin เห็นทุกลาน (แต่แก้ไขเฉพาะ unowned)
- **C.** admin เห็นและจัดการทุกอย่างได้

> ปัจจุบันมีความไม่สอดคล้องอยู่แล้ว: `latestScans` และ blacklist เป็น global ขณะที่ทุกอย่างอื่นถูกจำกัด

---

### 🟡 ตัดสินใจที่ 4 — Marketplace จะเอาไหม?

หน้าสมบูรณ์ ทำงานได้ **แต่ไม่มีลิงก์เข้าถึงเลย** และไม่มีปุ่มจอง
→ **A.** เพิ่มลิงก์ใน navbar/welcome + ปุ่มจอง · **B.** ถอดออกจาก scope · **C.** ปล่อยไว้แบบ hidden

### 🟡 ตัดสินใจที่ 5 — Email Verification จะเปิดไหม?

เปิดแค่ uncomment 1 บรรทัดใน `User.php` — **แต่ต้องแน่ใจว่า SMTP ใช้ได้จริง** ไม่งั้น user ใหม่ทุกคนจะล็อกตัวเองออกจากระบบ
→ **A.** เปิด (ต้องทดสอบ SMTP + จัดการ user เดิม 32 คน) · **B.** ถอด route/view/middleware `verified` ทิ้งให้หมด

### 🟡 ตัดสินใจที่ 6 — `admin.reservations.create` จะเอาไหม?

route เปิดอยู่ ไม่มีปุ่ม UI และถ้าใช้จะสร้าง data ที่ check-in ไม่ได้
→ **A.** แก้ให้รับ plate/province/brand/color แบบเดียวกับ user + เพิ่มปุ่ม · **B.** ถอดทั้ง route + controller method + view

### 🟡 ตัดสินใจที่ 7 — Owner ต้องมี Audit Log ไหม?

ตอนนี้ owner ทำ action สำคัญได้ (confirm / check-in / check-out / mark paid / ลบลาน) **โดยไม่ถูกบันทึกเลย** ขณะที่ admin ถูกบันทึกทุกอย่าง

### 🟡 ตัดสินใจที่ 8 — ระยะเวลาจอง

hardcode 1 ชั่วโมง → ต้องการ `reserve_end` ที่ผู้ใช้กำหนดเองไหม? (กระทบ overlap check + การคิดเงิน)

### 🟢 ตัดสินใจที่ 9 — `Role` / `Permission` model

ลบทิ้ง หรือเก็บไว้เพราะวางแผนจะทำ RBAC จริง?

### 🟢 ตัดสินใจที่ 10 — Test strategy

7 test พัง + factory/seeder ล้าสมัย → แก้ให้ตรงกับ flow ใหม่ หรือเขียนใหม่ทั้งชุด?

---

## 13. สิ่งที่ควรตรวจสอบเพิ่มเติม (แนะนำ)

| # | รายการ | วิธี / สิ่งที่คาดว่าจะเห็น |
|---|---|---|
| 1 | รัน `php artisan migrate:fresh --seed` ในฐานทดสอบ แล้วเปิดหน้า parking-logs | จะเห็นทะเบียนว่างทั้งหมด (ยืนยันบั๊ก seeder) |
| 2 | ทดสอบ AI scan กับรูปรถไทยจริง 20–30 รูป | วัดความแม่นยำจริง + ดูว่า `confidence` ควรถูกใช้ตัดสินใจไหม |
| 3 | ลองสร้างการจองผ่าน `/admin/reservations/create` แล้วกด check-in | ยืนยัน TypeError 500 บน UI จริง |
| 4 | ลองค้นหาอะไรก็ได้ในหน้า Admin Actions Log | ยืนยัน 500 บน UI จริง |
| 5 | ลองให้ owner ลบลานที่มีประวัติการจอด | ยืนยัน FK error |
| 6 | เปลี่ยน role user→owner ผ่านหน้า admin edit แล้ว login ด้วย user นั้น | ยืนยันว่าใช้งานอะไรไม่ได้ |
| 7 | รัน E2E ใหม่ทั้งชุด | report ปัจจุบันล้าสมัย |
| 8 | ตรวจว่ามี `schedule:work` รันอยู่จริงหรือไม่ | ถ้าไม่มี reservation ไม่หมดอายุ |
| 9 | ทดสอบส่งอีเมล reset password จริง | ก่อนตัดสินใจเรื่อง email verification |
| 10 | ตรวจ `storage/{path}` route ว่าเข้าถึงไฟล์อะไรได้บ้าง | ไม่มี middleware |
| 11 | ทดสอบ concurrent check-in ทะเบียนเดียวกัน 2 request พร้อมกัน | ตรวจว่า `lockForUpdate` เพียงพอไหม |
| 12 | ตรวจ cross-browser บน Safari/iOS | commit 3 ตัวล่าสุดเป็นการแก้ Safari |

---

## ภาคผนวก A — คำสั่งที่ใช้ตรวจสอบ (ทำซ้ำได้)

```bash
# Route inventory
php artisan route:list --json

# ยืนยัน verified middleware เป็น no-op
php artisan tinker --execute='$u=new \App\Models\User(); var_dump($u instanceof \Illuminate\Contracts\Auth\MustVerifyEmail);'

# ยืนยัน TypeError ตอน check-in ด้วย license_plate = null
php artisan tinker --execute='app(\App\Services\CheckInService::class)->checkIn(null,"Honda","ขาว",1,null,null);'

# ยืนยันช่องค้นหา Admin Actions พัง
php artisan tinker --execute='DB::table("admin_actions as aa")->where("aa.subject_id","::text","like","%x%")->count();'

# วัดข้อมูลที่หายจาก INNER JOIN ในหน้า reservation logs
php artisan tinker --execute='
$b=fn()=>DB::table("reservation_logs as rl")
  ->join("reservations as r","r.id","=","rl.reservation_id")
  ->join("parking_lots as lot","lot.id","=","r.parking_lot_id")
  ->whereNull("lot.owner_id");
echo "total=".$b()->count()
    ." lost_changed_by=".(clone $b())->whereNull("rl.changed_by")->count()
    ." lost_vehicle=".(clone $b())->whereNull("r.vehicle_id")->count()."\n";'

# ตรวจการแยกยุคของข้อมูล
php artisan tinker --execute='
foreach(DB::select("select (vehicle_id is null) as veh_null, min(created_at) f, max(created_at) l, count(*) from reservations group by 1") as $x)
  printf("veh_null=%s n=%d %s..%s\n", var_export($x->veh_null,true), $x->count, $x->f, $x->l);'

# ตรวจบัญชีมัดจำ
php artisan tinker --execute='
printf("parking_fee=%s discount=%s total=%s\n",
  DB::table("payments")->sum("parking_fee"),
  DB::table("payments")->sum("reservation_discount"),
  DB::table("payments")->sum("total_amount"));
printf("expired/cancelled fee total=%s\n", DB::table("reservations")
  ->whereIn("status",["expired","cancelled"])->where("reservation_fee",">",0)->sum("reservation_fee"));'

# รัน test suite
php artisan test
```

## ภาคผนวก B — สถิติสรุป

```
Controllers ........ 37   (Admin 11 · Owner 8 · User 3 · Auth 9 · root 5 · base 1)
Models ............. 15   (2 ตัวไม่มี table: Role, Permission)
Services ............ 3   (CarScan · CheckIn · CheckOut)
Middleware .......... 5   custom (+1 builtin ที่เป็น no-op)
Console Commands .... 1
Migrations ......... 22
Routes ............ 127
Blade views ........ 85   (2 ไฟล์เป็น dead code)
Config files ....... 15   (4 ไฟล์เป็นของโปรเจกต์เอง)
Test files ......... 24   (7 test พัง)
Factories ........... 7   (ยังเป็น schema เก่า)
Seeders ............. 1   (ยังเป็น schema เก่า)

Test result ................ 119 passed · 7 failed · 324 assertions · 33.46s
Routes ที่เรียกแต่ไม่มีจริง ....... 4
Routes ที่มีแต่ไม่มีทางเข้า ........ 5
Feature [IMPLEMENTED] ...... 14
Feature [PARTIAL] ........... 6
Feature [UNUSED] ............ 22 รายการ
Feature [CONFLICT] .......... 16 จุด
Known bugs .................. 25 (9 ยืนยันด้วยการรันจริง)
Security findings ........... 15 (6 ระดับสูง)
```

---

**สิ้นสุดรายงาน** · ไม่มีการแก้ไข source code, database หรือ configuration ใดๆ ในการตรวจสอบครั้งนี้
