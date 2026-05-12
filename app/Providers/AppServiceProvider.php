<?php

namespace App\Providers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
use App\Models\PayslipClaim;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
            $user = Auth::user();
            $now = Carbon::now();
            $cacheKey = 'topbar_notifications:v1:' . $now->format('Ymd') . ':' . ($now->day <= 15 ? 'a' : 'b');
            $notifications = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($now) {
                $items = [];

                try {
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
                        $items[] = [
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
                            $items[] = [
                                'level' => 'warning',
                                'message' => "{$unclaimed} employees have not yet claimed their payslip for {$periodLabel}{$cutoffLabel}.",
                                'target_url' => url('/payslip-claims') . '?run_id=' . $latestReleasedRun->id,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Topbar notification failed: latest release/unclaimed', ['error' => $e->getMessage()]);
                }

                $cutoffStart = $now->copy()->day <= 15
                    ? $now->copy()->startOfMonth()
                    : $now->copy()->day(16);
                $cutoffEnd = $now->copy()->day <= 15
                    ? $now->copy()->day(15)->endOfDay()
                    : $now->copy()->endOfMonth();

                try {
                    $incompleteAttendanceEmployees = AttendanceRecord::query()
                        ->whereBetween('date', [$cutoffStart->toDateString(), $cutoffEnd->toDateString()])
                        ->whereIn('status', ['Present', 'Late', 'Half-day'])
                        ->where(function ($inner) {
                            $inner->whereNull('clock_in')
                                ->orWhereNull('clock_out')
                                ->orWhereRaw('TRIM(COALESCE(clock_in, "")) = ""')
                                ->orWhereRaw('TRIM(COALESCE(clock_out, "")) = ""');
                        })
                        ->distinct('employee_id')
                        ->count('employee_id');

                    if ($incompleteAttendanceEmployees > 0) {
                        $items[] = [
                            'level' => 'warning',
                            'message' => "{$incompleteAttendanceEmployees} employees have incomplete attendance records for this cutoff.",
                            'target_url' => url('/attendance'),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Topbar notification failed: incomplete attendance', ['error' => $e->getMessage()]);
                }

                try {
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
                        $items[] = [
                            'level' => 'info',
                            'message' => 'Birthday celebrants today: ' . $birthdayNames->implode(', ') . '.',
                            'target_url' => url('/employee-records'),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Topbar notification failed: birthdays', ['error' => $e->getMessage()]);
                }

                try {
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
                        $items[] = [
                            'level' => 'info',
                            'message' => "Work anniversary today: {$name} celebrates {$yearLabel} with the company.",
                            'target_url' => url('/employee-records'),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Topbar notification failed: anniversaries', ['error' => $e->getMessage()]);
                }

                try {
                    $statusRows = Employee::query()
                        ->whereNotNull('date_hired')
                        ->where(function ($q) {
                            $q->whereRaw('LOWER(COALESCE(employment_type, "")) LIKE ?', ['%probation%'])
                                ->orWhereRaw('LOWER(COALESCE(employment_type, "")) LIKE ?', ['%probationary%'])
                                ->orWhereRaw('LOWER(COALESCE(employment_type, "")) LIKE ?', ['%trainee%']);
                        })
                        ->get(['first_name', 'middle_name', 'last_name', 'date_hired', 'employment_type'])
                        ->filter(function ($employee) use ($now) {
                            // Probationary/Trainee term is 6 months.
                            // Start notifying one month ahead and keep active while type is unchanged.
                            $notifyStart = Carbon::parse($employee->date_hired)->addMonthsNoOverflow(5)->startOfDay();
                            return $notifyStart->lessThanOrEqualTo($now);
                        });

                    $emitStatusNotification = function ($rows, string $label, int $termMonths) use (&$items, $now) {
                        if ($rows->isEmpty()) {
                            return;
                        }

                        $todayStart = $now->copy()->startOfDay();
                        $nextMonthCutoff = $now->copy()->addMonthNoOverflow()->endOfDay();
                        $dueNextMonthNames = $rows
                            ->filter(function ($employee) use ($todayStart, $nextMonthCutoff, $termMonths) {
                                $statusEnd = Carbon::parse($employee->date_hired)->addMonthsNoOverflow($termMonths)->endOfDay();
                                return $statusEnd->greaterThanOrEqualTo($todayStart)
                                    && $statusEnd->lessThanOrEqualTo($nextMonthCutoff);
                            })
                            ->map(function ($employee) {
                                return trim(collect([$employee->first_name, $employee->middle_name, $employee->last_name])->filter()->implode(' '));
                            })
                            ->filter()
                            ->values();

                        $statusWord = strtolower($label);
                        $totalCount = $rows->count();
                        $overdueCount = $rows->filter(function ($employee) use ($todayStart, $termMonths) {
                            $statusEnd = Carbon::parse($employee->date_hired)->addMonthsNoOverflow($termMonths)->endOfDay();
                            return $statusEnd->lt($todayStart);
                        })->count();
                        $message = "{$totalCount} {$statusWord} employees still need employment status update.";

                        if ($dueNextMonthNames->isNotEmpty()) {
                            $preview = $dueNextMonthNames->take(5)->implode(', ');
                            $remaining = $dueNextMonthNames->count() - min(5, $dueNextMonthNames->count());
                            $suffix = $remaining > 0 ? " and {$remaining} more" : '';
                            $message = "{$dueNextMonthNames->count()} {$statusWord} employees will finish within the next month: {$preview}{$suffix}. This will stay active until employment type is changed.";
                        } elseif ($overdueCount > 0) {
                            $message = "{$overdueCount} {$statusWord} employees are already overdue and still need employment status update.";
                        }

                        $items[] = [
                            'level' => 'warning',
                            'message' => $message,
                            'target_url' => url('/employee-records?probation_due=1'),
                        ];
                    };

                    $probationRows = $statusRows->filter(function ($employee) {
                        $type = strtolower(trim((string) ($employee->employment_type ?? '')));
                        return str_contains($type, 'probation');
                    })->filter(function ($employee) use ($now) {
                        $notifyStart = Carbon::parse($employee->date_hired)->addMonthsNoOverflow(5)->startOfDay();
                        return $notifyStart->lessThanOrEqualTo($now);
                    })->values();
                    $emitStatusNotification($probationRows, 'Probationary', 6);

                    $traineeRows = $statusRows->filter(function ($employee) {
                        $type = strtolower(trim((string) ($employee->employment_type ?? '')));
                        return str_contains($type, 'trainee');
                    })->filter(function ($employee) use ($now) {
                        $notifyStart = Carbon::parse($employee->date_hired)->addMonthsNoOverflow(2)->startOfDay();
                        return $notifyStart->lessThanOrEqualTo($now);
                    })->values();
                    $emitStatusNotification($traineeRows, 'Trainee', 3);
                } catch (\Throwable $e) {
                    Log::warning('Topbar notification failed: probation ending', ['error' => $e->getMessage()]);
                }

                return $items;
            });

            if (($user->role ?? 'admin') !== 'admin') {
                foreach ($notifications as &$notification) {
                    $targetUrl = (string) ($notification['target_url'] ?? '');
                    $isPayslipPage = str_contains($targetUrl, '/payslip')
                        && !str_contains($targetUrl, '/payslip-claims')
                        && !str_contains($targetUrl, '/payslips');
                    if ($isPayslipPage) {
                        $notification['target_url'] = url('/payslip-claims');
                    }
                }
                unset($notification);
            }

            $view->with('topbarNotifications', $notifications);
        });
    }
}
