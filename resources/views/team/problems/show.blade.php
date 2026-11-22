@extends('layouts.team')

@section('title', $contestProblem->label.' · '.$contest->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="text-decoration-none" href="{{ route('team.dashboard') }}">← Contest dashboard</a>
        <h1 class="h2 mt-2 mb-1">
            <span class="badge text-bg-dark">{{ $contestProblem->label }}</span>
            {{ $contestProblem->problem->title }}
        </h1>
        <p class="text-muted mb-0">{{ $contestProblem->problem->code }} · {{ $contestProblem->points }} points · {{ $contest->name }}</p>
    </div>
</div>

<section class="card shadow-sm mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Problem statement</h2></div>
    <div class="card-body">
        <div class="text-break" style="white-space: pre-wrap">{{ $contestProblem->problem->statement_md }}</div>
    </div>
</section>

<section class="card shadow-sm mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Sample test cases</h2></div>
    <div class="card-body">
        @forelse ($contestProblem->problem->sampleTestCases as $testCase)
            <h3 class="h6">Sample {{ $testCase->ordinal }}</h3>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="small fw-semibold mb-1">Input</div>
                    <pre class="bg-light border rounded p-3 mb-0 text-break">{{ $testCase->readInput() }}</pre>
                </div>
                <div class="col-md-6">
                    <div class="small fw-semibold mb-1">Expected output</div>
                    <pre class="bg-light border rounded p-3 mb-0 text-break">{{ $testCase->readOutput() }}</pre>
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">No sample test cases are available.</p>
        @endforelse
    </div>
</section>

@if ($contest->isRunning())
    <section class="card border-primary shadow-sm mb-4">
        <div class="card-header fw-semibold">Submit a solution</div>
        <div class="card-body">
            <p class="text-muted">Upload a Python (.py) or C++ (.cpp, .cc, .cxx) or java (.java)source file. The language is detected from the file extension.</p>
            <form method="POST" enctype="multipart/form-data"
                  action="{{ route('team.submissions.store', $contestProblem) }}">
                @csrf
                <label class="form-label" for="source_file">Source file</label>
                <input class="form-control @error('source_file') is-invalid @enderror" id="source_file"
                       name="source_file" type="file" accept=".py,.cpp,.cc,.cxx" required>
                @error('source_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-primary mt-3" type="submit">Submit to judge</button>
            </form>
        </div>
    </section>
@else
    <div class="alert alert-secondary">The contest is over; new submissions are closed.</div>
@endif

<section class="card shadow-sm">
    <div class="card-header"><h2 class="h5 mb-0">Your submissions</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Submitted</th><th>Language</th><th>Status</th><th>Verdict</th><th></th></tr></thead>
            <tbody>
            @forelse ($submissions as $submission)
                <tr>
                    <td><time class="local-datetime" datetime="{{ $submission->submitted_at->toIso8601String() }}">{{ $submission->submitted_at->format('M j H:i:s') }} UTC</time></td>
                    <td>{{ $submission->language }}</td>
                    <td>{{ ucfirst($submission->status) }}</td>
                    <td>{{ $submission->verdict ?? 'Pending' }}</td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('team.submissions.show', $submission) }}">Results</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">No submissions for this problem.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
