<?php

use App\Http\Controllers\ActuatorCommandController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PestObservationController;
use App\Http\Controllers\SprayingRecordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/pest-observations', [PestObservationController::class, 'store'])
        ->name('pest-observations.store');

    Route::post('/spraying-records', [SprayingRecordController::class, 'store'])
        ->name('spraying-records.store');
    Route::delete('/spraying-records/{sprayingRecord}', [SprayingRecordController::class, 'destroy'])
        ->name('spraying-records.destroy');

    Route::post('/actuators/{actuator}/commands', [ActuatorCommandController::class, 'store'])
        ->name('actuators.commands.store');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
