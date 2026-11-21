@extends('layouts.admin')

@section('title', 'Submission #'.$submission->id)

@if ($submission->isPending())
    @push('head')
        <meta http-equiv="refresh" content="5">
    @endpush
@endif

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Submission #{{ $submission->id }}</h1>
        <p class="text-muted mb-0">
            {{ $submission->participation->team->name }} · {{ $submission->contestProblem->label }}
            · {{ $submission->language }} · {{ $submission->submitted_at->format('M j, Y H:i:s') }}
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.contests.submissions.index', $contest) }}">Back to submissions</a>
</div>

@if ($judgeError)
    <div class="alert alert-danger" role="alert">Could not refresh from judge: {{ $judgeError }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Status</div><div class="fs-5">{{ ucfirst($submission->status) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Verdict</div><div class="fs-5">{{ $submission->verdict ?? 'Pending' }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Max time</div><div class="fs-5">{{ $submission->max_time_ms ?? '—' }} ms</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Max memory</div><div class="fs-5">{{ $submission->max_memory_kb ?? '—' }} KB</div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Test-case results</h2>
                <span class="badge text-bg-light">{{ $submission->results->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Case</th><th>Kind</th><th>Verdict</th><th>Time</th><th>Memory</th><th>Message</th></tr></thead>
                    <tbody>
                    @forelse ($submission->results->sortBy(fn ($result) => $result->testCase?->ordinal) as $result)
                        <tr>
                            <td>{{ $result->testCase?->ordinal ?? '—' }}</td>
                            <td>{{ ucfirst($result->testCase?->kind ?? 'unknown') }}</td>
                            <td>{{ $result->verdict }}</td>
                            <td>{{ $result->time_ms ?? '—' }} ms</td>
                            <td>{{ $result->memory_kb ?? '—' }} KB</td>
                            <td>{{ $result->message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ $submission->isPending() ? 'Waiting for judge results…' : 'No individual results.' }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card shadow-sm">
            <div class="card-header"><h2 class="h5 mb-0">Source code</h2></div>
            <div class="card-body"><pre class="mb-0"><code>{{ $submission->readSource() }}</code></pre></div>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="card shadow-sm mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Judging information</h2></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Judge job</dt><dd class="col-7"><code>{{ $submission->judge_job_id ?? '—' }}</code></dd>
                    <dt class="col-5">Rejudges</dt><dd class="col-7">{{ $submission->rejudge_count }}</dd>
                    <dt class="col-5">Last rejudged</dt><dd class="col-7">{{ $submission->last_rejudged_at?->format('M j, Y H:i:s') ?? '—' }}</dd>
                    <dt class="col-5">By</dt><dd class="col-7">{{ $submission->lastRejudgedBy?->name ?? '—' }}</dd>
                </dl>
            </div>
        </section>
        @if ($contest->isRunning())
            <section class="card border-danger">
                <div class="card-header text-danger fw-semibold">Rejudge</div>
                <div class="card-body">
                    <p class="text-muted">Resubmit the stored source and test cases to the judge. Existing results will be replaced.</p>
                    <form method="POST" action="{{ route('admin.contests.submissions.rejudge', [$contest, $submission]) }}">
                        @csrf
                        <button class="btn btn-danger" type="submit"
                                onclick="return confirm('Rejudge this submission now?')">Submit for rejudge</button>
                    </form>
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
