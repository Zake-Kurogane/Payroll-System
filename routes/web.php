<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth.simple')->group(function () {
    Route::view('/index', 'index')->name('index');
    Route::view('/employee-records', 'emp_records')->name('employee.records');
    Route::view('/attendance', 'layouts.attendance')->name('attendance');
    Route::view('/payroll-processing', 'payroll_processing')->name('payroll.processing');
    Route::view('/payslip', 'payslip')->name('payslip');
    Route::view('/report', 'report')->name('report');
});
