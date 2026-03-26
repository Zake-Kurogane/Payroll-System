<aside class="side">
    <div class="brand">
        <div class="brand__mark">
            <img class="brand__logo" src="{{ asset('image/logo.png') }}"
                alt="Aura Fortune G5 Traders Corporation logo" />
        </div>

        <div class="brand__text">
            <div class="brand__title">AURA FORTUNE G5</div>
            <div class="brand__sub">TRADERS CORPORATION</div>
        </div>
    </div>

    <nav class="menu" id="sidebarMenu">
        <style>
            /* Keep sidebar footer (time/date) visible by scrolling the nav area only when a dropdown is open. */
            .side > .menu.menu--scroll {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                overflow-x: hidden;
            }

            /* local submenu styling to avoid touching other pages' CSS bundles */
            .menu__group {
                border: none;
                border-radius: 12px;
                overflow: visible;
                background: transparent;
            }
            .menu__group summary {
                list-style: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 14px;
                border-radius: 12px;
                width: 100%;
            }
            .menu__group summary::-webkit-details-marker {
                display: none;
            }
            .menu__chevron {
                margin-left: auto;
                font-weight: 900;
                color: inherit;
                font-size: 12px;
            }
            .submenu {
                display: flex;
                flex-direction: column;
                border-top: 1px solid var(--line, rgba(17, 24, 39, 0.1));
                background: #fff;
                margin-top: 6px;
                border-radius: 12px;
            }
            .menu__item--child {
                padding-left: 18px;
            }
            details.menu__group[open] .menu__chevron {
                transform: rotate(180deg);
            }
            .menu__parent-link {
                color: inherit;
                text-decoration: none;
                font-weight: inherit;
                line-height: 1.2;
                display: inline-block;
                flex: 1;
                font-size: 12px;
            }
            .menu__item--parent .menu__icon { flex-shrink: 0; }
            .menu__item--parent.is-active {
                color: #fff;
                background: var(--maroon);
                box-shadow: 0 8px 18px rgba(156, 29, 60, 0.22);
            }
            .menu__item--parent {
                transition: transform 0.12s ease, background 0.12s ease;
            }
        </style>
        @can('admin')
            <a class="menu__item {{ request()->routeIs('index') ? 'is-active' : '' }}" href="{{ route('index') }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path d="M3 3h8v8H3V3zm10 0h8v5h-8V3zM3 13h8v8H3v-8zm10 7v-10h8v10h-8z" />
                    </svg>
                </span>
                <span>DASHBOARD</span>
            </a>
        @endcan

        <details id="employeeRecordsGroup"
            class="menu__group {{ request()->routeIs('employee.records', 'employee.disciplinary', 'employee.cases') ? 'is-open' : '' }}"
            {{ request()->routeIs('employee.records', 'employee.disciplinary', 'employee.cases') ? 'open' : '' }}>
            <summary class="menu__item menu__item--parent {{ request()->routeIs('employee.records', 'employee.disciplinary', 'employee.cases') ? 'is-active' : '' }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path
                            d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.94 1.97 3.45V20h7v-3.5c0-2.33-4.67-3.5-7-3.5z" />
                    </svg>
                </span>
                <a class="menu__parent-link" href="{{ route('employee.records') }}">EMPLOYEE<br />RECORDS</a>
                <span class="menu__chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:currentColor;display:block;">
                        <path d="M7 10l5 5 5-5z" />
                    </svg>
                </span>
            </summary>
            <div class="submenu">
                <a class="menu__item menu__item--child {{ request()->routeIs('employee.records') ? 'is-active' : '' }}"
                    href="{{ route('employee.records') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico">
                            <path
                                d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.94 1.97 3.45V20h7v-3.5c0-2.33-4.67-3.5-7-3.5z" />
                        </svg>
                    </span>
                    <span>Employee List</span>
                </a>
                <a class="menu__item menu__item--child {{ request()->routeIs('employee.cases') ? 'is-active' : '' }}"
                    href="{{ route('employee.cases') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico">
                            <path fill="currentColor"
                                d="M2.75 21.25a1.25 1.25 0 0 1 1.25-1.25H12a1.25 1.25 0 1 1 0 2.5H4a1.25 1.25 0 0 1-1.25-1.25ZM4.7 14.7l6.58 6.58 2.12-2.12-6.58-6.58-2.12 2.12Zm8.7-7.41 1.41-1.41 6.6 6.6a1 1 0 0 1 0 1.41l-2.12 2.12a1 1 0 0 1-1.41 0L13.4 8.42 7.82 14l-2.12-2.12 7.7-7.7a1 1 0 0 1 1.41 0Z" />
                        </svg>
                    </span>
                    <span>Employee Case Management</span>
                </a>
            </div>
        </details>

        <a class="menu__item {{ request()->routeIs('attendance') ? 'is-active' : '' }}"
            href="{{ route('attendance') }}">
            <span class="menu__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="ico">
                    <path
                        d="M7 2h10v2h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V2zm0 6h10V6H7v2zm0 4h6v2H7v-2zm0 4h8v2H7v-2z" />
                </svg>
            </span>
            <span>ATTENDANCE</span>
        </a>

        <a class="menu__item {{ request()->routeIs('loans') ? 'is-active' : '' }}"
            href="{{ route('loans') }}">
            <span class="menu__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="ico">
                    <path d="M4 7h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2zm0 2v8h16V9H4z" />
                    <path d="M7 13h6v2H7v-2z" />
                </svg>
            </span>
            <span>LOANS</span>
        </a>

        @can('admin')
            <a class="menu__item {{ request()->routeIs('payroll.processing') ? 'is-active' : '' }}"
                href="{{ route('payroll.processing') }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path d="M4 4h10a2 2 0 0 1 2 2v2h4v12H6a2 2 0 0 1-2-2V4zm2 2v14h12V10h-4V6H6z" />
                        <path d="M9 12h6v2H9v-2zm0 4h6v2H9v-2z" />
                    </svg>
                </span>
                <span>PAYROLL<br />PROCESSING</span>
            </a>
        @endcan

        <details id="payslipGroup"
            class="menu__group {{ request()->routeIs('payslip', 'payslip.claims') ? 'is-open' : '' }}"
            {{ request()->routeIs('payslip', 'payslip.claims') ? 'open' : '' }}>
            <summary
                class="menu__item menu__item--parent {{ request()->routeIs('payslip', 'payslip.claims') ? 'is-active' : '' }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1v5h5" />
                        <path d="M7 12h10v2H7v-2zm0 4h10v2H7v-2z" />
                    </svg>
                </span>
                <a class="menu__parent-link" href="{{ Gate::allows('admin') ? route('payslip') : route('payslip.claims') }}">PAYSLIP</a>
                <span class="menu__chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:currentColor;display:block;">
                        <path d="M7 10l5 5 5-5z" />
                    </svg>
                </span>
            </summary>
            <div class="submenu">
                @can('admin')
                    <a class="menu__item menu__item--child {{ request()->routeIs('payslip') ? 'is-active' : '' }}"
                        href="{{ route('payslip') }}">
                        <span class="menu__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="ico">
                                <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1v5h5" />
                                <path d="M7 12h10v2H7v-2zm0 4h10v2H7v-2z" />
                            </svg>
                        </span>
                        <span>Payslips</span>
                    </a>
                @endcan
                <a class="menu__item menu__item--child {{ request()->routeIs('payslip.claims') ? 'is-active' : '' }}"
                    href="{{ route('payslip.claims') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                            <!-- Signature (stroke only) -->
                            <path d="M3 18.5c2.2-2.1 3.6 1 5.2-.4 1.2-1.1 1.4-3.7 2.8-3.7 1.2 0 .8 2.9 2.3 2.9 1.2 0 2-1 2.9-1.9"
                                style="fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round" />
                            <!-- Pen at end of signature (filled, kept inside viewBox to avoid clipping) -->
                            <path d="M13.8 15.2l4.2-4.2 1.8 1.8-4.2 4.2-2.7.9.9-2.7z"
                                style="fill:currentColor;stroke:none" />
                            <path d="M18 11l1-1c.25-.25.65-.25.9 0l1.1 1.1c.25.25.25.65 0 .9l-1 1L18 11z"
                                style="fill:currentColor;stroke:none" />
                        </svg>
                    </span>
                    <span>Claims</span>
                </a>
            </div>
        </details>

        @can('admin')
            <a class="menu__item {{ request()->routeIs('report') ? 'is-active' : '' }}"
                href="{{ route('report') }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path d="M4 19h16v2H2V3h2v16z" />
                        <path d="M7 17V9h3v8H7zm5 0V5h3v12h-3zm5 0v-6h3v6h-3z" />
                    </svg>
                </span>
                <span>REPORT</span>
            </a>
        @endcan

        <script>
            (function() {
                const menu = document.getElementById('sidebarMenu');
                if (!menu) return;
                const empGroup = document.getElementById('employeeRecordsGroup');
                const payslipGroup = document.getElementById('payslipGroup');

                const sync = () => {
                    const open = !!(empGroup && empGroup.open) || !!(payslipGroup && payslipGroup.open);
                    menu.classList.toggle('menu--scroll', open);
                };

                sync();
                window.addEventListener('pageshow', sync);
                empGroup && empGroup.addEventListener('toggle', sync);
                payslipGroup && payslipGroup.addEventListener('toggle', sync);
            })();
        </script>
    </nav>

    <div class="side__footer">
        <div class="time" id="clock">--:-- --</div>
        <div class="date" id="date">--/--/----</div>
    </div>
</aside>
