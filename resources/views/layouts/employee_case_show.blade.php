@extends('layouts.app')

@section('title', 'Case ' . $case->case_no)

@section('vite')
    @vite(['resources/css/emp_records.css'])
@endsection

@section('content')
    <section class="content">
        <div class="headline">
            <div>
                <h1>CASE {{ $case->case_no }}</h1>
                <p class="muted">Incident / NTE / Hearing / Decision / Sanction lifecycle.</p>
            </div>
        </div>

        <section class="card">
            <div class="card__title">Case Information</div>
            <div class="grid2">
                <div><strong>Type:</strong> {{ ucfirst(str_replace('_', ' ', $case->case_type)) }}</div>
                <div><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $case->status)) }}</div>
                <div><strong>Date Reported:</strong> {{ optional($case->date_reported)->format('M d, Y') ?? '—' }}</div>
                <div><strong>Incident Date:</strong> {{ optional($case->incident_date)->format('M d, Y') ?? '—' }}</div>
            </div>
        </section>

        <section class="card">
            <div class="card__title">Parties Involved</div>
            <ul class="muted">
                @foreach ($case->parties as $party)
                    <li><strong>{{ ucfirst($party->role) }}:</strong>
                        {{ $party->employee ? ($party->employee->last_name . ', ' . $party->employee->first_name) : '—' }}
                    </li>
                @endforeach
                @if ($case->parties->isEmpty())
                    <li>No parties recorded yet.</li>
                @endif
            </ul>
        </section>

        <section class="card">
            <div class="card__title">Documents</div>
            @if ($case->documents->isEmpty())
                <p class="muted small">No documents uploaded yet.</p>
            @else
                <ul class="muted">
                    @foreach ($case->documents as $doc)
                        <li>
                            <strong>{{ ucfirst(str_replace('_', ' ', $doc->document_type)) }}:</strong>
                            {{ $doc->file_name }}
                            <span class="tiny">({{ optional($doc->date_uploaded)->format('M d, Y') ?? 'n/a' }})</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card">
            <div class="card__title">Sanctions</div>
            @if ($case->sanctions->isEmpty())
                <p class="muted small">No sanction recorded.</p>
            @else
                <ul class="muted">
                    @foreach ($case->sanctions as $s)
                        <li>
                            {{ ucfirst(str_replace('_', ' ', $s->sanction_type)) }} —
                            Status: {{ ucfirst($s->status) }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </section>
@endsection
