<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayslipController;

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
    Route::get('/payroll-runs', [PayrollRunController::class, 'index'])->name('payroll_runs.index');
    Route::post('/payroll-runs', [PayrollRunController::class, 'store'])->name('payroll_runs.store');
    Route::post('/payroll-runs/{run}/compute', [PayrollRunController::class, 'compute'])->name('payroll_runs.compute');
    Route::post('/payroll-runs/{run}/overrides', [PayrollRunController::class, 'saveOverride'])->name('payroll_runs.overrides');
    Route::get('/payroll-runs/{run}/rows', [PayrollRunController::class, 'rows'])->name('payroll_runs.rows');
    Route::post('/payroll-runs/{run}/lock', [PayrollRunController::class, 'lock'])->name('payroll_runs.lock');
    Route::post('/payroll-runs/{run}/unlock', [PayrollRunController::class, 'unlock'])->name('payroll_runs.unlock');
    Route::post('/payroll-runs/{run}/release', [PayrollRunController::class, 'release'])->name('payroll_runs.release');
    Route::post('/payroll-runs/{run}/payslips', [PayslipController::class, 'generate'])->name('payroll_runs.payslips.generate');
    Route::view('/payslip', 'layouts.payslip')->name('payslip');
    Route::get('/payslips/runs', [PayslipController::class, 'runs'])->name('payslips.runs');
    Route::get('/payslips', [PayslipController::class, 'index'])->name('payslips.index');
    Route::view('/report', 'layouts.reports')->name('report');
    Route::view('/settings', 'layouts.settings')->name('settings');
    Route::get('/settings/company-setup', [SettingsController::class, 'getCompanySetup'])->name('settings.company_setup.get');
    Route::post('/settings/company-setup', [SettingsController::class, 'saveCompanySetup'])->name('settings.company_setup.save');
    Route::get('/settings/salary-proration', [SettingsController::class, 'getSalaryProration'])->name('settings.salary_proration.get');
    Route::post('/settings/salary-proration', [SettingsController::class, 'saveSalaryProration'])->name('settings.salary_proration.save');
    Route::get('/settings/timekeeping-rules', [SettingsController::class, 'getTimekeepingRules'])->name('settings.timekeeping.get');
    Route::post('/settings/timekeeping-rules', [SettingsController::class, 'saveTimekeepingRules'])->name('settings.timekeeping.save');
    Route::get('/settings/statutory-setup', [SettingsController::class, 'getStatutorySetup'])->name('settings.statutory.get');
    Route::post('/settings/statutory-setup', [SettingsController::class, 'saveStatutorySetup'])->name('settings.statutory.save');
    Route::get('/settings/withholding-tax-policy', [SettingsController::class, 'getWithholdingTaxPolicy'])->name('settings.withholding_tax.policy.get');
    Route::post('/settings/withholding-tax-policy', [SettingsController::class, 'saveWithholdingTaxPolicy'])->name('settings.withholding_tax.policy.save');
    Route::get('/settings/withholding-tax-brackets', [SettingsController::class, 'getWithholdingTaxBrackets'])->name('settings.withholding_tax.brackets.get');
    Route::post('/settings/withholding-tax-brackets', [SettingsController::class, 'saveWithholdingTaxBrackets'])->name('settings.withholding_tax.brackets.save');
    Route::get('/settings/cash-advance-policy', [SettingsController::class, 'getCashAdvancePolicy'])->name('settings.cash_advance.policy.get');
    Route::post('/settings/cash-advance-policy', [SettingsController::class, 'saveCashAdvancePolicy'])->name('settings.cash_advance.policy.save');
    Route::get('/settings/cash-advances', [SettingsController::class, 'getCashAdvances'])->name('settings.cash_advance.list');
    Route::post('/settings/cash-advances', [SettingsController::class, 'saveCashAdvance'])->name('settings.cash_advance.create');
    Route::patch('/settings/cash-advances/{cashAdvance}', [SettingsController::class, 'updateCashAdvanceStatus'])->name('settings.cash_advance.update');

    Route::get('/settings/payroll-calendar', [SettingsController::class, 'getPayrollCalendar'])->name('settings.payroll_calendar.get');
    Route::post('/settings/payroll-calendar', [SettingsController::class, 'savePayrollCalendar'])->name('settings.payroll_calendar.save');
    Route::get('/settings/overtime-rules', [SettingsController::class, 'getOvertimeRules'])->name('settings.overtime.get');
    Route::post('/settings/overtime-rules', [SettingsController::class, 'saveOvertimeRules'])->name('settings.overtime.save');
    Route::get('/settings/attendance-codes', [SettingsController::class, 'getAttendanceCodes'])->name('settings.attendance_codes.get');
    Route::post('/settings/attendance-codes', [SettingsController::class, 'saveAttendanceCodes'])->name('settings.attendance_codes.save');
});
