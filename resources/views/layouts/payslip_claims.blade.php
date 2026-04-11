@extends('layouts.app')

@section('title', 'Payslip Claims')

@section('vite')
    @vite(['resources/css/payslips.css', 'resources/js/payslip_claims.js'])
@endsection

@section('body_end')
    <!-- Claim Sheet print modal (stays on page; no download) -->
    <div class="overlay" id="claimPrintOverlay" hidden></div>
    <aside class="modal" id="claimPrintModal" aria-hidden="true" hidden>
        <div class="modal__head">
            <div class="modal__title">Claim Sheet (Print)</div>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="btn btn--soft" type="button" id="claimPrintBtn" disabled>Print</button>
                <button class="btn" type="button" id="claimPrintCloseBtn">Close</button>
            </div>
        </div>
        <div id="claimPrintLoading" class="muted small" style="padding: 12px 14px;">Loading PDFâ€¦</div>
        <iframe class="modal__frame" id="claimPrintFrame" title="Claim Sheet Print Preview" hidden></iframe>
    </aside>
@endsection

@section('content')
    <section class="content content--claims">
        <div class="headline headline--withActions">
            <div>
                <h1>PAYSLIP CLAIMS</h1>
                <div class="muted small">Upload signed claim sheets and track claimed vs unclaimed payslips.</div>
            </div>
        </div>

        @if (session('success'))
            <div class="card js-autoHideAlert" data-hide-ms="3000" style="margin-bottom: 12px; border-color: rgba(34,197,94,.3); background: rgba(34,197,94,.06);">
                <div style="font-weight: 900; color: #166534;">{{ session('success') }}</div>
            </div>
        @endif

        @if ($errors->any())
            <div class="card" style="margin-bottom: 12px; border-color: rgba(239,68,68,.25); background: rgba(239,68,68,.06);">
                <div style="font-weight: 900; color: #991b1b;">{{ $errors->first() }}</div>
            </div>
        @endif

        <section class="card runCard">
            <div class="runCard__left">
                <div class="card__title big">Released Payroll Run</div>
                <form method="GET" action="{{ route('payslip.claims') }}">
                    <div class="runRow">
                        <div class="f">
                            <label>Month</label>
                            <input type="month" name="month" value="{{ $monthFilter ?? '' }}" onchange="this.form.submit()" />
                        </div>
                        <div class="f">
                            <label>Cutoff</label>
                            <select name="cutoff" onchange="this.form.submit()">
                                <option value="">All</option>
                                <option value="11-25" @selected(($cutoffFilter ?? '') === '11-25')>11-25</option>
                                <option value="26-10" @selected(($cutoffFilter ?? '') === '26-10')>26-10</option>
                            </select>
                        </div>
                        <div class="f f--grow">
                            <label>Assignment</label>
                            <input type="hidden" name="assignment" id="assignmentInput" value="{{ $assignmentFilter ?? '' }}" />
                            <input type="hidden" name="area_place" id="areaPlaceInput" value="{{ $areaPlaceFilter ?? '' }}" />
                            <div class="seg seg--pill claimsAssignSeg" id="assignSeg" role="group"
                                aria-label="Filter by assignment"
                                data-active-assign="{{ $assignmentFilter ?? '' }}"
                                data-active-place="{{ $areaPlaceFilter ?? '' }}">
                                <button type="button" class="seg__btn seg__btn--emp is-active" data-assign="">All</button>
                            </div>
                        </div>
                    </div>
                    <div class="runRow">
                        <div class="f f--grow">
                            <label>Matched Run</label>
                            <div class="runDisplay">
                                @if ($selectedRun)
                                    {{ ($selectedRun->run_code ?: ('RUN-' . $selectedRun->id)) . ' • ' . ($selectedRun->period_month ?: '-') . ' (' . ($selectedRun->cutoff ?: '-') . ') • ' . $selectedRun->displayLabel() }}
                                @else
                                    No released payroll run found for the selected filters.
                                @endif
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="runCard__right">
                <div class="runStats">
                    <div class="miniStat">
                        <div class="miniVal">{{ $summary['total'] ?? '-' }}</div>
                        <div class="miniLbl">Total</div>
                    </div>
                    <div class="miniStat">
                        <div class="miniVal">{{ $summary['claimed'] ?? '-' }}</div>
                        <div class="miniLbl">Claimed</div>
                    </div>
                    <div class="miniStat" style="{{ ($summary['needs_review'] ?? 0) > 0 ? 'color:#b45309;' : '' }}">
                        <div class="miniVal">{{ $summary['needs_review'] ?? '-' }}</div>
                        <div class="miniLbl">Needs Review</div>
                    </div>
                    <div class="miniStat">
                        <div class="miniVal">{{ $summary['unclaimed'] ?? '-' }}</div>
                        <div class="miniLbl">Unclaimed</div>
                    </div>
                </div>

                @if ($selectedRun)
                    <div class="runActions" style="gap: 10px; flex-wrap: wrap;">
                        <a class="btn btn--soft" href="{{ route('payslip.claims.sheet', ['run' => $selectedRun->id, 'assignment' => $assignmentFilter, 'area_place' => $areaPlaceFilter]) }}">
                            Download Claim Sheet (PDF)
                        </a>
                        <button class="btn btn--soft" type="button" id="printClaimSheetBtn"
                            data-url="{{ route('payslip.claims.sheet', ['run' => $selectedRun->id, 'assignment' => $assignmentFilter, 'area_place' => $areaPlaceFilter]) }}">
                            Print Claim Sheet
                        </button>
                    </div>
                @endif
            </div>
        </section>

        @if ($selectedRun)
            <section class="card tablecard">
                <div class="tablecard__head">
                    <div>
                        <div class="card__title big">Upload Signed Claim Sheet</div>
                        <div class="muted small">Upload scanned files (JPG/PNG/PDF). Filename order is used as page order.</div>
                    </div>
                </div>

                <form class="card" style="box-shadow:none; border-style:dashed;" method="POST"
                    action="{{ route('payslip.claims.proofs.upload', ['run' => $selectedRun->id]) }}"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="runRow" style="align-items: end;">
                        <div class="f f--grow">
                            <label>Proof file(s)</label>
                            <input type="file" name="proofs[]" accept=".jpg,.jpeg,.png,.pdf,application/pdf" multiple required />
                        </div>
                        <div class="f" style="min-width: 180px;">
                            <button class="btn btn--maroon" type="submit">Upload + Process</button>
                        </div>
                    </div>
                    <input type="hidden" name="month" value="{{ $monthFilter ?? '' }}" />
                    <input type="hidden" name="cutoff" value="{{ $cutoffFilter ?? '' }}" />
                    <input type="hidden" name="assignment" value="{{ $assignmentFilter ?? '' }}" />
                    <input type="hidden" name="area_place" value="{{ $areaPlaceFilter ?? '' }}" />
                </form>
            </section>

            <section class="card tablecard">
                <div class="tablecard__head">
                    <div>
                        <div class="card__title big">Proof Uploads</div>
                        <div class="muted small">Stored scans for this run.</div>
                    </div>
                </div>
                <div class="tablewrap">
                    <table class="table table--proofUploads" aria-label="Proof uploads table">
                        <thead>
                            <tr>
                                <th>Uploaded At</th>
                                <th>File</th>
                                <th>Processed</th>
                                <th>Result</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($proofs as $p)
                                <tr>
                                    <td>{{ $p->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $p->original_name }}</td>
                                    <td>{{ $p->processed_at ? $p->processed_at->format('Y-m-d H:i') : 'â€”' }}</td>
                                    <td>
                                        @php($ps = is_array($p->processed_summary ?? null) ? $p->processed_summary : [])
                                        @if (!empty($ps))
                                            <div class="small">
                                                rows: {{ (int) ($ps['rows_scanned'] ?? 0) }},
                                                detected: {{ (int) ($ps['claimed_detected'] ?? 0) }},
                                                new: {{ (int) ($ps['claimed_new'] ?? 0) }}
                                            </div>
                                            @if (!empty($ps['checkbox_cutoff']))
                                                <div class="small muted">cutoff: {{ $ps['checkbox_cutoff'] }}</div>
                                            @endif
                                            @if (!empty($ps['geo']['layout_used']))
                                                <div class="small muted">layout: {{ $ps['geo']['layout_used'] }}</div>
                                            @endif
                                            @if (!empty($ps['geo']['fallback_reason']))
                                                <div class="small muted">fallback: {{ $ps['geo']['fallback_reason'] }}</div>
                                            @endif
                                            @if (isset($ps['geo']['row_qr_found']) && $ps['geo']['row_qr_found'] !== '')
                                                <div class="small muted">qr/row: {{ $ps['geo']['row_qr_found'] }}</div>
                                            @endif
                                            @if (isset($ps['geo']['probe_page_index']))
                                                <div class="small muted">probe→pg: {{ $ps['geo']['probe_page_index'] }}</div>
                                            @endif
                                            @if (!empty($ps['geo']['first_mismatch']))
                                                <div class="small" style="color:#b45309;">{{ $ps['geo']['first_mismatch'] }}</div>
                                            @endif
                                            @if (!empty($ps['error']))
                                                <div class="small" style="color:#b91c1c; font-weight:700;">{{ $ps['error'] }}</div>
                                            @endif
                                        @else
                                            <span class="muted">No summary yet.</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="iconrow">
                                            <a class="iconbtn" href="{{ route('payslip.claims.proofs.download', ['proof' => $p->id]) }}" title="Download" aria-label="Download">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path d="M12 3v12"></path>
                                                    <path d="M7 10l5 5 5-5"></path>
                                                    <path d="M4 21h16"></path>
                                                </svg>
                                            </a>
                                            <form method="POST" action="{{ route('payslip.claims.proofs.destroy', ['proof' => $p->id]) }}"
                                                onsubmit="return confirm('Delete this proof file? This will also undo any auto-claims detected from it.');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="month" value="{{ $monthFilter ?? '' }}" />
                                                <input type="hidden" name="cutoff" value="{{ $cutoffFilter ?? '' }}" />
                                                <input type="hidden" name="assignment" value="{{ $assignmentFilter ?? '' }}" />
                                                <input type="hidden" name="area_place" value="{{ $areaPlaceFilter ?? '' }}" />
                                                <button class="iconbtn" type="submit" title="Delete" aria-label="Delete">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                        <path d="M3 6h18"></path>
                                                        <path d="M8 6V4h8v2"></path>
                                                        <path d="M19 6l-1 14H6L5 6"></path>
                                                        <path d="M10 11v6"></path>
                                                        <path d="M14 11v6"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="muted">No proof uploads yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @php($needsReviewRows = $employees->filter(fn($e) => ($e['review_status'] ?? '') === 'needs_review')->values())
            @if ($needsReviewRows->isNotEmpty())
            <section class="card tablecard" style="border-color: rgba(180,83,9,.35); background: rgba(254,243,199,.4);">
                <div class="tablecard__head">
                    <div>
                        <div class="card__title big" style="color:#92400e;">&#9888; Needs Review ({{ $needsReviewRows->count() }})</div>
                        <div class="muted small">QR code could not be read or did not match â€” HR must verify these manually. Click Confirm to mark as claimed.</div>
                    </div>
                </div>
                <div class="tablewrap">
                    <table class="table" aria-label="Needs review table">
                        <thead>
                            <tr>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Area</th>
                                <th>Confidence</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($needsReviewRows as $e)
                                <tr>
                                    <td>{{ $e['emp_no'] }}</td>
                                    <td>{{ $e['name'] }}</td>
                                    <td>{{ $e['area_place'] ?: 'â€”' }}</td>
                                    <td>{{ isset($e['confidence']) ? round((float)$e['confidence'] * 100) . '%' : 'â€”' }}</td>
                                    <td>
                                        <form method="POST"
                                            action="{{ route('payslip.claims.toggle', ['run' => $selectedRun->id, 'employeeId' => $e['employee_id']]) }}"
                                            style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="month" value="{{ $monthFilter ?? '' }}" />
                                            <input type="hidden" name="cutoff" value="{{ $cutoffFilter ?? '' }}" />
                                            <input type="hidden" name="assignment" value="{{ $assignmentFilter ?? '' }}" />
                                            <input type="hidden" name="area_place" value="{{ $areaPlaceFilter ?? '' }}" />
                                            <button class="btn btn--soft" type="submit" title="Mark as claimed (confirmed)">Confirm</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @endif

            <section class="card tablecard">
                <div class="tablecard__head">
                    <div>
                        <div class="card__title big">Employees (Claim Status)</div>
                        <div class="muted small">Auto-claimed employees are detected from uploaded claim sheets. QR codes identify each row definitively.</div>
                    </div>
                </div>
                <div class="tablewrap">
                    <table class="table" aria-label="Employees claim status table">
                        <thead>
                            <tr>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Assignment</th>
                                <th>Area</th>
                                <th>Received</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $e)
                                <tr>
                                    <td>{{ $e['emp_no'] }}</td>
                                    <td>{{ $e['name'] }}</td>
                                    <td>{{ $e['assignment_type'] ?: 'â€”' }}</td>
                                    <td>{{ $e['area_place'] ?: 'â€”' }}</td>
                                    <td style="text-align:center;">
                                        <form method="POST"
                                            action="{{ route('payslip.claims.toggle', ['run' => $selectedRun->id, 'employeeId' => $e['employee_id']]) }}"
                                            style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="month" value="{{ $monthFilter ?? '' }}" />
                                            <input type="hidden" name="cutoff" value="{{ $cutoffFilter ?? '' }}" />
                                            <input type="hidden" name="assignment" value="{{ $assignmentFilter ?? '' }}" />
                                            <input type="hidden" name="area_place" value="{{ $areaPlaceFilter ?? '' }}" />
                                            <button type="submit"
                                                title="{{ $e['claimed_at'] ? 'Click to mark unclaimed' : 'Click to mark claimed' }}"
                                                style="background:none;border:none;cursor:pointer;padding:0;">
                                                <input type="checkbox" @checked($e['claimed_at'])
                                                    style="cursor:pointer;width:16px;height:16px;pointer-events:none;"
                                                    aria-label="Toggle claimed" />
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        @if ($e['claimed_at'])
                                            <span class="badge badge--success">Claimed</span>
                                        @elseif (($e['review_status'] ?? '') === 'needs_review')
                                            <span class="badge" style="background:rgba(180,83,9,.12);color:#92400e;border-color:rgba(180,83,9,.3);">Needs Review</span>
                                        @else
                                            <span class="badge badge--muted">Unclaimed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="muted">No employees found for this run.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </section>
@endsection





