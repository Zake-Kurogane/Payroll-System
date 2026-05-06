<aside class="side" id="sideNav" aria-hidden="false">
    <button class="side__mobileClose" type="button" id="mobileNavClose" aria-label="Close navigation">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18.3 5.7a1 1 0 0 0-1.4 0L12 10.6 7.1 5.7a1 1 0 1 0-1.4 1.4l4.9 4.9-4.9 4.9a1 1 0 0 0 1.4 1.4l4.9-4.9 4.9 4.9a1 1 0 0 0 1.4-1.4l-4.9-4.9 4.9-4.9a1 1 0 0 0 0-1.4Z" />
        </svg>
    </button>
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

        @can('admin')
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
                                <path d="M14.2 2.6 20.4 8.8l-1.8 1.8-6.2-6.2 1.8-1.8z" />
                                <path d="M11.4 5.4 17.6 11.6l-2.2 2.2-6.2-6.2 2.2-2.2z" />
                                <path d="M8.8 8 15 14.2l-1.8 1.8L7 9.8 8.8 8z" />
                                <path d="m14.9 12.7 2.1-2.1 4.7 4.7a1.5 1.5 0 0 1-2.1 2.1l-4.7-4.7z" />
                                <path d="M2.4 18.2h8.4a1.3 1.3 0 0 1 0 2.6H2.4a1.3 1.3 0 0 1 0-2.6z" />
                            </svg>
                        </span>
                        <span>Employee Case Management</span>
                    </a>
                </div>
            </details>
        @else
            <a class="menu__item {{ request()->routeIs('employee.records') ? 'is-active' : '' }}"
                href="{{ route('employee.records') }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path
                            d="M16 11c1.66 0 3-1.57 3-3.5S17.66 4 16 4s-3 1.57-3 3.5S14.34 11 16 11zM8 11c1.66 0 3-1.57 3-3.5S9.66 4 8 4 5 5.57 5 7.5 6.34 11 8 11zm0 2c-2.33 0-7 1.17-7 3.5V20h14v-3.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.94 1.97 3.45V20h7v-3.5c0-2.33-4.67-3.5-7-3.5z" />
                    </svg>
                </span>
                <span>EMPLOYEE<br />RECORDS</span>
            </a>
            <a class="menu__item {{ request()->routeIs('employee.cases') ? 'is-active' : '' }}"
                href="{{ route('employee.cases') }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path d="M14.2 2.6 20.4 8.8l-1.8 1.8-6.2-6.2 1.8-1.8z" />
                        <path d="M11.4 5.4 17.6 11.6l-2.2 2.2-6.2-6.2 2.2-2.2z" />
                        <path d="M8.8 8 15 14.2l-1.8 1.8L7 9.8 8.8 8z" />
                        <path d="m14.9 12.7 2.1-2.1 4.7 4.7a1.5 1.5 0 0 1-2.1 2.1l-4.7-4.7z" />
                        <path d="M2.4 18.2h8.4a1.3 1.3 0 0 1 0 2.6H2.4a1.3 1.3 0 0 1 0-2.6z" />
                    </svg>
                </span>
                <span>EMPLOYEE CASE<br />MANAGEMENT</span>
            </a>
        @endcan

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
                    <path d="M2 7a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v1h1a2 2 0 0 1 2 2v6a3 3 0 0 1-3 3H4a2 2 0 0 1-2-2V7zm16 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z" />
                </svg>
            </span>
            <span>LOANS</span>
        </a>

        @can('admin')
            <a class="menu__item {{ request()->routeIs('payroll.processing') ? 'is-active' : '' }}"
                href="{{ route('payroll.processing') }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path d="M12 8.25a3.75 3.75 0 1 0 0 7.5 3.75 3.75 0 0 0 0-7.5Zm8.79 3.16-1.35-.78c.08-.42.12-.85.12-1.28s-.04-.86-.12-1.28l1.35-.78a1 1 0 0 0 .37-1.37l-1.5-2.6a1 1 0 0 0-1.3-.4l-1.36.62a8.83 8.83 0 0 0-2.22-1.28L14.6 1.1A1 1 0 0 0 13.62.3h-3a1 1 0 0 0-.98.8l-.3 1.44a8.84 8.84 0 0 0-2.22 1.28l-1.36-.62a1 1 0 0 0-1.3.4l-1.5 2.6a1 1 0 0 0 .37 1.37l1.35.78A8.9 8.9 0 0 0 4.56 10c0 .43.04.86.12 1.28l-1.35.78a1 1 0 0 0-.37 1.37l1.5 2.6a1 1 0 0 0 1.3.4l1.36-.62c.68.54 1.43.96 2.22 1.28l.3 1.44a1 1 0 0 0 .98.8h3a1 1 0 0 0 .98-.8l.3-1.44a8.83 8.83 0 0 0 2.22-1.28l1.36.62a1 1 0 0 0 1.3-.4l1.5-2.6a1 1 0 0 0-.37-1.37Z" />
                    </svg>
                </span>
                <span>PAYROLL<br />PROCESSING</span>
            </a>
        @endcan

        @can('admin')
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
                    <a class="menu__parent-link" href="{{ route('payslip') }}">PAYSLIP</a>
                    <span class="menu__chevron" aria-hidden="true">
                        <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:currentColor;display:block;">
                            <path d="M7 10l5 5 5-5z" />
                        </svg>
                    </span>
                </summary>
                <div class="submenu">
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
                    <a class="menu__item menu__item--child {{ request()->routeIs('payslip.claims') ? 'is-active' : '' }}"
                        href="{{ route('payslip.claims') }}">
                        <span class="menu__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                                <path d="M17.4 2.6a2 2 0 0 1 2.8 0l1.2 1.2a2 2 0 0 1 0 2.8l-9.9 9.9a2 2 0 0 1-.9.5l-3.1.9a.5.5 0 0 1-.6-.6l.9-3.1a2 2 0 0 1 .5-.9l9.9-9.9z" />
                                <path d="M2.6 20.3c1.8-1.4 3.5-1.8 5.2-1.2 2 .7 3.8.8 5.4.2 1.4-.5 2.9-.5 4.5 0l2 .7-.6 1.8-2-.7c-1.1-.4-2.1-.4-3.1-.1-2.1.7-4.3.6-6.6-.3-1.1-.4-2.2-.1-3.3.8l-1.5-1.2z" />
                            </svg>
                        </span>
                        <span>Claims</span>
                    </a>
                </div>
            </details>
        @else
            <a class="menu__item {{ request()->routeIs('payslip.claims') ? 'is-active' : '' }}"
                href="{{ route('payslip.claims') }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                        <path d="M17.4 2.6a2 2 0 0 1 2.8 0l1.2 1.2a2 2 0 0 1 0 2.8l-9.9 9.9a2 2 0 0 1-.9.5l-3.1.9a.5.5 0 0 1-.6-.6l.9-3.1a2 2 0 0 1 .5-.9l9.9-9.9z" />
                        <path d="M2.6 20.3c1.8-1.4 3.5-1.8 5.2-1.2 2 .7 3.8.8 5.4.2 1.4-.5 2.9-.5 4.5 0l2 .7-.6 1.8-2-.7c-1.1-.4-2.1-.4-3.1-.1-2.1.7-4.3.6-6.6-.3-1.1-.4-2.2-.1-3.3.8l-1.5-1.2z" />
                    </svg>
                </span>
                <span>PAYSLIP CLAIMS</span>
            </a>
        @endcan

        @can('admin')
            <a class="menu__item {{ request()->routeIs('report') ? 'is-active' : '' }}"
                href="{{ route('report') }}">
                <span class="menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="ico">
                        <path d="M11 3a9 9 0 1 0 9 9h-9V3z" />
                        <path d="M13 3.4A8.6 8.6 0 0 1 20.6 11H13V3.4z" />
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
