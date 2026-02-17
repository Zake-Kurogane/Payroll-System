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

    <nav class="menu">
        <a class="menu__item {{ request()->routeIs('index') ? 'is-active' : '' }}" href="{{ route('index') }}">
            <span class="menu__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="ico">
                    <path d="M3 3h8v8H3V3zm10 0h8v5h-8V3zM3 13h8v8H3v-8zm10 7v-10h8v10h-8z" />
                </svg>
            </span>
            <span>DASHBOARD</span>
        </a>

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

        <a class="menu__item {{ request()->routeIs('payslip') ? 'is-active' : '' }}"
            href="{{ route('payslip') }}">
            <span class="menu__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="ico">
                    <path d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1v5h5" />
                    <path d="M7 12h10v2H7v-2zm0 4h10v2H7v-2z" />
                </svg>
            </span>
            <span>PAYSLIP</span>
        </a>

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
    </nav>

    <div class="side__footer">
        <div class="time" id="clock">--:-- --</div>
        <div class="date" id="date">--/--/----</div>
    </div>
</aside>
