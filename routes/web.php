<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OwnerApplicationDocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\ReservationController as UserReservationController;
use App\Http\Controllers\User\ParkingLogController as UserParkingLogController;
use App\Http\Controllers\Admin\ParkingLotController;
use App\Http\Controllers\Admin\ParkingSlotController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\ReservationLogController;
use App\Http\Controllers\Admin\AdminActionController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ParkingLogController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\SuspiciousVehicleController;
use App\Http\Controllers\CarScanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\OwnerApplicationController as AdminOwnerApplicationController;
use App\Http\Controllers\Admin\OwnerResignationController as AdminOwnerResignationController;
use App\Http\Controllers\Owner\ResignationController as OwnerResignationController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\Owner\ApplicationController as OwnerApplicationController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\ParkingLogController as OwnerParkingLogController;
use App\Http\Controllers\Owner\ParkingLotController as OwnerParkingLotController;
use App\Http\Controllers\Owner\ParkingSlotController as OwnerParkingSlotController;
use App\Http\Controllers\Owner\PaymentController as OwnerPaymentController;
use App\Http\Controllers\Owner\ReservationController as OwnerReservationController;
use App\Http\Controllers\Owner\ReservationLogController as OwnerReservationLogController;
use App\Http\Controllers\Owner\RevenueController as OwnerRevenueController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Smart redirect by role
Route::get('/dashboard', function () {
    $role = request()->user()?->role;
    if ($role === 'admin') {
        return redirect()->route('admin.dashboard');
    }
    if ($role === 'owner') {
        return redirect()->route('owner.dashboard');
    }
    return redirect()->route('user.dashboard');
})->middleware(['auth', 'verified', 'force.password.reset'])->name('dashboard');

// ===== Admin Routes (role: admin) — Force Password Reset ครอบคลุม Admin ด้วย =====
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'force.password.reset', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

    // Parking Lots CRUD
    Route::resource('parking-lots', ParkingLotController::class)->except(['show']);
    // Parking Slots CRUD
    Route::resource('parking-slots', ParkingSlotController::class)->except(['show']);
    Route::get('parking-slots/bulk', [ParkingSlotController::class, 'bulkCreate'])->name('parking-slots.bulk.create');
    Route::post('parking-slots/bulk', [ParkingSlotController::class, 'bulkStore'])->name('parking-slots.bulk.store');
    // Users CRUD
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    // ตั้งรหัสชั่วคราว + force reset
    Route::patch('users/{user}/force-reset', [AdminUserController::class, 'forceReset'])->name('users.force-reset');
    // ลบผู้ใช้ (owner จะลบลานจอดของตัวเองไปด้วย + ยกเลิกการจองที่ค้างอยู่)
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    // Reservations — ไม่มีการสร้าง/แก้ไข/ลบ/ยืนยันด้วยมือ (ยืนยันผ่าน Mark as Paid เงินมัดจำ)
    Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->name('reservations.check-in');
    Route::post('reservations/{reservation}/check-out', [ReservationController::class, 'checkOut'])->name('reservations.check-out');
    // Reservation Logs
    Route::get('reservation-logs', [ReservationLogController::class, 'index'])->name('reservation-logs.index');
    Route::get('reservation-logs/export', [ReservationLogController::class, 'export'])->name('reservation-logs.export');
    // Admin Actions Log
    Route::get('admin-actions', [AdminActionController::class, 'index'])->name('admin-actions.index');
    Route::get('admin-actions/export', [AdminActionController::class, 'export'])->name('admin-actions.export');

    // CSV Export (ทั้งระบบ กรองลานได้)
    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('exports/reservations', [ExportController::class, 'reservations'])->name('exports.reservations');
    Route::get('exports/parking-logs', [ExportController::class, 'parkingLogs'])->name('exports.parking-logs');
    Route::get('exports/revenue', [ExportController::class, 'revenue'])->name('exports.revenue');
    // Parking Log History
    Route::get('parking-logs', [ParkingLogController::class, 'index'])->name('parking-logs.index');
    // Payments
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/mark-paid', [PaymentController::class, 'markPaid'])->name('payments.mark-paid');
    // AI Car Scan
    Route::get('scan', [CarScanController::class, 'create'])->name('scan.create');
    Route::post('scan', [CarScanController::class, 'store'])->name('scan.store');
    Route::get('scan/history', [CarScanController::class, 'history'])->name('scan.history');
    // Suspicious Vehicles (Blacklist)
    Route::post('suspicious-vehicles/{suspiciousVehicle}/toggle', [SuspiciousVehicleController::class, 'toggle'])->name('suspicious-vehicles.toggle');
    Route::resource('suspicious-vehicles', SuspiciousVehicleController::class)->except(['show']);
    // Owner Applications
    Route::get('owner-applications', [AdminOwnerApplicationController::class, 'index'])->name('owner-applications.index');
    Route::get('owner-applications/{ownerApplication}', [AdminOwnerApplicationController::class, 'show'])->name('owner-applications.show');
    Route::post('owner-applications/{ownerApplication}/approve', [AdminOwnerApplicationController::class, 'approve'])->name('owner-applications.approve');
    Route::post('owner-applications/{ownerApplication}/reject', [AdminOwnerApplicationController::class, 'reject'])->name('owner-applications.reject');

    // Owner Resignations
    Route::get('owner-resignations', [AdminOwnerResignationController::class, 'index'])->name('owner-resignations.index');
    Route::post('owner-resignations/{ownerResignation}/approve', [AdminOwnerResignationController::class, 'approve'])->name('owner-resignations.approve');
    Route::post('owner-resignations/{ownerResignation}/reject', [AdminOwnerResignationController::class, 'reject'])->name('owner-resignations.reject');
});

// ===== Public Marketplace =====
Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');

// ===== Owner Routes — Dashboard + Self-demotion (role: owner) =====
Route::prefix('owner')->name('owner.')->middleware(['auth', 'verified', 'force.password.reset', 'role:owner'])->group(function () {
    Route::get('dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');
    // คำร้องลาออก — มีผลเมื่อ Admin อนุมัติ
    Route::post('resignation', [OwnerResignationController::class, 'store'])->name('resignation.store');
});

// ===== Owner Routes — Management (approved owners only) =====
Route::prefix('owner')->name('owner.')->middleware(['auth', 'verified', 'force.password.reset', 'role:owner', 'owner.approved'])->group(function () {
    // Parking Lots
    Route::get('parking-lots', [OwnerParkingLotController::class, 'index'])->name('parking-lots.index');
    Route::get('parking-lots/create', [OwnerParkingLotController::class, 'create'])->name('parking-lots.create');
    Route::post('parking-lots', [OwnerParkingLotController::class, 'store'])->name('parking-lots.store');
    Route::get('parking-lots/{parking_lot}/edit', [OwnerParkingLotController::class, 'edit'])->name('parking-lots.edit');
    Route::patch('parking-lots/{parking_lot}', [OwnerParkingLotController::class, 'update'])->name('parking-lots.update');
    Route::delete('parking-lots/{parking_lot}', [OwnerParkingLotController::class, 'destroy'])->name('parking-lots.destroy');

    // Parking Slots
    Route::get('parking-slots/bulk', [OwnerParkingSlotController::class, 'bulkCreate'])->name('parking-slots.bulk.create');
    Route::post('parking-slots/bulk', [OwnerParkingSlotController::class, 'bulkStore'])->name('parking-slots.bulk.store');
    Route::resource('parking-slots', OwnerParkingSlotController::class)->except(['show']);

    // Reservations (read-only + check-in/out) — ยืนยันการจองผ่าน Mark as Paid เงินมัดจำ
    Route::get('reservations', [OwnerReservationController::class, 'index'])->name('reservations.index');
    Route::post('reservations/{reservation}/check-in', [OwnerReservationController::class, 'checkIn'])->name('reservations.check-in');
    Route::post('reservations/{reservation}/check-out', [OwnerReservationController::class, 'checkOut'])->name('reservations.check-out');
    // Reservation Log ของลานตัวเอง (ไม่มี CSV Export)
    Route::get('reservation-logs', [OwnerReservationLogController::class, 'index'])->name('reservation-logs.index');

    // Payments
    Route::get('payments', [OwnerPaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/mark-paid', [OwnerPaymentController::class, 'markPaid'])->name('payments.mark-paid');
    // Parking Log History
    Route::get('parking-logs', [OwnerParkingLogController::class, 'index'])->name('parking-logs.index');

    // Revenue
    Route::get('revenue', [OwnerRevenueController::class, 'index'])->name('revenue.index');

    // AI Car Scan
    Route::get('scan', [CarScanController::class, 'create'])->name('scan.create');
    Route::post('scan', [CarScanController::class, 'store'])->name('scan.store');
    Route::get('scan/history', [CarScanController::class, 'history'])->name('scan.history');
});

// ===== Owner Application — accessible to any authenticated user (apply + status) =====
Route::prefix('owner')->name('owner.')->middleware(['auth', 'verified', 'force.password.reset'])->group(function () {
    Route::get('apply', [OwnerApplicationController::class, 'create'])->name('application.create');
    Route::post('apply', [OwnerApplicationController::class, 'store'])->name('application.store');
    // Status/edit/resubmit accessible while role is still 'user' (pending state)
    Route::get('application', [OwnerApplicationController::class, 'show'])->name('application.show');
    Route::get('application/edit', [OwnerApplicationController::class, 'edit'])->name('application.edit');
    Route::put('application', [OwnerApplicationController::class, 'update'])->name('application.update');
});

// ===== User Routes (role: user เท่านั้น) =====
Route::prefix('user')->name('user.')->middleware(['auth', 'verified', 'force.password.reset', 'role:user'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'user'])->name('dashboard');
    // การจอง: ดู + สร้าง (เฉพาะของตัวเอง)
    Route::get('reservations', [UserReservationController::class, 'index'])->name('reservations.index');
    Route::get('reservations/create', [UserReservationController::class, 'create'])->name('reservations.create');
    Route::post('reservations', [UserReservationController::class, 'store'])->name('reservations.store');
    Route::get('reservations/{reservation}/edit', [UserReservationController::class, 'edit'])->name('reservations.edit');
    Route::patch('reservations/{reservation}/plate', [UserReservationController::class, 'update'])->name('reservations.update-plate');
    Route::post('reservations/{reservation}/cancel', [UserReservationController::class, 'cancel'])->name('reservations.cancel');
    // ประวัติการจอดของตัวเอง
    Route::get('parking-logs', [UserParkingLogController::class, 'index'])->name('parking-logs.index');
    // AI Car Scan (user)
    Route::get('scan', [CarScanController::class, 'create'])->name('scan.create');
    Route::post('scan', [CarScanController::class, 'store'])->name('scan.store');
});

// ===== Notifications (ทุก role) =====
Route::middleware(['auth', 'verified', 'force.password.reset'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // เอกสารแนบคำขอเป็น Owner (ไฟล์ส่วนตัว) — Controller ตรวจสิทธิ์: ผู้ยื่นคำขอหรือ Admin เท่านั้น
    Route::get('owner-applications/{ownerApplication}/document', [OwnerApplicationDocumentController::class, 'show'])->name('owner-applications.document');
});

// ===== Profile (ทุก role) =====
Route::middleware(['auth', 'force.password.reset'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__ . '/auth.php';
