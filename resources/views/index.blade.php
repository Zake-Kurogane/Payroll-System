<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll System | Dashboard</title>

    @vite(['resources/css/index.css', 'resources/js/index.js'])
</head>

<body>
    <div class="shell">

        <!-- SIDEBAR -->
        <aside class="side">
            <div class="brand">
                <div class="brand__mark">
                    <img class="brand__logo" src="/image/logo.png" alt="Aura Fortune G5 Traders Corporation logo" />
                </div>

                <div class="brand__text">
                    <div class="brand__title">AURA FORTUNE G5</div>
                    <div class="brand__sub">TRADERS CORPORATION</div>
                </div>
            </div>

            <nav class="menu">
                <a class="menu__item {{ request()->routeIs('index') ? 'is-active' : '' }}" href="{{ route('index') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <!-- DASHBOARD -->
                        <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                            <path d="M3 3h8v8H3V3zm10 0h8v5h-8V3zM3 13h8v8H3v-8zm10 7v-10h8v10h-8z" />
                        </svg>
                    </span>
                    <span>DASHBOARD</span>
                </a>

                <a class="menu__item {{ request()->routeIs('employee.records') ? 'is-active' : '' }}"
                    href="{{ route('employee.records') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <!-- EMPLOYEE RECORDS -->
                        <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                            <path
                                d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.94 1.97 3.45V20h7v-3.5c0-2.33-4.67-3.5-7-3.5z" />
                        </svg>
                    </span>
                    <span>EMPLOYEE<br />RECORDS</span>
                </a>

                <a class="menu__item {{ request()->routeIs('attendance') ? 'is-active' : '' }}"
                    href="{{ route('attendance') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <!-- ATTENDANCE -->
                        <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                            <path
                                d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5zm10.3-4.3-1.4-1.4-5.3 5.3-2.1-2.1-1.4 1.4 3.5 3.5 6.7-6.7z" />
                        </svg>
                    </span>
                    <span>ATTENDANCE</span>
                </a>

                <a class="menu__item {{ request()->routeIs('payroll.processing') ? 'is-active' : '' }}"
                    href="{{ route('payroll.processing') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <!-- PAYROLL PROCESSING -->
                        <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                            <path d="M4 4h10a2 2 0 0 1 2 2v2h4v12H6a2 2 0 0 1-2-2V4zm2 2v14h12V10h-4V6H6z" />
                            <path d="M9 12h6v2H9v-2zm0 4h6v2H9v-2z" />
                        </svg>
                    </span>
                    <span>PAYROLL<br />PROCESSING</span>
                </a>

                <a class="menu__item {{ request()->routeIs('payslip') ? 'is-active' : '' }}"
                    href="{{ route('payslip') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <!-- PAYSLIP -->
                        <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                            <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1v5h5" />
                            <path d="M7 12h10v2H7v-2zm0 4h10v2H7v-2z" />
                        </svg>
                    </span>
                    <span>PAYSLIP</span>
                </a>

                <a class="menu__item {{ request()->routeIs('report') ? 'is-active' : '' }}"
                    href="{{ route('report') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <!-- REPORT -->
                        <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                            <path d="M4 19h16v2H2V3h2v16z" />
                            <path d="M7 17V9h3v8H7zm5 0V5h3v12h-3zm5 0v-6h3v6h-3z" />
                        </svg>
                    </span>
                    <span>REPORT</span>
                </a>
            </nav>

            <div class="side__footer">
                <div class="time" id="clock">--:-- --</div>
                <div class="date" id="date">--/--/----</div>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="main">

            <!-- TOP BAR -->
            <header class="top">
                <div>
                    <div class="top__title">WELCOME</div>
                    <div class="top__sub">ADMIN</div>
                </div>

                <div class="top__right">
                    <div class="user-menu">
                        <button class="pill-user" type="button" id="userMenuBtn" aria-haspopup="true"
                            aria-expanded="false">
                            <span class="pill-user__name">ADMIN</span>
                            <span class="pill-user__avatar" aria-hidden="true">
                                <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                                    <path
                                        d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z" />
                                </svg>
                            </span>
                        </button>

                        <div class="user-dropdown" id="userMenu" role="menu" aria-labelledby="userMenuBtn">
                            <a href="#" class="user-dropdown__item" role="menuitem">Edit Profile</a>
                            <a href="{{ route('settings') }}" class="user-dropdown__item" role="menuitem">Settings</a>

                            <div class="user-dropdown__divider" aria-hidden="true"></div>
                            <a href="{{ url('/login') }}" class="user-dropdown__item" role="menuitem">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- CONTENT -->
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
                            <option value="1-15">1–15</option>
                            <option value="16-30">16–30</option>
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
        </main>
    </div>
</body>

</html>
