# Smart Parking System

ระบบจัดการที่จอดรถอัจฉริยะ พัฒนาด้วย **Laravel 11** + **Google Gemini Vision AI**

![Laravel](https://img.shields.io/badge/Laravel-11-red)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15+-blue)
![Gemini](https://img.shields.io/badge/AI-Gemini%202.5%20Flash-orange)
![License](https://img.shields.io/badge/License-MIT-green)

---

## Features

- **รถเข้า-ออก** — บันทึก check-in/out พร้อมคำนวณค่าจอดอัตโนมัติ (ปัดชั่วโมงขึ้น)
- **จองล่วงหน้า** — workflow จอง → อนุมัติ → ใช้งาน พร้อม auto-expire
- **AI สแกนรถ** — อัปโหลดรูปรถ → Gemini Vision อ่านทะเบียน + สี + ยี่ห้อ
- **Blacklist** — แจ้งเตือนอัตโนมัติเมื่อพบรถต้องสงสัย
- **ชำระเงิน** — ติดตามสถานะ ค้างชำระ/ชำระแล้ว พร้อมส่วนลดจากการจอง
- **Audit Log** — บันทึกทุก action ของ admin
- **Dashboard KPI** — รถในลาน, ช่องว่าง, รายได้วันนี้, การจองรออนุมัติ

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4, Laravel 11 |
| Frontend | Blade, Tailwind CSS, Alpine.js, Vite |
| Database | PostgreSQL 15+ |
| AI Vision | Google Gemini 2.5 Flash |
| Auth | Laravel Breeze |

---

## Requirements

- PHP 8.4+
- PostgreSQL 15+
- Node.js 18+
- Composer
- Google Gemini API Key — ฟรีที่ [aistudio.google.com/apikey](https://aistudio.google.com/apikey)

---

## Installation

```bash
# 1. Clone
git clone https://github.com/tpp72/smart-parking-system.git
cd smart-parking-system

# 2. Install dependencies
composer install
npm install

# 3. Environment
cp .env.example .env
php artisan key:generate
```

แก้ไข `.env`:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=smart-parking-system
DB_USERNAME=postgres
DB_PASSWORD=your_password

# AI Car Scan (required)
GEMINI_API_KEY=AIzaSy...
CARSCAN_MODEL=gemini-2.5-flash
```

```bash
# 4. Migrate & build
php artisan migrate
php artisan config:cache
php artisan storage:link
npm run build

# 5. Run
php artisan serve

# Scheduler — สำหรับ auto-expire reservation (แยก terminal)
php artisan schedule:work
```

---

## Roles

| Role | สิทธิ์ |
|------|--------|
| **admin** | จัดการทุกอย่าง: รถเข้า-ออก, การจอง, ชำระเงิน, ผู้ใช้, ลานจอด, AI สแกน |
| **user** | จองที่จอด, ดูประวัติ, จัดการรถของตัวเอง, AI สแกนรถ |

---

## AI Car Scan

อัปโหลดรูปรถ → **Google Gemini Vision API** วิเคราะห์ → ได้ผล:

| Field | ตัวอย่าง |
|-------|---------|
| `license_plate` | `5กก 6285` |
| `color` | `เงิน` |
| `brand` | `Honda` |
| `confidence` | `95` |

Gemini models ที่ใช้ได้ (free tier):

| Model | Free Quota | หมายเหตุ |
|-------|-----------|---------|
| `gemini-2.5-flash` | 1,500 req/วัน | แนะนำ |
| `gemini-2.5-flash-lite` | 1,500 req/วัน | เร็วสุด |
| `gemini-2.5-pro` | น้อยกว่า | แม่นสุด |

---

## Project Structure

```
app/
├── Http/Controllers/
│   ├── Admin/               # Check-in, Check-out, Payment, ฯลฯ
│   ├── User/                # Reservation, Vehicle, Parking logs
│   └── CarScanController.php
├── Services/
│   └── CarScanService.php   # Gemini Vision API logic
└── Models/                  # 15 Eloquent models

config/
└── carscan.php              # Gemini API key + model

docs/
├── project-documentation.md
└── ai-scan-documentation.md
```

---

## Documentation

- [เอกสารโปรเจคฉบับสมบูรณ์](docs/project-documentation.md)
- [เอกสาร AI Car Scan](docs/ai-scan-documentation.md)

---

## Developer

Developed by [tpp72](https://github.com/tpp72)

## License

MIT License
