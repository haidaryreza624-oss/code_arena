@extends('layouts.team')

@section('title', 'Submission results')

@if ($submission->isPending())
    @push('head')
        <meta http-equiv="refresh" content="5">
    @endpush
@endif

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="text-decoration-none" href="{{ route('team.problems.show', $submission->contestProblem) }}">← Back to problem</a>
        <h1 class="h2 mt-2 mb-1">Submission #{{ $submission->id }}</h1>
        <p class="text-muted mb-0">
            {{ $submission->contestProblem->label }} · {{ $submission->contestProblem->problem->title }}
            · {{ strtoupper($submission->language) }}
            · <time class="local-datetime" datetime="{{ $submission->submitted_at->toIso8601String() }}">{{ $submission->submitted_at->format('M j H:i:s') }} UTC</time>
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('team.dashboard') }}">Contest dashboard</a>
</div>

@if ($judgeError)
    <div class="alert alert-danger" role="alert">Could not refresh the judge result: {{ $judgeError }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Overall verdict</div><div class="fs-4 fw-semibold">{{ $submission->verdict ?? 'Pending' }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Judge status</div><div class="fs-5">{{ ucfirst($submission->status) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Max execution time</div><div class="fs-5">{{ $submission->max_time_ms ?? '—' }} ms</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Max memory</div><div class="fs-5">{{ $submission->max_memory_kb ?? '—' }} KB</div></div></div></div>
</div>

<section class="card shadow-sm mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Sample test-case results</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Sample</th><th>Verdict</th><th>Time</th><th>Memory</th><th>Message</th></tr></thead>
            <tbody>
            @forelse ($sampleResults as $result)
                <tr>
                    <td>{{ $result->testCase->ordinal }}</td>
                    <td>{{ $result->verdict }}</td>
                    <td>{{ $result->time_ms ?? '—' }} ms</td>
                    <td>{{ $result->memory_kb ?? '—' }} KB</td>
                    <td>{{ $result->message }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">
                    {{ $submission->isPending() ? 'Waiting for judge results…' : 'No sample-case results were returned.' }}
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="card shadow-sm">
    <div class="card-header"><h2 class="h5 mb-0">Submitted source</h2></div>
    <div class="card-body"><pre class="mb-0 text-break"><code>{{ $submission->readSource() }}</code></pre></div>
</section>
@endsection
