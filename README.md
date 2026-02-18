# 🚗 Smart Parking System

A Smart Parking Management System built with **Laravel 12** and **PostgreSQL**.  
This system provides parking lot management, slot reservations, vehicle tracking, payments, and suspicious vehicle monitoring.

---

![Laravel](https://img.shields.io/badge/Laravel-12-red)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Database-blue)
![License](https://img.shields.io/badge/License-MIT-green)

---

## 📌 Project Overview

Smart Parking System is a web-based application designed to manage parking lots efficiently.  
It allows users to reserve parking slots, track vehicle entries/exits, process payments, and monitor suspicious vehicles.

The system is built following the MVC architecture using Laravel Framework.

---

## ✨ Features

### 👤 User Management

- User registration & authentication
- Role-based access (`user`, `admin`)
- Notification system

### 🅿️ Parking Management

- Manage parking lots
- Manage parking slots
- Dynamic hourly rates
- Entry/Exit device tracking

### 🚘 Vehicle Management

- Register vehicles
- Unique license plate tracking
- Link vehicles to users

### 📅 Reservation System

- Reserve parking slots
- Reservation logs
- Status tracking (pending, approved, etc.)

### 💳 Payment System

- Parking fee calculation
- Reservation discount support
- Payment status tracking
- Penalty management

### 📷 Monitoring & Security

- License plate scanning
- Suspicious vehicle tracking
- Admin action logs

---

## 🛠 Tech Stack

- **Backend:** Laravel 12
- **Database:** PostgreSQL
- **ORM:** Eloquent
- **Authentication:** Laravel built-in auth
- **Architecture:** MVC Pattern

---

## 🗂 Database Structure

Main entities:

- Users
- Vehicles
- Parking Lots
- Parking Slots
- Reservations
- Parking Logs
- Payments
- Penalties
- License Plate Scans
- Suspicious Vehicles
- Notifications

Relational integrity is enforced using foreign key constraints with cascade rules.

---

## ⚙️ Installation Guide

### 1️⃣ Clone Repository

```bash
git clone https://github.com/tpp72/smart-parking-system.git
cd smart-parking-system
```

### 2️⃣ Install Dependencies

```bash
composer install
```

### 3️⃣ Setup Environment File

```bash
cp .env.example .env
```

Edit .env:

```bash
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=smart-parking-system
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 4️⃣ Generate Application Key

```bash
php artisan key:generate
```

### 5️⃣ Run Database Migration

```bash
php artisan migrate
(Optional) Seed database:
php artisan db:seed
```

### 6️⃣ Start Development Server

```bash
php artisan serve
```

🧠 System Architecture

This project follows Laravel MVC architecture:

Models → Handle database relationships & business logic

Controllers → Handle HTTP requests

Migrations → Define database schema

PostgreSQL → Enforce relational constraints

🔒 Security Notes

.env file is excluded from Git

Database credentials are not committed

Role-based access control

Foreign key constraints prevent orphan data

🚀 Future Improvements

Real-time parking availability dashboard

QR Code entry system

Payment gateway integration

Mobile responsive UI

Admin analytics dashboard

👨‍💻 Developer

Developed by tpp72

📄 License

This project is open-source and available under the MIT License.
