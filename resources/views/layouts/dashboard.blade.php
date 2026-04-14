@extends('layouts.app')

@section('title', 'Dashboard')

@section('vite')
    @vite(['resources/css/index.css', 'resources/css/dashboard.css', 'resources/js/dashboard.js'])
@endsection

@section('content')
    <section class="content">
        <div class="headline">
            <div>
                <h1>DASHBOARD</h1>
            </div>
        </div>

        <!-- FILTERS -->
        <section class="card cutoffbar">
            <div class="cutoffbar__left">
                <div class="f f--wide">
                    <label>Payroll Month</label>
                    <input id="cutoffMonth" type="month" />
                </div>

                <div class="f f--wide">
                    <label>Cut-off</label>
                    <select id="cutoffSelect"></select>
                </div>

                <div class="f f--wide">
                    <label>Type of Deductions</label>
                    <select id="deductionTypeSelect" aria-label="Filter by deduction type">
                        <option value="all">All</option>
                        <option value="loans">Loans</option>
                        <option value="government">Government</option>
                        <option value="attendance">Attendance</option>
                        <option value="charges">Charges</option>
                        <option value="cash_advance">Cash Advance</option>
                    </select>
                </div>

                <div class="filter-assign" style="display:grid;gap:8px;">
                    <label style="font-weight:600;font-size:14px;color:var(--muted);">Assignment</label>
                    <div class="seg seg--pill" id="assignSeg" role="group" aria-label="Filter by assignment">
                        <button type="button" class="seg__btn seg__btn--emp is-active" data-assign="">All</button>
                        @foreach ($assignments ?? [] as $a)
                            @php
                                $places = $groupedAreaPlaces[$a] ?? [];
                            @endphp
                            <div class="seg__btn-wrap">
                                <button type="button" class="seg__btn seg__btn--emp" data-assign="{{ $a }}">
                                    {{ $a }}
                                    @if (!empty($places))
                                        <span class="seg__chevron">▾</span>
                                    @endif
                                </button>
                                @if (!empty($places))
                                    <div class="seg__dropdown" data-group="{{ $a }}" style="display:none;">
                                        @foreach ($places as $ap)
                                            <button type="button" class="seg__dropdown-item"
                                                data-place="{{ $ap }}">{{ $ap }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </section>

        <!-- KPI CARDS -->
        <div class="kpis">
            <a class="kpi kpi--link" href="{{ route('employee.records') }}" aria-label="View employee records">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiEmployees">0</div>
                    <div class="kpi__label">TOTAL EMPLOYEE</div>
                </div>
            </a>

            <a class="kpi kpi--link" id="kpiGrossLink" href="{{ route('report') }}" aria-label="Open reports for gross">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiGross">₱ 0</div>
                    <div class="kpi__label">GROSS</div>
                </div>
            </a>

            <a class="kpi kpi--link" id="kpiDedLink" href="{{ route('report') }}" aria-label="Open reports for deductions">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiDed">₱ 0</div>
                    <div class="kpi__label">DEDUCTIONS</div>
                </div>
            </a>

            <a class="kpi kpi--link" id="kpiNetLink" href="{{ route('report') }}" aria-label="Open reports for net pay">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiNet">₱ 0</div>
                    <div class="kpi__label">NET PAY</div>
                </div>
            </a>

            <a class="kpi kpi--link" href="{{ $latestProcessedRunId ? route('payslip.claims', ['run_id' => $latestProcessedRunId]) : route('payslip.claims') }}"
                aria-label="View payslip claims">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiUnclaimed">0</div>
                    <div class="kpi__label">UNCLAIMED PAYSLIP</div>
                </div>
            </a>
        </div>

        <!-- GRID -->
        <div class="grid">
            <!-- CHART -->
            <section class="card chart-card">
                <div class="card__head">
                    <div class="card__title">Payroll Cost Trend (Last 6 Months)</div>

                    <div class="legend" style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span class="dot dot--net"></span><span>Net pay</span>
                            <span class="dot dot--ded"></span><span>Deduction</span>
                        </div>
                        <div class="trendMode" role="group" aria-label="Trend view mode">
                            <button type="button" class="trendMode__btn is-active" id="trendByCutoffBtn">By Cutoff</button>
                            <button type="button" class="trendMode__btn" id="trendMonthlyBtn">Monthly Total</button>
                        </div>
                    </div>
                </div>

                <div class="chartwrap">
                    <div class="yaxis">
                        <span>400K</span><span>300K</span><span>200K</span><span>100K</span>
                    </div>

                    <div class="chart" id="chart"></div>
                </div>

                <div class="xaxis" id="chartLabels"></div>
            </section>

            <!-- Recent Activity -->
            <section class="card todo">
                <div class="card__head">
                    <div class="card__title">Recent Activity</div>
                </div>

                <div class="todo__list" id="recentActivityList">
                    <div class="todo__item">
                        <div class="todo__name">No recent activity.</div>
                    </div>
                </div>
            </section>
        </div>
    </section>
@endsection
