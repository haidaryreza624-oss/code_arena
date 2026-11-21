@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="mb-4">
    <h1 class="h2 mb-1">Admin dashboard</h1>
    <p class="text-muted mb-0">Manage contests and the resources used by Code Arena.</p>
</div>

<div class="row g-3">
    <div class="col-12 col-md-6 col-xl-4">
        <a class="card h-100 text-decoration-none text-dark shadow-sm" href="{{ route('admin.contests.index') }}">
            <div class="card-body">
                <h2 class="h5">Contests</h2>
                <p class="text-muted mb-0">Create and manage contest schedules and settings.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a class="card h-100 text-decoration-none text-dark shadow-sm" href="{{ route('admin.problems.index') }}">
            <div class="card-body">
                <h2 class="h5">Problem bank</h2>
                <p class="text-muted mb-0">Manage problem statements and test cases.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a class="card h-100 text-decoration-none text-dark shadow-sm" href="{{ route('admin.teams.index') }}">
            <div class="card-body">
                <h2 class="h5">Teams</h2>
                <p class="text-muted mb-0">Review teams and contest participation accounts.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a class="card h-100 text-decoration-none text-dark shadow-sm" href="{{ route('admin.submissions.index') }}">
            <div class="card-body">
                <h2 class="h5">Submissions</h2>
                <p class="text-muted mb-0">Review submitted solutions and judging results.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a class="card h-100 text-decoration-none text-dark shadow-sm" href="{{ route('admin.questions.index') }}">
            <div class="card-body">
                <h2 class="h5">Questions</h2>
                <p class="text-muted mb-0">Manage contestant questions and clarifications.</p>
            </div>
        </a>
    </div>
</div>
@endsection
