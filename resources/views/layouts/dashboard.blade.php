@extends('layouts.app')

@section('title', 'Dashboard')

@section('vite')
    @vite(['resources/css/index.css', 'resources/js/index.js'])
@endsection

@section('content')
    <section class="content">
        <div class="headline">
            <div>
                <h1>DASHBOARD</h1>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filters card">
            <div class="f">
                <label>Month:</label>
                <select id="month">
                    <option>Jan 2026</option>
                    <option>Dec 2025</option>
                    <option>Nov 2025</option>
                </select>
            </div>

            <div class="f">
                <label>Cut-off:</label>
                <select id="cutoff">
                    <option value="1-15">1&ndash;15</option>
                    <option value="16-30">16&ndash;30</option>
                </select>
            </div>

            <div class="f">
                <label>Dept:</label>
                <select id="dept">
                    <option>All</option>
                    <option>Admin</option>
                    <option>HR</option>
                    <option>IT</option>
                </select>
            </div>

            <div class="f">
                <label>Place:</label>
                <select id="place">
                    <option>All</option>
                    <option>Tagum</option>
                    <option>Davao</option>
                </select>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="kpis">
            <article class="kpi">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiEmployees">50</div>
                    <div class="kpi__label">TOTAL EMPLOYEES</div>
                </div>
            </article>

            <article class="kpi">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiPending">12</div>
                    <div class="kpi__label">PENDING PAYROLL</div>
                </div>
            </article>

            <article class="kpi">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiNet">₱ 412,500</div>
                    <div class="kpi__label">TOTAL NET PAY</div>
                </div>
            </article>

            <article class="kpi">
                <div class="kpi__meta">
                    <div class="kpi__value" id="kpiDed">₱ 412,500</div>
                    <div class="kpi__label">TOTAL DEDUCTIONS</div>
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
