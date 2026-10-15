<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BedenganController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\PestObservationController;
use App\Http\Controllers\SprayHistoryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// --- Authentication (Public) ---
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

// --- Protected Dashboard & Web API (Requires Login Session) ---
Route::middleware('auth.session')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Read Data (All roles)
    Route::get('/api/history', [SprayHistoryController::class, 'index']);
    Route::get('/api/observations', [PestObservationController::class, 'index']);
    Route::get('/api/bedengans', [BedenganController::class, 'index']);
    Route::get('/api/devices', [DeviceController::class, 'index']);

    // Input Data (All roles)
    Route::post('/api/history/add', [SprayHistoryController::class, 'store']);
    Route::post('/api/observations/add', [PestObservationController::class, 'store']);

    // Update Data (Data owner or Admin)
    Route::post('/api/history/update/{id}', [SprayHistoryController::class, 'update']);
    Route::post('/api/observations/update/{id}', [PestObservationController::class, 'update']);

    // --- Admin-only Operations ---
    Route::middleware('admin')->group(function () {
        // Delete records (with audit logging)
        Route::post('/api/history/delete/{id}', [SprayHistoryController::class, 'destroy']);
        Route::post('/api/observations/delete/{id}', [PestObservationController::class, 'destroy']);

        // Live Actuator activities feed & web toggle logging
        Route::get('/api/device-activities', [DeviceController::class, 'getActivities']);
        Route::post('/api/device-activities/web', [DeviceController::class, 'logWebActivity']);

        // Manage devices
        Route::post('/api/devices/add', [DeviceController::class, 'store']);
        Route::post('/api/devices/delete/{id}', [DeviceController::class, 'destroy']);

        // Manage bedengans
        Route::post('/api/bedengans/add', [BedenganController::class, 'store']);
        Route::post('/api/bedengans/delete/{id}', [BedenganController::class, 'destroy']);

        // Manage users & credentials
        Route::get('/api/users', [UserController::class, 'index']);
        Route::post('/api/users/add', [UserController::class, 'store']);
        Route::post('/api/users/reset-password', [UserController::class, 'resetPassword']);
        Route::post('/api/users/delete/{id}', [UserController::class, 'destroy']);
    });
});

// --- IoT Hardware Endpoints (Protected by DEVICE_TOKEN, invoked by ESP32) ---
Route::post('/api/devices/heartbeat', [DeviceController::class, 'heartbeat']);
Route::post('/api/device-activities/report', [DeviceController::class, 'reportActivity']);
