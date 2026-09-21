<?php

/*
|--------------------------------------------------------------------------
| ชื่อหน้าบนแท็บเบราว์เซอร์ (layouts/app.blade.php: "ชื่อหน้า | Smart Parking")
|--------------------------------------------------------------------------
| ใช้คำเดียวกับหัวข้อหน้า (h1) และเมนูใน App\Support\Navigation · หน้า guest กำหนดชื่อเองผ่าน <x-guest-layout title>
*/

return [

    'dashboard'                         => 'หน้าหลัก',

    // ── ผู้ดูแลระบบ ────────────────────────────────────────
    'admin.dashboard'                   => 'ภาพรวมระบบ',
    'admin.reservations.index'          => 'การจอง',
    'admin.scan.create'                 => 'AI สแกน',
    'admin.scan.history'                => 'ประวัติสแกน',
    'admin.parking-lots.index'          => 'ลานจอด',
    'admin.parking-lots.create'         => 'เพิ่มลานจอด',
    'admin.parking-lots.edit'           => 'แก้ไขลานจอด',
    'admin.parking-slots.index'         => 'ช่องจอด',
    'admin.parking-slots.create'        => 'เพิ่มช่องจอด',
    'admin.parking-slots.edit'          => 'แก้ไขช่องจอด',
    'admin.parking-slots.bulk.create'   => 'เพิ่มหลายช่อง',
    'admin.payments.index'              => 'ชำระเงิน',
    'admin.users.index'                 => 'ผู้ใช้',
    'admin.users.create'                => 'เพิ่มผู้ใช้',
    'admin.users.edit'                  => 'จัดการผู้ใช้',
    'admin.owner-applications.index'    => 'คำขอเป็นเจ้าของลาน',
    'admin.owner-applications.show'     => 'พิจารณาคำขอเป็นเจ้าของลาน',
    'admin.owner-resignations.index'    => 'คำร้องลาออก',
    'admin.suspicious-vehicles.index'   => 'บัญชีดำ',
    'admin.suspicious-vehicles.create'  => 'เพิ่มเข้าบัญชีดำ',
    'admin.suspicious-vehicles.edit'    => 'แก้ไขบัญชีดำ',
    'admin.parking-logs.index'          => 'ประวัติการจอด',
    'admin.reservation-logs.index'      => 'Log การจอง',
    'admin.admin-actions.index'         => 'Audit Log',
    'admin.exports.index'               => 'ส่งออก CSV',

    // ── เจ้าของลาน ─────────────────────────────────────────
    'owner.dashboard'                   => 'ภาพรวม',
    'owner.reservations.index'          => 'การจอง',
    'owner.scan.create'                 => 'AI สแกน',
    'owner.scan.history'                => 'ประวัติสแกน',
    'owner.parking-lots.index'          => 'ลานจอด',
    'owner.parking-lots.create'         => 'เพิ่มลานจอด',
    'owner.parking-lots.edit'           => 'แก้ไขลานจอด',
    'owner.parking-slots.index'         => 'ช่องจอด',
    'owner.parking-slots.create'        => 'เพิ่มช่องจอด',
    'owner.parking-slots.edit'          => 'แก้ไขช่องจอด',
    'owner.parking-slots.bulk.create'   => 'เพิ่มหลายช่อง',
    'owner.payments.index'              => 'ชำระเงิน',
    'owner.revenue.index'               => 'รายได้',
    'owner.parking-logs.index'          => 'ประวัติการจอด',
    'owner.reservation-logs.index'      => 'Log การจอง',

    // ── คำขอเป็นเจ้าของลาน (บัญชีผู้ใช้) ─────────────────────
    'owner.application.create'          => 'สมัครเป็นเจ้าของลาน',
    'owner.application.show'            => 'คำขอเป็นเจ้าของลาน',
    'owner.application.edit'            => 'แก้ไขคำขอและส่งใหม่',

    // ── ผู้ใช้ ─────────────────────────────────────────────
    'user.dashboard'                    => 'หน้าหลัก',
    'user.reservations.index'           => 'การจองของฉัน',
    'user.reservations.create'          => 'จองที่จอด',
    'user.reservations.edit'            => 'แก้ไขข้อมูลรถ',
    'user.parking-logs.index'           => 'ประวัติการจอด',
    'user.scan.create'                  => 'AI สแกน',

    // ── ทุกบทบาท ───────────────────────────────────────────
    'notifications.index'               => 'การแจ้งเตือน',
    'profile.edit'                      => 'โปรไฟล์',
    'marketplace.index'                 => 'ตลาดที่จอดรถ',
];
