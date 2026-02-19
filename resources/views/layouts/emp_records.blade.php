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
                        <div class="suggest" id="searchSuggest" hidden></div>
                    </div>
                    <div class="field">
                        <label class="field__label">Department</label>
                        <select id="deptFilter" name="department" class="field__control">
                            <option value="">All</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept }}" @selected(request('department') == $dept)>{{ $dept }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field field--status">
                        <label class="field__label">Status</label>
                        <select id="statusFilter" name="status" class="field__control">
                            <option value="">All</option>
                            @foreach ($statuses as $s)
                                <option value="{{ $s->id }}" @selected(request('status') == $s->id)>{{ $s->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="seg seg--pill" id="assignSeg" role="group" aria-label="Assignment filter">
                        <button type="button" class="seg__btn seg__btn--emp {{ request('assignment') ? '' : 'is-active' }}"
                            data-assign="">All</button>
                        @foreach ($assignments as $a)
                            <button type="button"
                                class="seg__btn seg__btn--emp {{ request('assignment') == $a ? 'is-active' : '' }}"
                                data-assign="{{ $a }}">{{ $a }}</button>
                        @endforeach
                        <input type="hidden" name="assignment" id="assignmentInput" value="{{ request('assignment') }}" />
                    </div>

                    <div class="field field--area" id="areaPlaceFilterWrap"
                        style="{{ request('assignment') === 'Area' ? '' : 'display:none;' }}">
                        <label class="field__label">Area Place</label>
                        <select id="areaPlaceFilter" name="area_place" class="field__control">
                            <option value="">All</option>
                            @foreach ($areaPlaces as $ap)
                                <option value="{{ $ap }}" @selected(request('area_place') == $ap)>{{ $ap }}
                                </option>
                            @endforeach
                        </select>
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
                        <button class="btn btn--soft" type="button" id="bulkDeleteEmpBtn">Delete
                            Selected</button>

                        <select class="bulkSelect" id="bulkAssignSelect" aria-label="Set assignment">
                            <option value="">Set assignment...</option>
                            @foreach ($assignments as $a)
                                <option value="{{ $a }}">{{ $a }}</option>
                            @endforeach
                        </select>

                        <select class="bulkSelect" id="bulkAreaPlaceSelect" aria-label="Set area place" style="display:none">
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
                <table class="table" id="empTable">
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
                            <th class="sortable" data-sort="assignment">Assignment <span class="sortIcon"
                                    aria-hidden="true"></span></th>
                            <th class="sortable" data-sort="salary">Salary <span class="sortIcon"
                                    aria-hidden="true"></span></th>
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
                                <td>
                                    @php
                                        $assign = $emp->assignment_type ?: '';
                                        $assignText = $assign === 'Area' ? "Area ({$emp->area_place})" : $assign;
                                    @endphp
                                    {{ $assignText ?: '-' }}
                                </td>
                                <td>
                                    <div class="salaryVal">&#8369;
                                        {{ number_format(($emp->basic_pay ?? 0) + ($emp->allowance ?? 0)) }}</div>
                                </td>
                                <td>
                                    @php
                                        $missing = [];
                                        $basicPay = (float) ($emp->basic_pay ?? 0);
                                        if ($basicPay <= 0) {
                                            $missing[] = 'Basic Pay';
                                        }
                                        $assignType = trim((string) ($emp->assignment_type ?? ''));
                                        if ($assignType === '') {
                                            $missing[] = 'Assignment';
                                        }
                                        if ($assignType === 'Area' && trim((string) ($emp->area_place ?? '')) === '') {
                                            $missing[] = 'Area Place';
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
                                    <button class="iconbtn" type="button" data-action="edit"
                                        data-id="{{ $emp->emp_no }}" title="Edit" aria-label="Edit">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                        </svg>
                                    </button>
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
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No employees found.</td>
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
            window.__areaPlaces = @json($areaPlaces);
        </script>

        <div class="toast" id="toast" aria-live="polite" aria-atomic="true"></div>

        <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

        <!-- DRAWER -->
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
                            <input type="number" id="f_empId" required placeholder="e.g. 1044"
                                inputmode="numeric" pattern="\\d{4}" min="0" max="9999" />
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
                            <input type="text" id="f_mobile" placeholder="09xx-xxx-xxxx"
                                inputmode="numeric" pattern="^[0-9]{4}-[0-9]{3}-[0-9]{4}$" />
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

                    </div>

                    <div class="sectionTitle">Compensation</div>
                    <div class="grid2">
                        <div class="field">
                            <label>Basic Pay (monthly) *</label>
                            <input type="number" id="f_rate" required placeholder="e.g. 20000" min="0" />
                        </div>

                        <div class="field">
                            <label>Allowance (monthly)</label>
                            <input type="number" id="f_allowance" placeholder="e.g. 1500" min="0" />
                        </div>

                        <div class="field">
                            <label>Total Salary (auto)</label>
                            <input type="text" id="f_totalSalary" readonly />
                        </div>
                    </div>

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
                                @foreach ($areaPlaces as $ap)
                                    <option value="{{ $ap }}">{{ $ap }}</option>
                                @endforeach
                            </select>
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
                            <input type="text" id="f_sss" placeholder="00-0000000-0"
                                inputmode="numeric" pattern="^[0-9]{2}-[0-9]{7}-[0-9]{1}$" />
                        </div>
                        <div class="field">
                            <label>PhilHealth No.</label>
                            <input type="text" id="f_ph" placeholder="00-000000000-0"
                                inputmode="numeric" pattern="^[0-9]{2}-[0-9]{9}-[0-9]{1}$" />
                        </div>
                        <div class="field">
                            <label>Pag-IBIG No.</label>
                            <input type="text" id="f_pagibig" placeholder="0000-0000-0000"
                                inputmode="numeric" pattern="^[0-9]{4}-[0-9]{4}-[0-9]{4}$" />
                        </div>
                        <div class="field">
                            <label>TIN</label>
                            <input type="text" id="f_tin" placeholder="000-000-000-000"
                                inputmode="numeric" pattern="^[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}$" />
                        </div>
                    </div>

                    <div class="sectionTitle">Bank Details</div>
                    <div class="grid2">
                        <div class="field">
                            <label>Bank Name</label>
                            <input type="text" id="f_bankName" placeholder="e.g. BDO" />
                        </div>
                        <div class="field">
                            <label>Account Name</label>
                            <input type="text" id="f_accountName" placeholder="e.g. Juan Dela Cruz" />
                        </div>
                        <div class="field field--full">
                            <label>Account Number</label>
                            <input type="text" id="f_accountNumber" placeholder="0000-0000-0000-0000"
                                inputmode="numeric" pattern="^[0-9]{4}(-[0-9]{4}){2,4}$" />
                            <div class="hint">Leave blank to pay by cash.</div>
                        </div>
                        <div class="field field--full">
                            <label>Payout Method (auto)</label>
                            <input type="text" id="f_payoutMethod" readonly />
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

@endsection
