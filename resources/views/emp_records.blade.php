<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll System | Employee Records</title>

    @vite(['resources/css/emp_records.css', 'resources/js/emp_records.js'])
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
                <a class="menu__item" href="{{ route('index') }}">
                    <span class="menu__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="ico" aria-hidden="true">
                            <path d="M3 3h8v8H3V3zm10 0h8v5h-8V3zM3 13h8v8H3v-8zm10 7v-10h8v10h-8z" />
                        </svg>
                    </span>
                    <span>DASHBOARD</span>
                </a>

                <a class="menu__item {{ request()->routeIs('employee.records') ? 'is-active' : '' }}"
                    href="{{ route('employee.records') }}">
                    <span class="menu__icon" aria-hidden="true">
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
                            <a href="{{ url('/logout') }}" class="user-dropdown__item" role="menuitem">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <section class="content">
                <div class="headline">
                    <div>
                        <h1>EMPLOYEE RECORDS</h1>
                        <p class="muted">Manage employee profiles, assignment, and payroll-related info.</p>
                    </div>
                </div>

                <div class="toolbar">
                    <div class="filtersCard">
                        <div class="filtersRow">
                            <div class="field field--grow">
                                <label class="field__label">Search</label>
                                <input id="searchInput" class="field__control" type="search"
                                    placeholder="Search employee id or name" />
                            </div>
                            <div class="field">
                                <label class="field__label">Department</label>
                                <select id="deptFilter" class="field__control">
                                    <option value="All" selected>All</option>
                                    <option value="Admin">Admin</option>
                                    <option value="HR">HR</option>
                                    <option value="IT">IT</option>
                                    <option value="Finance">Finance</option>
                                </select>
                            </div>
                            <div class="field field--status">
                                <label class="field__label">Status</label>
                                <select id="statusFilter" class="field__control">
                                    <option value="All" selected>All</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                    <option value="Resigned">Resigned</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLE CARD -->
                <section class="card tablecard">
                    <div class="tablecard__head">
                        <div>
                            <div class="card__title">Employee List</div>
                            <div class="muted small" id="resultsMeta">Showing 0 employees</div>
                        </div>
                        <div class="actionsTop">
                            <button class="btn btn--soft" type="button" id="exportBtn">? EXPORT</button>
                            <button class="btn btn--soft" type="button" id="importBtn">? IMPORT</button>
                            <button class="btn btn--maroon" type="button" id="openAddBtn">+ ADD EMPLOYEE</button>
                            <input id="importFile" type="file" accept=".csv,.xlsx" hidden />
                            <div class="bulk" id="bulkBarEmp" aria-hidden="true" style="display:none">
                                <span class="bulk__text"><span id="selectedCountEmp">0</span> selected</span>
                                <button class="btn btn--soft" type="button" id="bulkDeleteEmpBtn">Delete
                                    Selected</button>

                                <select class="bulkSelect" id="bulkAssignSelect" aria-label="Set assignment">
                                    <option value="">Set assignment...</option>
                                    <option value="Tagum">Tagum</option>
                                    <option value="Davao">Davao</option>
                                    <option value="Area">Area</option>
                                </select>

                                <button class="btn btn--maroon" type="button" id="bulkAssignApply"
                                    disabled>Apply</button>
                            </div>
                        </div>
                    </div>

                    <div class="tablewrap">
                        <table class="table" id="empTable">
                            <thead>
                                <tr>
                                    <th class="col-check">
                                        <input type="checkbox" id="selectAll" aria-label="Select all" />
                                    </th>

                                    <th class="sortable" data-sort="empId">Emp ID <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="name">Name <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="dept">Department <span class="sortIcon"
                                            aria-hidden="true"></span>
                                    </th>
                                    <th class="sortable" data-sort="position">Position <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="type">Employment Type <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="assignment">Assignment <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="rate">Rate/Salary <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th>Gov’t IDs</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody id="empTbody">
                                <!-- Injected by JS -->
                            </tbody>
                        </table>
                    </div>

                    <!-- ✅ Pagination footer -->
                    <div class="tableFooter">
                        <div class="tableFooter__left" id="pageMeta">Page 1 of 1</div>

                        <div class="tableFooter__right">
                            <label class="toolLabel" for="pageSize">Rows:</label>
                            <select id="pageSize" class="toolSelect">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                            </select>

                            <div class="pager">
                                <button class="pagerBtn" type="button" id="firstPage"
                                    aria-label="First page">|◀</button>
                                <button class="pagerBtn" type="button" id="prevPage"
                                    aria-label="Previous page">◀</button>

                                <div class="pagerMid">
                                    <input id="pageInput" type="number" min="1" value="1" />
                                    <span class="muted small">/</span>
                                    <span id="totalPages" class="small">1</span>
                                </div>

                                <button class="pagerBtn" type="button" id="nextPage"
                                    aria-label="Next page">▶</button>
                                <button class="pagerBtn" type="button" id="lastPage"
                                    aria-label="Last page">▶|</button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- DRAWER (ADD/EDIT) - kept from your structure -->
                <aside class="drawer" id="drawer" aria-hidden="true">
                    <div class="drawer__overlay" id="drawerOverlay"></div>

                    <div class="drawer__panel" role="dialog" aria-modal="true" aria-labelledby="drawerTitle">
                        <div class="drawer__head">
                            <div>
                                <div class="drawer__title" id="drawerTitle">Employee Details</div>
                                <div class="muted small" id="drawerSubtitle">View and manage employee record.</div>
                            </div>
                            <button class="iconbtn" id="closeDrawerBtn" type="button" aria-label="Close">✕</button>
                        </div>

                        <form class="form" id="empForm">
                            <div class="sectionTitle">Basic Information</div>
                            <div class="grid2">
                                <div class="field">
                                    <label>Employee ID *</label>
                                    <input type="text" id="f_empId" required placeholder="e.g. 1044" />
                                </div>

                                <div class="field">
                                    <label>Status</label>
                                    <select id="f_status">
                                        <option>Active</option>
                                        <option>Inactive</option>
                                        <option>Resigned</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label>First Name *</label>
                                    <input type="text" id="f_first" required placeholder="First name" />
                                </div>

                                <div class="field">
                                    <label>Last Name *</label>
                                    <input type="text" id="f_last" required placeholder="Last name" />
                                </div>

                                <div class="field">
                                    <label>Middle Name</label>
                                    <input type="text" id="f_middle" placeholder="Middle name (optional)" />
                                </div>

                                <div class="field">
                                    <label>Birthday</label>
                                    <input type="date" id="f_bday" />
                                </div>
                            </div>

                            <div class="sectionTitle">Contact & Address</div>
                            <div class="grid2">
                                <div class="field">
                                    <label>Mobile No.</label>
                                    <input type="text" id="f_mobile" placeholder="09xx..." />
                                </div>
                                <div class="field">
                                    <label>Email</label>
                                    <input type="email" id="f_email" placeholder="name@email.com" />
                                </div>
                                <div class="field field--full">
                                    <label>Address</label>
                                    <input type="text" id="f_address" placeholder="Complete address" />
                                </div>
                            </div>

                            <div class="sectionTitle">Employment Details</div>
                            <div class="grid2">
                                <div class="field">
                                    <label>Department *</label>
                                    <select id="f_dept" required>
                                        <option value="">Select department</option>
                                        <option>Admin</option>
                                        <option>HR</option>
                                        <option>IT</option>
                                        <option>Finance</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label>Position *</label>
                                    <input type="text" id="f_position" required placeholder="e.g. Assistant" />
                                </div>

                                <div class="field">
                                    <label>Employment Type</label>
                                    <select id="f_type">
                                        <option>Regular</option>
                                        <option>Contractual</option>
                                        <option>Probationary</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label>Date Hired</label>
                                    <input type="date" id="f_hired" />
                                </div>

                                <div class="field">
                                    <label>Pay Type</label>
                                    <select id="f_payType">
                                        <option>Monthly</option>
                                        <option>Daily</option>
                                        <option>Hourly</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label>Basic Pay / Salary *</label>
                                    <input type="number" id="f_rate" required placeholder="e.g. 12000"
                                        min="0" />
                                </div>
                            </div>

                            <div class="sectionTitle">Assignment</div>
                            <div class="grid2">
                                <div class="field">
                                    <label>Assignment Type</label>
                                    <select id="f_assignmentType" name="assignmentType" required>
                                        <option value="Davao">Davao</option>
                                        <option value="Tagum">Tagum</option>
                                        <option value="Area">Area</option>
                                    </select>
                                </div>

                                <div class="field" id="areaPlaceWrap" style="display:none;">
                                    <label>Location (Area Place)</label>
                                    <select id="f_areaPlace" name="areaPlace" disabled></select>
                                </div>
                            </div>

                            <div class="sectionTitle">Cash Advance</div>
                            <div class="grid2">
                                <div class="field">
                                    <label>Cash Advance Eligibility</label>
                                    <input type="text" id="f_caEligible" readonly />
                                </div>
                                <div class="field">
                                    <label>Max Cash Advance (2 months salary)</label>
                                    <input type="text" id="f_caMax" readonly />
                                </div>
                            </div>

                            <div class="sectionTitle">Government IDs</div>
                            <div class="grid2">
                                <div class="field">
                                    <label>SSS No.</label>
                                    <input type="text" id="f_sss" placeholder="SSS number" />
                                </div>
                                <div class="field">
                                    <label>PhilHealth No.</label>
                                    <input type="text" id="f_ph" placeholder="PhilHealth number" />
                                </div>
                                <div class="field">
                                    <label>Pag-IBIG No.</label>
                                    <input type="text" id="f_pagibig" placeholder="Pag-IBIG number" />
                                </div>
                                <div class="field">
                                    <label>TIN</label>
                                    <input type="text" id="f_tin" placeholder="Tax Identification No." />
                                </div>
                            </div>

                            <div class="form__actions">
                                <button class="btn btn--soft" type="button" id="deleteBtn">Delete</button>
                                <div class="spacer"></div>
                                <button class="btn btn--soft" type="button" id="cancelBtn">Cancel</button>
                                <button class="btn btn--maroon" type="submit" id="saveBtn">Save</button>
                            </div>
                        </form>
                    </div>
                </aside>

            </section>
        </main>
    </div>
</body>

</html>
