<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', [AuthController::class, 'showLogin'])
    ->middleware('guest')
    ->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('login.submit');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/profile/me', [AuthController::class, 'me'])->name('profile.me');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');

    Route::view('/index', 'layouts.dashboard')->name('index');
    Route::view('/employee-records', 'layouts.emp_records')->name('employee.records');
    Route::view('/attendance', 'layouts.attendance')->name('attendance');
    Route::view('/payroll-processing', 'layouts.payroll_processing')->name('payroll.processing');
    Route::view('/payslip', 'layouts.payslip')->name('payslip');
    Route::view('/report', 'layouts.reports')->name('report');
    Route::view('/settings', 'layouts.settings')->name('settings');
});
