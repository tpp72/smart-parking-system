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
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Smart redirect: admin → admin.dashboard, user → user.dashboard
Route::get('/dashboard', function () {
    $role = request()->user()?->role;
    if ($role === 'admin') {
        return redirect()->route('admin.dashboard');
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
