<?php

namespace App\Http\Controllers;

use App\Models\AreaPlace;
use App\Models\Assignment;
use App\Models\Employee;
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

        return view('layouts.dashboard', [
            'totalEmployees' => $totalEmployees,
            'assignments' => $assignments,
            'groupedAreaPlaces' => $groupedAreaPlaces,
        ]);
    }
}
