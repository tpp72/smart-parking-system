<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ParkingLotController;
use App\Http\Controllers\Admin\ParkingSlotController;
use App\Http\Controllers\Admin\EntryExitDeviceController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\ReservationLogController;
use App\Http\Controllers\Admin\AdminActionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'user'])
    ->middleware(['auth', 'verified', 'force.password.reset'])
    ->name('dashboard');

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
    Route::resource('reservations', ReservationController::class)->except(['show', 'create', 'store']);
    // Reservation Logs
    Route::get('reservation-logs', [ReservationLogController::class, 'index'])->name('reservation-logs.index');
    Route::get('reservation-logs/export', [ReservationLogController::class, 'export'])->name('reservation-logs.export');
    // Admin Actions Log
    Route::get('admin-actions', [AdminActionController::class, 'index'])->name('admin-actions.index');
    Route::get('admin-actions/export', [AdminActionController::class, 'export'])->name('admin-actions.export');
});

Route::middleware(['auth', 'force.password.reset'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__ . '/auth.php';
