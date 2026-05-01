<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeCaseController;
use App\Http\Controllers\EmployeeDisciplineController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PayslipClaimController;
use App\Http\Controllers\DeductionCaseController;
use App\Http\Controllers\EmployeeLoanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminUserController;

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
    Route::put('/profile', [AuthController::class, 'updateProfile'])->middleware('role:admin')->name('profile.update');

    // Shared (Admin + HR)
    Route::middleware('role:admin,hr')->group(function () {
        Route::get('/employee-records', [EmployeeController::class, 'page'])->name('employee.records');
        Route::view('/attendance', 'layouts.attendance')->name('attendance');
        Route::get('/loans', [EmployeeLoanController::class, 'page'])->name('loans');

        // Payslip claims (Admin + HR)
        Route::get('/payslip-claims', [PayslipClaimController::class, 'page'])->name('payslip.claims');
        Route::get('/payslip-claims/{run}/claim-sheet', [PayslipClaimController::class, 'downloadClaimSheet'])->name('payslip.claims.sheet')->whereNumber('run');
        Route::post('/payslip-claims/{run}/proofs', [PayslipClaimController::class, 'uploadProof'])->name('payslip.claims.proofs.upload')->whereNumber('run');
        Route::get('/payslip-claims/{run}/proofs', fn ($run) => redirect()->route('payslip.claims', ['run_id' => $run]))->whereNumber('run');
        Route::get('/payslip-claims/proofs/{proof}/download', [PayslipClaimController::class, 'downloadProof'])->name('payslip.claims.proofs.download')->whereNumber('proof');
        Route::delete('/payslip-claims/proofs/{proof}', [PayslipClaimController::class, 'destroyProof'])->name('payslip.claims.proofs.destroy')->whereNumber('proof');
        Route::post('/payslip-claims/{run}/employees/{employeeId}/toggle', [PayslipClaimController::class, 'toggleClaim'])->name('payslip.claims.toggle')->whereNumber(['run', 'employeeId']);

        // Employee data endpoints used by pages
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/heartbeat', [EmployeeController::class, 'heartbeat'])->name('employees.heartbeat');
        Route::get('/employees/next-id', [EmployeeController::class, 'nextId'])->name('employees.nextId');
        Route::get('/employees/filters', [EmployeeController::class, 'filters'])->name('employees.filters');
        Route::get('/employees/suggest', [EmployeeController::class, 'suggest'])->name('employees.suggest');
        Route::get('/employees/pl-balances', [EmployeeController::class, 'paidLeaveBalances'])->name('employees.plBalances');
        Route::get('/employees/{emp_no}/area-history', [EmployeeController::class, 'areaHistory'])->name('employees.areaHistory');
        Route::get('/employees/{emp_no}/pl-balance', [EmployeeController::class, 'paidLeaveBalance'])->name('employees.plBalance');
        Route::get('/employees/{emp_no}/attendance-year', [EmployeeController::class, 'attendanceYear'])->name('employees.attendanceYear');

        // Employee Case Management
        Route::get('/employee-cases', [EmployeeCaseController::class, 'page'])->name('employee.cases');
        Route::get('/employee-cases/filters', [EmployeeCaseController::class, 'filters'])->name('employee.cases.filters');
        Route::get('/employee-cases/list', [EmployeeCaseController::class, 'index'])->name('employee.cases.list');
        Route::post('/employee-cases', [EmployeeCaseController::class, 'store'])->name('employee.cases.store');
        Route::get('/employee-cases/emp-history/{employeeId}', [EmployeeCaseController::class, 'employeeHistory'])->name('employee.cases.empHistory');
        Route::patch('/employee-cases/{case}/advance', [EmployeeCaseController::class, 'advance'])->name('employee.cases.advance');
        Route::get('/employee-cases/{case}', [EmployeeCaseController::class, 'show'])->name('employee.cases.show');

        // Loans
        Route::get('/loans/list', [EmployeeLoanController::class, 'list'])->name('loans.list');
        Route::get('/loans/{loan}', [EmployeeLoanController::class, 'show'])->name('loans.show');
        Route::get('/loans/{loan}/history', [EmployeeLoanController::class, 'history'])->name('loans.history');
        Route::post('/loans', [EmployeeLoanController::class, 'store'])->name('loans.store');
        Route::put('/loans/{loan}', [EmployeeLoanController::class, 'update'])->name('loans.update');
        Route::post('/loans/{loan}/pause', [EmployeeLoanController::class, 'pause'])->name('loans.pause');
        Route::post('/loans/{loan}/resume', [EmployeeLoanController::class, 'resume'])->name('loans.resume');
        Route::post('/loans/{loan}/close', [EmployeeLoanController::class, 'close'])->name('loans.close');

        // Attendance
        Route::get('/attendance/area', [AttendanceController::class, 'resolveArea'])->name('attendance.area');
        Route::get('/attendance/template', [AttendanceController::class, 'downloadTemplate'])->name('attendance.template');
        Route::post('/attendance/import', [AttendanceController::class, 'importExcel'])->name('attendance.import');
        Route::get('/attendance/records', [AttendanceController::class, 'index'])->name('attendance.records');
        Route::post('/attendance/records', [AttendanceController::class, 'store'])->name('attendance.records.store');
        Route::put('/attendance/records/{record}', [AttendanceController::class, 'update'])->name('attendance.records.update');
        Route::delete('/attendance/records/{record}', [AttendanceController::class, 'destroy'])->name('attendance.records.destroy');

        // Cash Advance transactions (used by Loans page)
        Route::get('/settings/cash-advances', [SettingsController::class, 'getCashAdvances'])->name('settings.cash_advance.list');
        Route::post('/settings/cash-advances', [SettingsController::class, 'saveCashAdvance'])->name('settings.cash_advance.create');
        Route::patch('/settings/cash-advances/{cashAdvance}', [SettingsController::class, 'updateCashAdvanceStatus'])->name('settings.cash_advance.update');
        Route::delete('/settings/cash-advances/{cashAdvance}', [SettingsController::class, 'destroyCashAdvance'])->name('settings.cash_advance.delete');
    });

    // Admin-only
    Route::middleware('role:admin')->group(function () {
        Route::get('/index', [DashboardController::class, 'index'])->name('index');
        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
        // Employee CRUD
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::put('/employees/{emp_no}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{emp_no}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        Route::post('/employees/bulk-assign', [EmployeeController::class, 'bulkAssign'])->name('employees.bulkAssign');

        // Disciplinary
        Route::view('/employee-disciplinary', 'layouts.disciplinary')->name('employee.disciplinary');
        Route::get('/employees/{emp_no}/discipline-records', [EmployeeDisciplineController::class, 'index'])->name('employees.discipline.index');
        Route::get('/employees/{emp_no}/tardiness', [EmployeeDisciplineController::class, 'tardiness'])->name('employees.tardiness');
        Route::post('/employees/discipline-import', [EmployeeDisciplineController::class, 'import'])->name('employees.discipline.import');

        // Payroll processing
        Route::view('/payroll-processing', 'layouts.payroll_processing')->name('payroll.processing');
        Route::get('/payroll-runs', [PayrollRunController::class, 'index'])->name('payroll_runs.index');
        Route::get('/payroll-runs/attendance-check', [PayrollRunController::class, 'attendanceCheck'])->name('payroll_runs.attendance_check');
        Route::post('/payroll-runs', [PayrollRunController::class, 'store'])->name('payroll_runs.store');
        Route::delete('/payroll-runs/{run}', [PayrollRunController::class, 'destroy'])->name('payroll_runs.destroy');
        Route::post('/payroll-runs/{run}/compute', [PayrollRunController::class, 'compute'])->name('payroll_runs.compute');
        Route::post('/payroll-runs/{run}/overrides', [PayrollRunController::class, 'saveOverride'])->name('payroll_runs.overrides');
        Route::get('/payroll-runs/{run}/rows', [PayrollRunController::class, 'rows'])->name('payroll_runs.rows');
        Route::get('/payroll-runs/{run}/field-area-allocations', [PayrollRunController::class, 'fieldAreaAllocations'])->name('payroll_runs.field_area_allocations');
        Route::post('/payroll-runs/{run}/lock', [PayrollRunController::class, 'lock'])->name('payroll_runs.lock');
        Route::post('/payroll-runs/{run}/unlock', [PayrollRunController::class, 'unlock'])->name('payroll_runs.unlock');
        Route::post('/payroll-runs/{run}/release', [PayrollRunController::class, 'release'])->name('payroll_runs.release');
        Route::post('/payroll-runs/{run}/payslips', [PayslipController::class, 'generate'])->name('payroll_runs.payslips.generate');

        // Payslips
        Route::view('/payslip', 'layouts.payslip')->name('payslip');
        Route::get('/payslips/runs', [PayslipController::class, 'runs'])->name('payslips.runs');
        Route::get('/payslips', [PayslipController::class, 'index'])->name('payslips.index');
        Route::get('/payslips/print', [PayslipController::class, 'printView'])->name('payslips.print');
        Route::get('/payslips/export', [PayslipController::class, 'export'])->name('payslips.export');
        Route::get('/payslips/export-pdf', [PayslipController::class, 'exportPdf'])->name('payslips.export_pdf');
        Route::post('/payslips/send-email', [PayslipController::class, 'sendEmail'])->name('payslips.send_email');
        Route::post('/payslips/release', [PayslipController::class, 'releaseSelected'])->name('payslips.release');
        Route::post('/payslips/runs/{run}/release-all', [PayslipController::class, 'releaseAll'])->name('payslips.release_all');

        Route::view('/report', 'layouts.reports')->name('report');

        // Settings
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
        Route::get('/settings/payroll-deduction-policy', [SettingsController::class, 'getPayrollDeductionPolicy'])->name('settings.payroll_deduction.policy.get');
        Route::post('/settings/payroll-deduction-policy', [SettingsController::class, 'savePayrollDeductionPolicy'])->name('settings.payroll_deduction.policy.save');
        Route::get('/settings/payroll-calendar', [SettingsController::class, 'getPayrollCalendar'])->name('settings.payroll_calendar.get');
        Route::post('/settings/payroll-calendar', [SettingsController::class, 'savePayrollCalendar'])->name('settings.payroll_calendar.save');
        Route::get('/settings/overtime-rules', [SettingsController::class, 'getOvertimeRules'])->name('settings.overtime.get');
        Route::post('/settings/overtime-rules', [SettingsController::class, 'saveOvertimeRules'])->name('settings.overtime.save');
        Route::get('/settings/attendance-codes', [SettingsController::class, 'getAttendanceCodes'])->name('settings.attendance_codes.get');
        Route::post('/settings/attendance-codes', [SettingsController::class, 'saveAttendanceCodes'])->name('settings.attendance_codes.save');

        // Create and manage accounts
        Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::patch('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');

        // Deduction Cases (Charges / Shortages)
        Route::get('/employees/{emp_no}/deduction-cases', [DeductionCaseController::class, 'index']);
        Route::post('/employees/{emp_no}/deduction-cases', [DeductionCaseController::class, 'store']);
        Route::put('/employees/{emp_no}/deduction-cases/{case}', [DeductionCaseController::class, 'update']);
        Route::post('/employees/{emp_no}/deduction-cases/{case}/close', [DeductionCaseController::class, 'close']);
        Route::get('/employees/{emp_no}/deduction-cases/{case}/schedules', [DeductionCaseController::class, 'schedules']);
    });
});
