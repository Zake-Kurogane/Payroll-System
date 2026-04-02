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
        <div id="claimPrintLoading" class="muted small" style="padding: 12px 14px;">Loading PDF…</div>
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
            <div class="card" style="margin-bottom: 12px; border-color: rgba(34,197,94,.3); background: rgba(34,197,94,.06);">
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
                <div class="card__title big">Select Released Payroll Run</div>
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
                    </div>
                    <div class="runRow">
                        <div class="f f--grow">
                            <label>Payroll Run</label>
                            <select name="run_id" onchange="this.form.submit()">
                                <option value="" selected>- Select a released run -</option>
                                @foreach ($runs as $r)
                                    <option value="{{ $r->id }}" @selected($selectedRun && $selectedRun->id === $r->id)>
                                        {{ ($r->run_code ?: ('RUN-' . $r->id)) . ' • ' . ($r->period_month ?: '-') . ' (' . ($r->cutoff ?: '-') . ') • ' . $r->displayLabel() }}
                                    </option>
                                @endforeach
                            </select>
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
                        <a class="btn btn--soft" href="{{ route('payslip.claims.sheet', ['run' => $selectedRun->id]) }}">
                            Download Claim Sheet (PDF)
                        </a>
                        <button class="btn btn--soft" type="button" id="printClaimSheetBtn"
                            data-url="{{ route('payslip.claims.sheet', ['run' => $selectedRun->id]) }}">
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
                            <div class="muted small" style="margin-top:6px;">Tip: scan at 300 DPI, crop to page edges, avoid shadows.</div>
                        </div>
                        <div class="f" style="min-width: 180px;">
                            <button class="btn btn--maroon" type="submit">Upload + Process</button>
                        </div>
                    </div>
                    <input type="hidden" name="month" value="{{ $monthFilter ?? '' }}" />
                    <input type="hidden" name="cutoff" value="{{ $cutoffFilter ?? '' }}" />
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
                    <table class="table" aria-label="Proof uploads table">
                        <thead>
                            <tr>
                                <th>Uploaded At</th>
                                <th>File</th>
                                <th>Processed</th>
                                <th>Summary</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($proofs as $p)
                                <tr>
                                    <td>{{ $p->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $p->original_name }}</td>
                                    <td>{{ $p->processed_at ? $p->processed_at->format('Y-m-d H:i') : '—' }}</td>
                                    <td>
                                        @php
                                            $s = $p->processed_summary ?? [];
                                            $txt = '';
                                            if (!empty($s['claimed_new'])) $txt .= "Claimed(new): {$s['claimed_new']} ";
                                            if (!empty($s['claimed_rows_detected'])) $txt .= "Rows(claimed): " . implode(',', (array) $s['claimed_rows_detected']) . " ";
                                            if (!empty($s['claimed_emp_nos_detected'])) $txt .= "Emp(claimed): " . implode(',', (array) $s['claimed_emp_nos_detected']) . " ";
                                            if (!empty($s['pages'])) $txt .= "Pages: {$s['pages']} ";
                                            if (!empty($s['rows_scanned'])) $txt .= "Rows: {$s['rows_scanned']} ";
                                            if (!empty($s['ink_strict_max'])) $txt .= "Ink strict(max): {$s['ink_strict_max']} ";
                                            if (!empty($s['ink_soft_max'])) $txt .= "Ink soft(max): {$s['ink_soft_max']} ";
                                            if (!empty($s['geo'])) {
                                                $g = $s['geo'];
                                                if (!empty($g['fallback_reason'])) {
                                                    $txt .= "geo[fallback:{$g['fallback_reason']}] ";
                                                } else {
                                                    $iw = $g['img_w'] ?? '?'; $ih = $g['img_h'] ?? '?';
                                                    $tl = $g['table_left'] ?? '?'; $tr = $g['table_right'] ?? '?';
                                                    $tt = $g['table_top'] ?? '?'; $tb = $g['table_bottom'] ?? '?';
                                                    $rx1 = $g['rec_x1'] ?? '?'; $rx2 = $g['rec_x2'] ?? '?';
                                                    $rh = $g['row_h'] ?? '?';
                                                    $txt .= "geo[img:{$iw}x{$ih} tbl:{$tl}-{$tr}x{$tt}-{$tb} rec:{$rx1}-{$rx2} rowH:{$rh}] ";
                                                }
                                                if (!empty($g['row_ink_strict']))  $txt .= "ink_strict(rows):[{$g['row_ink_strict']}] ";
                                                if (!empty($g['row_sig_ink']))     $txt .= "sig_ink(rows):[{$g['row_sig_ink']}] ";
                                                if (!empty($g['row_qr_found']))    $txt .= "qr_found(rows):[{$g['row_qr_found']}] ";
                                                // legacy keys from old scanner builds
                                                if (!empty($g['row_ink_soft']))       $txt .= "ink_soft(rows):[{$g['row_ink_soft']}] ";
                                                if (!empty($g['row_sig_ink_soft']))   $txt .= "sig_soft(rows):[{$g['row_sig_ink_soft']}] ";
                                                if (!empty($g['row_sig_ink_strict'])) $txt .= "sig_strict(rows):[{$g['row_sig_ink_strict']}] ";
                                            }
                                            if (!empty($s['slice_first_emp_no']) && !empty($s['slice_last_emp_no'])) $txt .= "Slice: {$s['slice_first_emp_no']}â€“{$s['slice_last_emp_no']} ";
                                        @endphp
                                        <span class="muted small">{{ trim($txt) ?: '—' }}</span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                            <a class="btn btn--soft" href="{{ route('payslip.claims.proofs.download', ['proof' => $p->id]) }}">Download</a>
                                            <form method="POST" action="{{ route('payslip.claims.proofs.destroy', ['proof' => $p->id]) }}"
                                                onsubmit="return confirm('Delete this proof file? This will also undo any auto-claims detected from it.');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="month" value="{{ $monthFilter ?? '' }}" />
                                                <input type="hidden" name="cutoff" value="{{ $cutoffFilter ?? '' }}" />
                                                <button class="btn btn--soft" type="submit">Delete</button>
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
                        <div class="muted small">QR code could not be read or did not match — HR must verify these manually. Click Confirm to mark as claimed.</div>
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
                                    <td>{{ $e['area_place'] ?: '—' }}</td>
                                    <td>{{ isset($e['confidence']) ? round((float)$e['confidence'] * 100) . '%' : '—' }}</td>
                                    <td>
                                        <form method="POST"
                                            action="{{ route('payslip.claims.toggle', ['run' => $selectedRun->id, 'employeeId' => $e['employee_id']]) }}"
                                            style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="month" value="{{ $monthFilter ?? '' }}" />
                                            <input type="hidden" name="cutoff" value="{{ $cutoffFilter ?? '' }}" />
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
                                    <td>{{ $e['assignment_type'] ?: '—' }}</td>
                                    <td>{{ $e['area_place'] ?: '—' }}</td>
                                    <td style="text-align:center;">
                                        <form method="POST"
                                            action="{{ route('payslip.claims.toggle', ['run' => $selectedRun->id, 'employeeId' => $e['employee_id']]) }}"
                                            style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="month" value="{{ $monthFilter ?? '' }}" />
                                            <input type="hidden" name="cutoff" value="{{ $cutoffFilter ?? '' }}" />
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
