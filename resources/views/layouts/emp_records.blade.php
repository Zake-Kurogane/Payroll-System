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

                            <div class="seg seg--pill" role="group" aria-label="Assignment filter">
                                <button type="button" class="seg__btn seg__btn--emp is-active"
                                    data-assign="All">All</button>
                                <button type="button" class="seg__btn seg__btn--emp"
                                    data-assign="Tagum">Tagum</button>
                                <button type="button" class="seg__btn seg__btn--emp"
                                    data-assign="Davao">Davao</button>
                                <button type="button" class="seg__btn seg__btn--emp"
                                    data-assign="Area">Area</button>
                            </div>

                            <div class="field field--area" id="areaPlaceFilterWrap" style="display:none;">
                                <label class="field__label">Area Place</label>
                                <select id="areaPlaceFilter" class="field__control">
                                    <option value="All" selected>All</option>
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
                            <button class="btn btn--soft btn--icon" type="button" id="exportBtn">
                                <svg class="btn__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <path d="M7 10l5-5 5 5" />
                                    <path d="M12 5v14" />
                                </svg>
                                EXPORT
                            </button>
                            <button class="btn btn--soft btn--icon" type="button" id="importBtn">
                                <svg class="btn__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <path d="M7 10l5 5 5-5" />
                                    <path d="M12 4v11" />
                                </svg>
                                IMPORT
                            </button>
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
                                    </th>

                                    <th class="sortable" data-sort="empId">Emp ID <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="name">Name <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="dept">Department <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="position">Position <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="type">Employment Type <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="assignment">Assignment <span class="sortIcon"
                                            aria-hidden="true"></span></th>
                                    <th class="sortable" data-sort="salary">Salary <span class="sortIcon"
                                            aria-hidden="true"></span></th>

                                    <!-- ✅ NEW -->
                                    <th>Payroll Required</th>

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
                                    <input type="text" id="f_accountNumber" placeholder="e.g. 0123456789" />
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


