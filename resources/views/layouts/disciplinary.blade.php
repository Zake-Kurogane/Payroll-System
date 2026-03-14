@extends('layouts.app')

@section('title', 'Disciplinary')

@section('vite')
    @vite(['resources/css/emp_records.css'])
@endsection

@section('content')
    <section class="content">
        <div class="headline">
            <div>
                <h1>DISCIPLINARY</h1>
                <p class="muted">View Notice to Explain, sanctions, memos, and tardiness summaries per employee.</p>
            </div>
        </div>

        <section class="card">
            <div class="card__title">Coming Soon</div>
            <p class="muted small">This section will list disciplinary records and tardiness details. Select an employee
                from the Employee Records page to manage or import their items.</p>
        </section>
    </section>
@endsection
