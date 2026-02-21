<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SettingsController;

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

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/heartbeat', [EmployeeController::class, 'heartbeat'])->name('employees.heartbeat');
    Route::get('/employees/filters', [EmployeeController::class, 'filters'])->name('employees.filters');
    Route::get('/employees/suggest', [EmployeeController::class, 'suggest'])->name('employees.suggest');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('/employees/bulk-assign', [EmployeeController::class, 'bulkAssign'])->name('employees.bulkAssign');
    Route::put('/employees/{emp_no}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{emp_no}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::view('/index', 'layouts.dashboard')->name('index');
    Route::get('/employee-records', [EmployeeController::class, 'page'])->name('employee.records');
    Route::view('/attendance', 'layouts.attendance')->name('attendance');

    Route::get('/attendance/template', [AttendanceController::class, 'downloadTemplate'])->name('attendance.template');
    Route::post('/attendance/import', [AttendanceController::class, 'importExcel'])->name('attendance.import');
    Route::get('/attendance/records', [AttendanceController::class, 'index'])->name('attendance.records');
    Route::post('/attendance/records', [AttendanceController::class, 'store'])->name('attendance.records.store');
    Route::put('/attendance/records/{record}', [AttendanceController::class, 'update'])->name('attendance.records.update');
    Route::delete('/attendance/records/{record}', [AttendanceController::class, 'destroy'])->name('attendance.records.destroy');
    Route::view('/payroll-processing', 'layouts.payroll_processing')->name('payroll.processing');
    Route::view('/payslip', 'layouts.payslip')->name('payslip');
    Route::view('/report', 'layouts.reports')->name('report');
    Route::view('/settings', 'layouts.settings')->name('settings');

    Route::get('/settings/payroll-calendar', [SettingsController::class, 'getPayrollCalendar'])->name('settings.payroll_calendar.get');
    Route::post('/settings/payroll-calendar', [SettingsController::class, 'savePayrollCalendar'])->name('settings.payroll_calendar.save');
    Route::get('/settings/pay-groups', [SettingsController::class, 'getPayGroups'])->name('settings.pay_groups.get');
    Route::post('/settings/pay-groups', [SettingsController::class, 'savePayGroups'])->name('settings.pay_groups.save');
    Route::get('/settings/overtime-rules', [SettingsController::class, 'getOvertimeRules'])->name('settings.overtime.get');
    Route::post('/settings/overtime-rules', [SettingsController::class, 'saveOvertimeRules'])->name('settings.overtime.save');
    Route::get('/settings/attendance-codes', [SettingsController::class, 'getAttendanceCodes'])->name('settings.attendance_codes.get');
    Route::post('/settings/attendance-codes', [SettingsController::class, 'saveAttendanceCodes'])->name('settings.attendance_codes.save');
});
