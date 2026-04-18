@extends('layouts.app')

@section('title', 'Employee Records')

@section('vite')
    @vite(['resources/css/emp_records.css', 'resources/js/emp_records.js'])
@endsection

@section('content')
    <section class="content">
        <div class="headline">
            <div>
                <h1>EMPLOYEE RECORDS</h1>
                <p class="muted">Manage employee profiles, assignment, and payroll-related info.</p>
            </div>
        </div>

        <div class="toolbar">
            <form class="filtersCard" method="GET" action="{{ route('employee.records') }}">
                <div class="filtersRow">
                    <div class="field field--grow">
                        <label class="field__label">Search</label>
                        <input id="searchInput" name="q" class="field__control" type="search"
                            value="{{ request('q') }}" placeholder="Search employee id or name" />
                    </div>
                    <div class="field">
                        <label class="field__label">Department</label>
                        <select id="deptFilter" name="department">
                            <option value="">All</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept }}" @selected(request('department') == $dept)>{{ $dept }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field field--status">
                        <label class="field__label">Status</label>
                        <select id="statusFilter" name="status">
                            <option value="">All</option>
                            @foreach ($statuses as $s)
                                <option value="{{ $s->id }}" @selected(request('status') == $s->id)>{{ $s->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field field--assign-group">
                        <label class="field__label">Assignment</label>
                        <div class="seg seg--pill" id="assignSeg" role="group" aria-label="Filter by assignment">
                            <button type="button" class="seg__btn seg__btn--emp is-active" data-assign="">All</button>
                            @foreach ($assignments as $a)
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
            </form>
        </div>

        <!-- TABLE CARD -->
        <section class="card tablecard">
            <div class="tablecard__head">
                <div>
                    <div class="card__title">Employee List</div>
                    <div class="muted small" id="resultsMeta">
                        Showing {{ $employees->count() }} of {{ $employees->total() }} employees
                    </div>
                </div>
                <div class="actionsTop">
                    <div class="bulk" id="bulkBarEmp" aria-hidden="true" style="display:none">
                        <span class="bulk__text"><span id="selectedCountEmp">0</span> selected</span>
                        @can('admin')
                            <button class="btn btn--soft" type="button" id="bulkDeleteEmpBtn">Delete
                                Selected</button>
                        @endcan

                        <select class="bulkSelect" id="bulkAssignSelect" aria-label="Set assignment">
                            <option value="">Set assignment...</option>
                            @foreach ($assignments as $a)
                                <option value="{{ $a }}">{{ $a }}</option>
                            @endforeach
                        </select>

                        <select class="bulkSelect" id="bulkAreaPlaceSelect" aria-label="Set area place"
                            style="display:none">
                            <option value="">Set area place...</option>
                        </select>

                        <button class="btn btn--maroon" type="button" id="bulkAssignApply" disabled>Apply</button>
                    </div>
                    <button class="btn btn--soft btn--icon" type="button" id="exportBtn">
                        <svg class="btn__icon btn__icon--solid" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M5 18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3h-4v2H9v-2H5v3z" />
                            <path d="M12 4l5 5h-3v6h-4V9H7l5-5z" />
                        </svg>
                        EXPORT
                    </button>
                    <button class="btn btn--maroon" type="button" id="openAddBtn">+ ADD EMPLOYEE</button>
                </div>
            </div>

            <div class="tablewrap">
                <table class="table" id="empTable" data-has-comp="{{ Gate::allows('viewCompensation') ? '1' : '0' }}">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" id="selectAll" aria-label="Select all" />
                            </th>
                            <th class="sortable" data-sort="empId">Emp ID <span class="sortIcon" aria-hidden="true"></span>
                            </th>
                            <th class="sortable" data-sort="name">Name <span class="sortIcon" aria-hidden="true"></span>
                            </th>
                            <th class="sortable" data-sort="dept">Department <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            <th class="sortable" data-sort="position">Position <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            <th class="sortable" data-sort="assignment">Assignment <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            @can('viewCompensation')
                                <th class="sortable col-basicpay" data-sort="basicPay">Basic Pay <span class="sortIcon"
                                        aria-hidden="true"></span></th>
                                <th class="sortable" data-sort="allowance">P/A <span class="sortIcon"
                                        aria-hidden="true"></span></th>
                                <th class="sortable col-salary" data-sort="salary">Total Salary <span class="sortIcon"
                                        aria-hidden="true"></span></th>
                            @endcan
                            <th>Payroll Required</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="empTbody">
                        @forelse($employees as $emp)
                            <tr>
                                <td class="col-check">
                                    <input class="empCheck" type="checkbox" data-id="{{ $emp->emp_no }}"
                                        aria-label="Select employee {{ $emp->emp_no }}">
                                </td>
                                <td>{{ $emp->emp_no }}</td>
                                <td>{{ $emp->last_name }},
                                    {{ $emp->first_name }}{{ $emp->middle_name ? ' ' . $emp->middle_name : '' }}</td>
                                <td>{{ $emp->department ?: '-' }}</td>
                                <td>{{ $emp->position ?: '-' }}</td>
                                <td>
                                    @php
                                        $assign = $emp->assignment_type ?: '';
                                        $isRegularField =
                                            $assign === 'Field' &&
                                            strtolower(trim((string) ($emp->employment_type ?? ''))) === 'regular';
                                        $assignText = null;
                                        if ($emp->area_place) {
                                            $assignText = "{$assign} ({$emp->area_place})";
                                        } else {
                                            $assignText = $assign;
                                        }
                                    @endphp
                                    {{ $assignText ?? null ?: '-' }}
                                </td>
                                @can('viewCompensation')
                                    <td class="col-basicpay">
                                        <div class="salaryVal">&#8369; {{ number_format($emp->basic_pay ?? 0) }}</div>
                                    </td>
                                    <td>
                                        <div class="salaryVal">&#8369; {{ number_format($emp->allowance ?? 0) }}</div>
                                    </td>
                                    <td class="col-salary">
                                        <div class="salaryVal">&#8369;
                                            {{ number_format(($emp->basic_pay ?? 0) + ($emp->allowance ?? 0)) }}</div>
                                    </td>
                                @endcan
                                <td>
                                    @php
                                        $missing = [];
                                        if (Gate::allows('viewCompensation')) {
                                            $basicPay = (float) ($emp->basic_pay ?? 0);
                                            if ($basicPay <= 0) {
                                                $missing[] = 'Basic Pay';
                                            }
                                        }
                                        $assignType = trim((string) ($emp->assignment_type ?? ''));
                                        if ($assignType === '') {
                                            $missing[] = 'Assignment';
                                        }
                                        $needsAreaPlace = !empty($groupedAreaPlaces[$assignType] ?? []);
                                        if (
                                            $assignType !== '' &&
                                            $needsAreaPlace &&
                                            trim((string) ($emp->area_place ?? '')) === ''
                                        ) {
                                            $missing[] = 'Area Place';
                                        }
                                        $isRegularField =
                                            $assignType === 'Field' &&
                                            strtolower(trim((string) ($emp->employment_type ?? ''))) === 'regular';
                                        if ($isRegularField && trim((string) ($emp->external_area ?? '')) === '') {
                                            $missing[] = 'External Area';
                                        }
                                        $govRequired = ['sss', 'philhealth', 'pagibig', 'tin'];
                                        $missingGov = array_filter(
                                            $govRequired,
                                            fn($k) => trim((string) ($emp->{$k} ?? '')) === '',
                                        );
                                        if (count($missingGov)) {
                                            $missing[] = 'Gov IDs';
                                        }
                                    @endphp
                                    @if (count($missing) === 0)
                                        <span class="badge badge--ok">&#10003; Complete</span>
                                    @else
                                        <div class="badges">
                                            @foreach ($missing as $m)
                                                <span
                                                    class="badge {{ in_array($m, ['Gov IDs', 'Basic Pay']) ? 'badge--bad' : 'badge--warn' }}">
                                                    &#9888; Missing {{ $m }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="actions">
                                    <div class="actionsRow">
                                        <button class="iconbtn" type="button" data-action="edit"
                                            data-id="{{ $emp->emp_no }}" title="Edit" aria-label="Edit">
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                            </svg>
                                        </button>
                                        <button class="iconbtn" type="button" data-action="attendance-year"
                                            data-id="{{ $emp->emp_no }}" title="Attendance Year"
                                            aria-label="Attendance Year">
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                            </svg>
                                        </button>
                                        @if ($emp->assignment_type === 'Field')
                                            <button class="iconbtn" type="button" data-action="history"
                                                data-id="{{ $emp->emp_no }}" title="Area History"
                                                aria-label="Area History">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <circle cx="12" cy="12" r="9"></circle>
                                                    <path d="M12 7v5l3 3"></path>
                                                </svg>
                                            </button>
                                        @endif
                                        @can('admin')
                                            <button class="iconbtn" type="button" data-action="delete"
                                                data-id="{{ $emp->emp_no }}" title="Delete" aria-label="Delete">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M3 6h18"></path>
                                                    <path d="M8 6V4h8v2"></path>
                                                    <path d="M19 6l-1 14H6L5 6"></path>
                                                    <path d="M10 11v6"></path>
                                                    <path d="M14 11v6"></path>
                                                </svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                @php
                                    $colspan = Gate::allows('viewCompensation') ? 11 : 8;
                                @endphp
                                <td colspan="{{ $colspan }}">No employees found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- ? Pagination footer -->
            <div class="tableFooter">
                <div class="tableFooter__left">
                    Page {{ $employees->currentPage() }} of {{ $employees->lastPage() }}
                </div>

                <div class="tableFooter__right">
                    <label class="rowsLbl" for="rowsPerPage">Rows:</label>
                    <select class="rowsSelect" id="rowsPerPage">
                        <option value="10" @selected((int) request('rows', 20) === 10)>10</option>
                        <option value="20" @selected((int) request('rows', 20) === 20)>20</option>
                        <option value="50" @selected((int) request('rows', 20) === 50)>50</option>
                        <option value="100" @selected((int) request('rows', 20) === 100)>100</option>
                    </select>
                    <div class="pager">
                        @php
                            $firstUrl = $employees->url(1);
                            $prevUrl = $employees->previousPageUrl();
                            $nextUrl = $employees->nextPageUrl();
                            $lastUrl = $employees->url($employees->lastPage());
                            $isFirst = $employees->currentPage() <= 1;
                            $isLast = $employees->currentPage() >= $employees->lastPage();
                        @endphp
                        <a class="pagerBtn {{ $isFirst ? 'is-disabled' : '' }}" href="{{ $isFirst ? '#' : $firstUrl }}"
                            aria-label="First page" aria-disabled="{{ $isFirst ? 'true' : 'false' }}">
                            &#124;&#9664;
                        </a>
                        <a class="pagerBtn {{ $isFirst ? 'is-disabled' : '' }}" href="{{ $isFirst ? '#' : $prevUrl }}"
                            aria-label="Previous page" aria-disabled="{{ $isFirst ? 'true' : 'false' }}">
                            &#9664;
                        </a>

                        <div class="pagerMid">
                            <input class="pagerInput" id="pageInput" type="number" min="1"
                                value="{{ $employees->currentPage() }}" readonly aria-label="Current page" />
                            <span class="small" id="pageTotal">/ {{ $employees->lastPage() }}</span>
                        </div>

                        <a class="pagerBtn {{ $isLast ? 'is-disabled' : '' }}" href="{{ $isLast ? '#' : $nextUrl }}"
                            aria-label="Next page" aria-disabled="{{ $isLast ? 'true' : 'false' }}">
                            &#9654;
                        </a>
                        <a class="pagerBtn {{ $isLast ? 'is-disabled' : '' }}" href="{{ $isLast ? '#' : $lastUrl }}"
                            aria-label="Last page" aria-disabled="{{ $isLast ? 'true' : 'false' }}">
                            &#9654;&#124;
                        </a>
                    </div>
                </div>
            </div>
            </div>
        </section>

        <script>
            window.__serverRender = true;
            window.__serverEmployees = @json($employees->items());
            window.__areaPlaces = @json($groupedAreaPlaces);
            window.__canViewCompensation = @json(Gate::allows('viewCompensation'));
            window.__canDeleteEmployee = @json(Gate::allows('admin'));
            window.__positions = @json($positions ?? []);
            window.__externalPositions = @json($externalPositions ?? []);
        </script>

        <div class="toast" id="toast" aria-live="polite" aria-atomic="true"></div>

        <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

        <!-- DRAWER -->
        <div class="overlay" id="drawerOverlay" hidden></div>
        <aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle"
            aria-hidden="true">
            <div class="drawer__head">
                <div>
                    <div class="drawer__title" id="drawerTitle">Employee Details</div>
                    <div class="drawer__sub" id="drawerSubtitle">View and manage employee record.</div>
                </div>
                <button class="iconbtn" id="closeDrawerBtn" type="button" aria-label="Close">✕</button>
            </div>

            <form class="form" id="empForm">
                <div class="sectionTitle">Basic Information</div>
                <div class="grid2">
                    <div class="field">
                        <label>Employee ID</label>
                        <input type="text" id="f_empId" required placeholder="Auto-generated" inputmode="numeric"
                            pattern="\d{4}" maxlength="4" readonly
                            style="background:var(--surface-2,#f5f5f5);cursor:default;" />
                        <small class="err" id="errEmpId"></small>
                    </div>

                    <div class="field">
                        <label>Status</label>
                        <select id="f_status">
                            @foreach ($statuses as $s)
                                <option value="{{ $s->id }}">{{ $s->label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>First Name *</label>
                        <input type="text" id="f_first" required placeholder="First name" />
                        <small class="err" id="errFirst"></small>
                    </div>

                    <div class="field">
                        <label>Last Name *</label>
                        <input type="text" id="f_last" required placeholder="Last name" />
                        <small class="err" id="errLast"></small>
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
                        <input type="text" id="f_mobile" placeholder="09xx-xxx-xxxx" inputmode="numeric"
                            pattern="^[0-9]{4}-[0-9]{3}-[0-9]{4}$" />
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input type="email" id="f_email" placeholder="name@email.com" />
                    </div>
                    <div class="field">
                        <label>Province</label>
                        <select id="f_addrProvince">
                            <option value="">— Select Province —</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>City / Municipality</label>
                        <select id="f_addrCity" disabled>
                            <option value="">— Select City / Municipality —</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Barangay</label>
                        <select id="f_addrBarangay" disabled>
                            <option value="">— Select Barangay —</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Street / House No.</label>
                        <input type="text" id="f_addrStreet" placeholder="e.g. 123 Rizal St." />
                    </div>
                </div>

                <div class="sectionTitle">Employment Details</div>
                <div class="grid2">
                    <div class="field">
                        <label>Department</label>
                        <select id="f_dept" name="department">
                            <option value="">-- Select --</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept }}">{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Based Location</label>
                        <select id="f_basedLocation" name="basedLocation">
                            <option value="">-- Select --</option>
                            @foreach ($basedLocations ?? [] as $loc)
                                <option value="{{ $loc }}">{{ $loc }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Positions *</label>
                        <div class="ddcheck" id="posDd">
                            <button type="button" class="ddcheck__btn" id="posDdBtn" aria-haspopup="listbox"
                                aria-expanded="false">
                                Select position(s)
                            </button>
                            <div class="ddcheck__panel" id="posDdPanel" hidden>
                                <input type="text" class="ddcheck__search" id="posSearch"
                                    placeholder="Type to search..." autocomplete="off" />
                                <div class="ddcheck__list" id="posDdList"></div>
                            </div>
                        </div>
                        <small class="err" id="errPositions"></small>
                    </div>

                    <div class="field">
                        <label>Employment Type</label>
                        <select id="f_type">
                            <option value="">-- Select --</option>
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

                    <div id="plInfoWrap" class="plField" style="display:none;">
                        <div class="plField__label">PL ALLOWANCE</div>
                        <div class="plField__pill">
                            <span id="f_plCount"></span> <span class="plField__unit">days</span>
                        </div>
                        <div id="f_plBadge" class="plField__pillMeta"></div>
                    </div>

                </div>

                @can('viewCompensation')
                    <div class="sectionTitle">Compensation</div>
                    <div class="grid2">
                        <div class="field">
                            <label>Basic Pay (monthly) *</label>



































                            <input type="number" id="f_rate" required placeholder="e.g. 20000" min="0" />
                            <small class="err" id="errRate"></small>
                        </div>

                        <div class="field">
                            <label>Allowance (monthly)</label>
                            <input type="number" id="f_allowance" placeholder="e.g. 1500" min="0" />
                            <small class="err" id="errAllowance"></small>
                        </div>

                        <div class="field">
                            <label>Total Salary (auto)</label>
                            <input type="text" id="f_totalSalary" readonly />
                        </div>
                    </div>
                @endcan

                <div class="sectionTitle">Assignment</div>
                <div class="grid2">
                    <div class="field">
                        <label>Assignment Type</label>
                        <select id="f_assignmentType" name="assignmentType" required>
                            @foreach ($assignments as $a)
                                <option value="{{ $a }}">{{ $a }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field" id="areaPlaceWrap" style="display:none;">
                        <label>Location (Area Place)</label>
                        <select id="f_areaPlace" name="areaPlace" disabled>
                            <option value="">-- Select area place --</option>
                            @foreach ($groupedAreaPlaces as $group => $places)
                                @foreach ($places as $ap)
                                    <option value="{{ $ap }}">{{ $ap }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>

                    <div class="field" id="externalAreaWrap" style="display:none;">
                        <label>External Area <span class="hint">(fixed deduction attribution)</span></label>
                        <select id="f_externalArea" name="externalArea" disabled>
                            <option value="">-- Select external area --</option>
                            @foreach ($groupedAreaPlaces as $group => $places)
                                @foreach ($places as $ap)
                                    <option value="{{ $ap }}">{{ $ap }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        <small class="err" id="errExternalArea"></small>
                    </div>

                    <div class="field" id="externalPositionWrap" style="display:none;">
                        <label>External Position</label>
                        <select id="f_externalPosition" name="externalPosition" disabled>
                            <option value="">-- Select external position --</option>
                            @foreach ($externalPositions ?? [] as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <small class="err" id="errExternalPosition"></small>
                    </div>
                </div>

                <div class="sectionTitle">Government IDs</div>
                <div class="grid2">
                    <div class="field">
                        <label>SSS No.</label>
                        <input type="text" id="f_sss" placeholder="00-0000000-0" inputmode="numeric"
                            pattern="^[0-9]{2}-[0-9]{7}-[0-9]{1}$" />
                    </div>
                    <div class="field">
                        <label>PhilHealth No.</label>
                        <input type="text" id="f_ph" placeholder="00-000000000-0" inputmode="numeric"
                            pattern="^[0-9]{2}-[0-9]{9}-[0-9]{1}$" />
                    </div>
                    <div class="field">
                        <label>Pag-IBIG No.</label>
                        <input type="text" id="f_pagibig" placeholder="0000-0000-0000" inputmode="numeric"
                            pattern="^[0-9]{4}-[0-9]{4}-[0-9]{4}$" />
                    </div>
                    <div class="field">
                        <label>TIN</label>
                        <input type="text" id="f_tin" placeholder="000-000-000-000" inputmode="numeric"
                            pattern="^[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}$" />
                    </div>
                </div>

                <div class="sectionTitle">Bank Details</div>
                <div class="grid2">
                    <div class="field">
                        <label>Bank Name</label>
                        <input type="text" id="f_bankName" placeholder="e.g. BDO" />
                        <small class="err" id="errBankName"></small>
                    </div>
                    <div class="field">
                        <label>Account Name</label>
                        <input type="text" id="f_accountName" placeholder="e.g. Juan Dela Cruz" />
                        <small class="err" id="errAccountName"></small>
                    </div>
                    <div class="field field--full">
                        <label>Account Number</label>
                        <input type="text" id="f_accountNumber" placeholder="0000-0000-0000-0000" inputmode="numeric"
                            pattern="^[0-9]{4}(-[0-9]{4}){2,4}$" />
                        <div class="hint">Leave blank to pay by cash.</div>
                        <small class="err" id="errAccountNumber"></small>
                    </div>
                    <div class="field field--full">
                        <label>Payout Method (auto)</label>
                        <input type="text" id="f_payoutMethod" readonly />
                    </div>
                </div>

                @if (false)
                    <!-- Add Charge Form (hidden by default) -->
                    <div id="chargeFormWrap"
                        style="display:none; margin-top:10px; padding:12px; border:1px solid var(--line); border-radius:6px;">
                        <div class="sectionTitle" style="margin-top:0;">New Charge / Shortage</div>
                        <div class="grid2">
                            <div class="field">
                                <label>Type</label>
                                <select id="cf_type">
                                    <option value="shortage">Shortage</option>
                                    <option value="charge">Charge</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Amount (Total)</label>
                                <input type="number" id="cf_amount" min="0.01" step="0.01"
                                    placeholder="e.g. 3000" />
                            </div>
                            <div class="field field--full">
                                <label>Description / Notes</label>
                                <input type="text" id="cf_description" placeholder="Reason or details (optional)" />
                            </div>
                            <div class="field">
                                <label>Payment Plan</label>
                                <select id="cf_planType">
                                    <option value="one_time">One-time (next cutoff)</option>
                                    <option value="installment">Installment</option>
                                </select>
                            </div>
                            <div class="field" id="cf_installmentWrap" style="display:none;">
                                <label>No. of Cutoffs</label>
                                <input type="number" id="cf_installmentCount" min="2" max="24"
                                    placeholder="e.g. 3" />
                            </div>
                            <div class="field">
                                <label>Start Month</label>
                                <input type="month" id="cf_startMonth" />
                            </div>
                            <div class="field">
                                <label>Start Cutoff</label>
                                <select id="cf_startCutoff">
                                    <option value="11-25">11–25</option>
                                    <option value="26-10">26–10</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; margin-top:10px;">
                            <button class="btn btn--soft" type="button" id="cancelChargeBtn">Cancel</button>
                            <button class="btn btn--maroon" type="button" id="saveChargeBtn">Save</button>
                        </div>
                    </div>

                    <div class="sectionTitle">Attendance Tardiness (view only)</div>
                    <div class="tardyGrid">
                        <div class="metricCard">
                            <div class="metricCard__label">This Month</div>
                            <div class="metricCard__value" id="tardyMonth">â€”</div>
                            <div class="metricCard__sub">minutes late</div>
                        </div>
                        <div class="metricCard">
                            <div class="metricCard__label">This Year</div>
                            <div class="metricCard__value" id="tardyYear">â€”</div>
                            <div class="metricCard__sub">minutes late</div>
                        </div>
                        <div class="metricCard metricCard--wide">
                            <div class="metricCard__label">All Time</div>
                            <div class="metricCard__value" id="tardyTotal">â€”</div>
                            <div class="metricCard__sub" id="tardyLateDays">â€” late days</div>
                        </div>
                    </div>

                    <div class="sectionTitle">Memos / Sanctions / NTE</div>
                    <div class="tablewrap tablewrap--preview" style="margin-bottom:6px;">
                        <table class="table table--preview" id="disciplineTable">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="disciplineTbody">
                                <tr>
                                    <td colspan="4" class="muted small">No records yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="muted tiny" id="disciplineHint">Import via the "IMPORT DISCIPLINE" button (columns:
                        emp_no,
                        type, date, remarks, reference).</div>
                @endif

                <div class="form__actions">
                    @can('admin')
                        <button class="btn btn--soft" type="button" id="deleteBtn">Delete</button>
                    @endcan
                    <div class="spacer"></div>
                    <button class="btn btn--soft" type="button" id="cancelBtn">Cancel</button>
                    <button class="btn btn--maroon" type="submit" id="saveBtn">Save</button>
                </div>
            </form>
        </aside>

        <!-- ATTENDANCE YEAR DRAWER -->
        <div class="overlay" id="attendanceYearOverlay" hidden></div>
        <aside class="drawer drawer--wide" id="attendanceYearDrawer" role="dialog" aria-modal="true"
            aria-labelledby="attendanceYearTitle" aria-hidden="true">
            <div class="drawer__head">
                <div>
                    <div class="drawer__title" id="attendanceYearTitle">Yearly Attendance</div>
                    <div class="muted small" id="attendanceYearSubtitle"></div>
                </div>
                <button class="iconbtn" id="closeAttendanceYearBtn" type="button" aria-label="Close">x</button>
            </div>
            <div class="form">
                <div class="attendanceYearToolbar">
                    <div class="field">
                        <label for="attendanceYearSelect">Year</label>
                        <select id="attendanceYearSelect"></select>
                    </div>
                    <div class="attendanceYearPl" id="attendanceYearPl"></div>
                </div>

                <div class="attendanceYearTotals" id="attendanceYearTotals"></div>
                <div class="attendanceYearLegend" id="attendanceYearLegend"></div>
                <div class="attendanceYearGrid" id="attendanceYearGrid"></div>
                <div class="attendanceYearTrace" id="attendanceYearTrace"></div>
            </div>
        </aside>

        <!-- AREA HISTORY DRAWER -->
        <div class="overlay" id="historyDrawerOverlay" hidden></div>
        <aside class="drawer drawer--narrow" id="historyDrawer" role="dialog" aria-modal="true"
            aria-labelledby="historyDrawerTitle" aria-hidden="true">
            <div class="drawer__head">
                <div>
                    <div class="drawer__title" id="historyDrawerTitle">Area Assignment History</div>
                    <div class="muted small" id="historyDrawerSubtitle"></div>
                </div>
                <button class="iconbtn" id="closeHistoryDrawerBtn" type="button" aria-label="Close">✕</button>
            </div>
            <div class="form">
                <div id="historyDrawerExternal" class="muted small" style="margin-bottom:10px;display:none;"></div>
                <div class="field historySearchField">
                    <input type="search" id="historySearch" class="field__control" placeholder="Search area or period…"
                        autocomplete="off" />
                </div>
                <div class="tablewrap tablewrap--preview">
                    <table class="table table--preview" aria-label="Area assignment history table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Area</th>
                            </tr>
                        </thead>
                        <tbody id="areaHistoryList">
                            <tr>
                                <td colspan="2" class="muted small">No history yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>

    </section>

    <style>
        .drawer--narrow {
            width: min(360px, 92vw);
        }

        #empTable th:last-child,
        #empTable td:last-child {
            width: 180px;
        }

        #historyDrawer .form {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding-bottom: 0;
        }

        #historyDrawer .historySearchField {
            margin-bottom: 0;
            flex: 0 0 auto;
            min-width: 0;
        }

        #historyDrawer .tablewrap,
        #historyDrawer .tablewrap.tablewrap--preview {
            margin-top: 40px !important;
            flex: 1 1 auto;
            overflow-y: auto;
        }

        #attendanceYearDrawer .form {
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .attendanceYearToolbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
        }

        .attendanceYearToolbar .field {
            min-width: 140px;
            max-width: 180px;
        }

        .attendanceYearPl {
            font-size: 12px;
            font-weight: 800;
            color: var(--maroon);
            background: rgba(156, 29, 60, 0.08);
            border: 1px solid rgba(156, 29, 60, 0.16);
            border-radius: 999px;
            padding: 8px 12px;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
        }

        .attendanceYearTotals {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .attendanceYearTotals .badge {
            font-size: 12px;
            font-weight: 900;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
        }

        .attendanceYearLegend {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .attendanceYearLegend .legendItem {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 800;
            background: #fff;
        }

        .attendanceYearGrid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }

        .attendanceMonth {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px;
            background: #fff;
        }

        .attendanceMonth h4 {
            margin: 0 0 8px;
            font-size: 13px;
            color: var(--maroon);
        }

        .attendanceWeekdays,
        .attendanceDays {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 4px;
        }

        .attendanceWeekdays span {
            font-size: 10px;
            font-weight: 900;
            text-align: center;
            color: var(--muted);
        }

        .attendanceDay {
            min-height: 34px;
            border: 1px solid var(--line);
            border-radius: 8px;
            display: grid;
            align-content: center;
            justify-items: center;
            font-size: 10px;
            font-weight: 800;
            background: #fff;
        }

        .attendanceDay--empty {
            border: none;
            background: transparent;
        }

        .attendanceDay--weekend {
            background: #fafafa;
        }

        .attendanceDay__n {
            font-size: 10px;
            color: var(--muted);
            line-height: 1;
        }

        .attendanceDay__code {
            font-size: 10px;
            line-height: 1;
        }

        .attendanceDay--P,
        .attendanceDay--L,
        .attendanceDay--PL,
        .attendanceDay--HD,
        .attendanceDay--HOL {
            border-color: rgba(5, 150, 105, 0.35);
            background: rgba(16, 185, 129, 0.12);
        }

        .attendanceDay--A {
            border-color: rgba(185, 28, 28, 0.35);
            background: rgba(239, 68, 68, 0.12);
        }

        .attendanceDay--OFF,
        .attendanceDay--RNR {
            border-color: rgba(107, 114, 128, 0.35);
            background: rgba(107, 114, 128, 0.12);
        }

        .attendanceYearTrace {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px;
            font-size: 12px;
            font-weight: 700;
            background: #fff;
        }
    </style>

@endsection
