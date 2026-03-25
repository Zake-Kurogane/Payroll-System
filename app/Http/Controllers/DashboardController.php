<?php

namespace App\Http\Controllers;

use App\Models\AreaPlace;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunRow;
use App\Models\PayslipClaim;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $assignments = Assignment::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label');

        $assignmentOrder = Assignment::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label')
            ->all();

        $rows = AreaPlace::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['label', 'parent_assignment']);

        $grouped = [];
        foreach ($rows as $row) {
            $parent = $row->parent_assignment ?? 'Field';
            $grouped[$parent][] = $row->label;
        }

        $ordered = [];
        foreach ($assignmentOrder as $label) {
            if (isset($grouped[$label])) {
                $ordered[$label] = $grouped[$label];
            }
        }

        $groupedAreaPlaces = $ordered;

        $totalEmployees = Employee::count();

        $latestReleasedRun = PayrollRun::query()
            ->where('status', 'Released')
            ->orderByDesc('id')
            ->first();

        $unclaimedPayslips = 0;
        if ($latestReleasedRun) {
            $totalInRun = PayrollRunRow::query()->where('payroll_run_id', $latestReleasedRun->id)->count();
            $claimedInRun = PayslipClaim::query()
                ->where('payroll_run_id', $latestReleasedRun->id)
                ->whereNotNull('claimed_at')
                ->count();
            $unclaimedPayslips = max(0, (int) $totalInRun - (int) $claimedInRun);
        }

        return view('layouts.dashboard', [
            'totalEmployees' => $totalEmployees,
            'assignments' => $assignments,
            'groupedAreaPlaces' => $groupedAreaPlaces,
            'unclaimedPayslips' => $unclaimedPayslips,
            'latestReleasedRunId' => $latestReleasedRun?->id,
        ]);
    }
}
