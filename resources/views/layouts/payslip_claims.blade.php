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
                        <div class="muted small">Upload scanned images (JPG/PNG). Filename order is used as page order.</div>
                    </div>
                </div>

                <form class="card" style="box-shadow:none; border-style:dashed;" method="POST"
                    action="{{ route('payslip.claims.proofs.upload', ['run' => $selectedRun->id]) }}"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="runRow" style="align-items: end;">
                        <div class="f f--grow">
                            <label>Proof file(s)</label>
                            <input type="file" name="proofs[]" accept=".jpg,.jpeg,.png" multiple required />
                            <div class="muted small" style="margin-top:6px;">Tip: scan at 300 DPI, crop to page edges, avoid shadows.</div>
                        </div>
                        <div class="f" style="min-width: 180px;">
                            <button class="btn btn--maroon" type="submit">Upload + Process</button>
                        </div>
                    </div>
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
                                            if (!empty($s['ink_strict_max'])) $txt .= "Ink(max): {$s['ink_strict_max']} ";
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

            <section class="card tablecard">
                <div class="tablecard__head">
                    <div>
                        <div class="card__title big">Employees (Claim Status)</div>
                        <div class="muted small">Auto-claimed employees are detected from uploaded signature boxes.</div>
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
                                    <td>
                                        @if ($e['claimed_at'])
                                            <span class="badge badge--success">Claimed</span>
                                        @else
                                            <span class="badge badge--muted">Unclaimed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="muted">No employees found for this run.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </section>
@endsection
