<?php

namespace App\Providers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
use App\Models\PayslipClaim;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn ($user) => ($user->role ?? 'admin') === 'admin');
        Gate::define('viewCompensation', fn ($user) => ($user->role ?? 'admin') === 'admin');

        View::composer('partials.topbar', function ($view) {
            $now = Carbon::now();
            $notifications = [];

            $latestReleasedRun = PayrollRun::query()
                ->where('status', 'Released')
                ->orderByDesc('released_at')
                ->orderByDesc('id')
                ->first();

            if ($latestReleasedRun) {
                $period = trim((string) ($latestReleasedRun->period_month ?? ''));
                $cutoff = trim((string) ($latestReleasedRun->cutoff ?? ''));
                $periodLabel = $period !== '' ? $period : 'latest period';
                $cutoffLabel = $cutoff !== '' ? " ({$cutoff})" : '';
                $notifications[] = [
                    'level' => 'info',
                    'message' => "Payslip for {$periodLabel}{$cutoffLabel} has been released.",
                    'target_url' => url('/payslip') . '?run_id=' . $latestReleasedRun->id,
                ];

                $expectedClaims = PayrollRunRow::query()
                    ->where('payroll_run_id', $latestReleasedRun->id)
                    ->count();
                $claimed = PayslipClaim::query()
                    ->where('payroll_run_id', $latestReleasedRun->id)
                    ->whereNotNull('claimed_at')
                    ->count();
                $unclaimed = max(0, $expectedClaims - $claimed);
                if ($unclaimed > 0) {
                    $notifications[] = [
                        'level' => 'warning',
                        'message' => "{$unclaimed} employees have not yet claimed their payslip for {$periodLabel}{$cutoffLabel}.",
                        'target_url' => url('/payslip-claims') . '?run_id=' . $latestReleasedRun->id,
                    ];
                }
            }

            $cutoffStart = $now->copy()->day <= 15
                ? $now->copy()->startOfMonth()
                : $now->copy()->day(16);
            $cutoffEnd = $now->copy()->day <= 15
                ? $now->copy()->day(15)->endOfDay()
                : $now->copy()->endOfMonth();

            $incompleteAttendanceEmployees = AttendanceRecord::query()
                ->whereBetween('date', [$cutoffStart->toDateString(), $cutoffEnd->toDateString()])
                ->where(function ($q) {
                    $q->whereIn('status', ['Present', 'Late', 'Half-day'])
                        ->where(function ($inner) {
                            $inner->whereNull('clock_in')->orWhereNull('clock_out');
                        });
                })
                ->distinct('employee_id')
                ->count('employee_id');

            if ($incompleteAttendanceEmployees > 0) {
                $notifications[] = [
                    'level' => 'warning',
                    'message' => "{$incompleteAttendanceEmployees} employees have incomplete attendance records for this cutoff.",
                    'target_url' => url('/attendance'),
                ];
            }

            $birthdayNames = Employee::query()
                ->whereNotNull('birthday')
                ->whereMonth('birthday', $now->month)
                ->whereDay('birthday', $now->day)
                ->orderBy('first_name')
                ->get(['first_name', 'middle_name', 'last_name'])
                ->map(function ($e) {
                    return trim(collect([$e->first_name, $e->middle_name, $e->last_name])->filter()->implode(' '));
                })
                ->filter()
                ->values();

            if ($birthdayNames->isNotEmpty()) {
                $notifications[] = [
                    'level' => 'info',
                    'message' => 'Birthday celebrants today: ' . $birthdayNames->implode(', ') . '.',
                    'target_url' => url('/employee-records'),
                ];
            }

            $anniversaryRows = Employee::query()
                ->whereNotNull('date_hired')
                ->whereMonth('date_hired', $now->month)
                ->whereDay('date_hired', $now->day)
                ->get(['first_name', 'middle_name', 'last_name', 'date_hired']);

            foreach ($anniversaryRows as $row) {
                $name = trim(collect([$row->first_name, $row->middle_name, $row->last_name])->filter()->implode(' '));
                if ($name === '') {
                    continue;
                }
                $years = Carbon::parse($row->date_hired)->diffInYears($now);
                if ($years <= 0) {
                    continue;
                }
                $yearLabel = $years === 1 ? '1 year' : "{$years} years";
                $notifications[] = [
                    'level' => 'info',
                    'message' => "Work anniversary today: {$name} celebrates {$yearLabel} with the company.",
                    'target_url' => url('/employee-records'),
                ];
            }

            $probationEndingCount = Employee::query()
                ->whereNotNull('date_hired')
                ->where(function ($q) {
                    $q->whereRaw('LOWER(COALESCE(employment_type, "")) LIKE ?', ['%probation%'])
                        ->orWhereRaw('LOWER(COALESCE(employment_type, "")) LIKE ?', ['%non-regular%'])
                        ->orWhereRaw('LOWER(COALESCE(employment_type, "")) LIKE ?', ['%trainee%']);
                })
                ->get(['date_hired'])
                ->filter(function ($e) use ($now) {
                    $probationEnd = Carbon::parse($e->date_hired)->addMonthsNoOverflow(6)->startOfDay();
                    $daysLeft = $now->copy()->startOfDay()->diffInDays($probationEnd, false);
                    return $daysLeft >= 0 && $daysLeft <= 7;
                })
                ->count();

            if ($probationEndingCount > 0) {
                $notifications[] = [
                    'level' => 'warning',
                    'message' => "{$probationEndingCount} employees are reaching the end of probation in 7 days. Please review for regularization.",
                    'target_url' => url('/employee-records'),
                ];
            }

            $view->with('topbarNotifications', $notifications);
        });
    }
}
