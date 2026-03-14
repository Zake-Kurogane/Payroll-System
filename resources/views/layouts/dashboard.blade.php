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
                <div class="f">
                    <label>Payroll Month</label>
                    <input id="cutoffMonth" type="month" />
                </div>

                <div class="f">
                    <label>Cut-off</label>
                    <select id="cutoffSelect"></select>
                </div>

                <div style="display:grid;gap:8px;">
                    <label style="font-weight:600;font-size:14px;color:var(--muted);">Assignment</label>
                    <div class="seg seg--pill" id="assignSeg" role="group" aria-label="Filter by assignment">
                        <button type="button" class="seg__btn seg__btn--emp is-active" data-assign="">All</button>
                        @foreach ($assignments ?? [] as $a)
                            <div class="seg__btn-wrap">
                                <button type="button" class="seg__btn seg__btn--emp" data-assign="{{ $a }}">
                                    {{ $a }} <span class="seg__chevron">▾</span>
                                </button>
                                <div class="seg__dropdown" data-group="{{ $a }}" style="display:none;">
                                    @foreach ($groupedAreaPlaces[$a] ?? [] as $ap)
                                        <button type="button" class="seg__dropdown-item"
                                            data-place="{{ $ap }}">{{ $ap }}</button>
                                    @endforeach
                                </div>
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
                    <div class="kpi__value" id="kpiEmployees">{{ number_format($totalEmployees ?? 0) }}</div>
                    <div class="kpi__label">TOTAL EMPLOYEE</div>
                </div>
            </a>

            <article class="kpi">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiGross">₱ 0</div>
                    <div class="kpi__label">GROSS</div>
                </div>
            </article>

            <article class="kpi">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiDed">₱ 0</div>
                    <div class="kpi__label">DEDUCTIONS</div>
                </div>
            </article>

            <article class="kpi">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiNet">₱ 0</div>
                    <div class="kpi__label">NET PAY</div>
                </div>
            </article>

            <article class="kpi">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiUnclaimed">0</div>
                    <div class="kpi__label">UNCLAIMED PAYSLIP</div>
                </div>
            </article>
        </div>

        <!-- GRID -->
        <div class="grid">
            <!-- CHART -->
            <section class="card chart-card">
                <div class="card__head">
                    <div class="card__title">Payroll Cost Trend (Last 6 Months)</div>

                    <div class="legend">
                        <span class="dot dot--net"></span><span>Net pay</span>
                        <span class="dot dot--ded"></span><span>Deduction</span>
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

            <!-- TODO -->
            <section class="card todo">
                <div class="card__head">
                    <div class="card__title">To-Do / Actions</div>
                </div>

                <div class="todo__list">
                    <div class="todo__item">
                        <div class="todo__name">Attendance import</div>
                    </div>

                    <div class="todo__item">
                        <div class="todo__name">Payroll pending:</div>
                        <div class="todo__value" id="todoPending">12</div>
                    </div>

                    <div class="todo__item">
                        <div class="todo__name">Payslips not generated:</div>
                        <div class="todo__value" id="todoPayslip">6</div>
                    </div>
                </div>
            </section>
        </div>
    </section>
@endsection
