<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\ReservationController as UserReservationController;
use App\Http\Controllers\User\VehicleController as UserVehicleController;
use App\Http\Controllers\User\ParkingLogController as UserParkingLogController;
use App\Http\Controllers\Admin\ParkingLotController;
use App\Http\Controllers\Admin\ParkingSlotController;
use App\Http\Controllers\Admin\EntryExitDeviceController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\ReservationLogController;
use App\Http\Controllers\Admin\AdminActionController;
use App\Http\Controllers\Admin\CheckInController;
use App\Http\Controllers\Admin\CheckOutController;
use App\Http\Controllers\Admin\ParkingLogController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\CarScanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\OwnerApplicationController as AdminOwnerApplicationController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\Owner\ApplicationController as OwnerApplicationController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\ParkingLotController as OwnerParkingLotController;
use App\Http\Controllers\Owner\ParkingSlotController as OwnerParkingSlotController;
use App\Http\Controllers\Owner\ReservationController as OwnerReservationController;
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
})->middleware(['auth', 'verified'])->name('dashboard');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

    // Parking Lots CRUD
    Route::resource('parking-lots', ParkingLotController::class)->except(['show']);
    // Parking Slots CRUD
    Route::resource('parking-slots', ParkingSlotController::class)->except(['show']);
    Route::get('parking-slots/bulk', [ParkingSlotController::class, 'bulkCreate'])->name('parking-slots.bulk.create');
    Route::post('parking-slots/bulk', [ParkingSlotController::class, 'bulkStore'])->name('parking-slots.bulk.store');
    // Entry and Exit Devices CRUD
    Route::resource('devices', EntryExitDeviceController::class)->except(['show']);
    // Users CRUD
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    // ตั้งรหัสชั่วคราว + force reset
    Route::patch('users/{user}/force-reset', [AdminUserController::class, 'forceReset'])->name('users.force-reset');
    // Reservation CRUD
    Route::resource('reservations', ReservationController::class)->except(['show']);
    Route::post('reservations/{reservation}/confirm', [ReservationController::class, 'confirm'])->name('reservations.confirm');
    // Reservation Logs
    Route::get('reservation-logs', [ReservationLogController::class, 'index'])->name('reservation-logs.index');
    Route::get('reservation-logs/export', [ReservationLogController::class, 'export'])->name('reservation-logs.export');
    // Admin Actions Log
    Route::get('admin-actions', [AdminActionController::class, 'index'])->name('admin-actions.index');
    Route::get('admin-actions/export', [AdminActionController::class, 'export'])->name('admin-actions.export');
    // Parking Log History
    Route::get('parking-logs', [ParkingLogController::class, 'index'])->name('parking-logs.index');
    // Vehicles CRUD
    Route::resource('vehicles', VehicleController::class)->except(['show']);
    // Manual Check-In
    Route::get('check-in', [CheckInController::class, 'create'])->name('check-in.create');
    Route::post('check-in', [CheckInController::class, 'store'])->name('check-in.store');
    // Manual Check-Out
    Route::get('check-out', [CheckOutController::class, 'index'])->name('check-out.index');
    Route::post('check-out/{log}', [CheckOutController::class, 'store'])->name('check-out.store');
    // Payments
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/mark-paid', [PaymentController::class, 'markPaid'])->name('payments.mark-paid');
    // AI Car Scan
    Route::get('scan', [CarScanController::class, 'create'])->name('scan.create');
    Route::post('scan', [CarScanController::class, 'store'])->name('scan.store');
    Route::get('scan/history', [CarScanController::class, 'history'])->name('scan.history');
    // Owner Applications
    Route::get('owner-applications', [AdminOwnerApplicationController::class, 'index'])->name('owner-applications.index');
    Route::get('owner-applications/{ownerApplication}', [AdminOwnerApplicationController::class, 'show'])->name('owner-applications.show');
    Route::post('owner-applications/{ownerApplication}/approve', [AdminOwnerApplicationController::class, 'approve'])->name('owner-applications.approve');
    Route::post('owner-applications/{ownerApplication}/reject', [AdminOwnerApplicationController::class, 'reject'])->name('owner-applications.reject');
});

// ===== Public Marketplace =====
Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');

// ===== Owner Routes — Dashboard + Application (all owners, including pending) =====
Route::prefix('owner')->name('owner.')->middleware(['auth', 'verified', 'force.password.reset', 'owner'])->group(function () {
    Route::get('dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');

    // Application status / edit / resubmit (any owner regardless of status)
    Route::get('application', [OwnerApplicationController::class, 'show'])->name('application.show');
    Route::get('application/edit', [OwnerApplicationController::class, 'edit'])->name('application.edit');
    Route::put('application', [OwnerApplicationController::class, 'update'])->name('application.update');
});

// ===== Owner Routes — Management (approved owners only) =====
Route::prefix('owner')->name('owner.')->middleware(['auth', 'verified', 'force.password.reset', 'owner', 'owner.approved'])->group(function () {
    // Parking Lots
    Route::get('parking-lots', [OwnerParkingLotController::class, 'index'])->name('parking-lots.index');
    Route::get('parking-lots/create', [OwnerParkingLotController::class, 'create'])->name('parking-lots.create');
    Route::post('parking-lots', [OwnerParkingLotController::class, 'store'])->name('parking-lots.store');
    Route::get('parking-lots/{parking_lot}/edit', [OwnerParkingLotController::class, 'edit'])->name('parking-lots.edit');
    Route::patch('parking-lots/{parking_lot}', [OwnerParkingLotController::class, 'update'])->name('parking-lots.update');
    Route::patch('parking-lots/{parking_lot}/toggle', [OwnerParkingLotController::class, 'toggle'])->name('parking-lots.toggle');
    Route::delete('parking-lots/{parking_lot}', [OwnerParkingLotController::class, 'destroy'])->name('parking-lots.destroy');

    // Parking Slots
    Route::get('parking-slots/bulk', [OwnerParkingSlotController::class, 'bulkCreate'])->name('parking-slots.bulk.create');
    Route::post('parking-slots/bulk', [OwnerParkingSlotController::class, 'bulkStore'])->name('parking-slots.bulk.store');
    Route::resource('parking-slots', OwnerParkingSlotController::class)->except(['show']);

    // Reservations (read-only + confirm)
    Route::get('reservations', [OwnerReservationController::class, 'index'])->name('reservations.index');
    Route::post('reservations/{reservation}/confirm', [OwnerReservationController::class, 'confirm'])->name('reservations.confirm');

    // Revenue
    Route::get('revenue', [OwnerRevenueController::class, 'index'])->name('revenue.index');
});

// ===== Owner Application — accessible to any authenticated user (to apply) =====
Route::prefix('owner')->name('owner.')->middleware(['auth', 'verified', 'force.password.reset'])->group(function () {
    Route::get('apply', [OwnerApplicationController::class, 'create'])->name('application.create');
    Route::post('apply', [OwnerApplicationController::class, 'store'])->name('application.store');
});

// ===== User Routes (role: user เท่านั้น) =====
Route::prefix('user')->name('user.')->middleware(['auth', 'verified', 'force.password.reset', 'role:user'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'user'])->name('dashboard');
    // การจอง: ดู + สร้าง (เฉพาะของตัวเอง)
    Route::get('reservations', [UserReservationController::class, 'index'])->name('reservations.index');
    Route::get('reservations/create', [UserReservationController::class, 'create'])->name('reservations.create');
    Route::post('reservations', [UserReservationController::class, 'store'])->name('reservations.store');
    // รถของตัวเอง
    Route::get('vehicles', [UserVehicleController::class, 'index'])->name('vehicles.index');
    Route::get('vehicles/create', [UserVehicleController::class, 'create'])->name('vehicles.create');
    Route::post('vehicles', [UserVehicleController::class, 'store'])->name('vehicles.store');
    Route::delete('vehicles/{vehicle}', [UserVehicleController::class, 'destroy'])->name('vehicles.destroy');
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
});

// ===== Profile (ทุก role) =====
Route::middleware(['auth', 'force.password.reset'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__ . '/auth.php';
