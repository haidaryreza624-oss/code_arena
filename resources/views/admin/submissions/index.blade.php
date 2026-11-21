@extends('layouts.admin')

@section('title', 'Submissions')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">All submissions</h1>
        <p class="text-muted mb-0">{{ number_format($submissions->total()) }} matching submission(s)</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.dashboard') }}">Back to dashboard</a>
</div>

<form id="submission-filters" class="card card-body shadow-sm mb-3" method="GET"
      action="{{ route('admin.submissions.index') }}">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4">
            <label class="form-label" for="submission-search">Search</label>
            <input class="form-control" id="submission-search" name="search" value="{{ request('search') }}"
                   placeholder="Team, contest, problem, job ID or submission ID">
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="contest-filter">Contest</label>
            <select class="form-select" id="contest-filter" name="contest_id">
                <option value="">All contests</option>
                @foreach ($contests as $contest)
                    <option value="{{ $contest->id }}" @selected((string) request('contest_id') === (string) $contest->id)>
                        {{ $contest->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="team-filter">Team</label>
            <select class="form-select" id="team-filter" name="team_id">
                <option value="">All teams</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected((string) request('team_id') === (string) $team->id)>
                        {{ $team->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="problem-filter">Problem</label>
            <select class="form-select" id="problem-filter" name="problem_id">
                <option value="">All problems</option>
                @foreach ($problems as $problem)
                    <option value="{{ $problem->id }}" @selected((string) request('problem_id') === (string) $problem->id)>
                        {{ $problem->code }} · {{ $problem->title }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="status-filter">Status</label>
            <select class="form-select" id="status-filter" name="status">
                <option value="">All statuses</option>
                @foreach (['queued', 'judging', 'done', 'error'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="verdict-filter">Verdict</label>
            <select class="form-select" id="verdict-filter" name="verdict">
                <option value="">All verdicts</option>
                @foreach (['AC', 'WA', 'TLE', 'MLE', 'RE', 'CE', 'IE'] as $verdict)
                    <option value="{{ $verdict }}" @selected(request('verdict') === $verdict)>{{ $verdict }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="language-filter">Language</label>
            <select class="form-select" id="language-filter" name="language">
                <option value="">All languages</option>
                @foreach ($languages as $language)
                    <option value="{{ $language }}" @selected(request('language') === $language)>{{ $language }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="from-local">Submitted from (local time)</label>
            <input id="from-utc" type="hidden" name="from" value="{{ request('from') }}">
            <input class="form-control" id="from-local" data-utc-target="from-utc" type="datetime-local">
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="to-local">Submitted through (local time)</label>
            <input id="to-utc" type="hidden" name="to" value="{{ request('to') }}">
            <input class="form-control" id="to-local" data-utc-target="to-utc" type="datetime-local">
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="sample-filter">Run type</label>
            <select class="form-select" id="sample-filter" name="sample_run">
                <option value="">All runs</option>
                <option value="0" @selected(request('sample_run') === '0')>Contest submissions</option>
                <option value="1" @selected(request('sample_run') === '1')>Sample runs</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">Apply filters</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.submissions.index') }}">Clear</a>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Submitted</th>
                    <th>Contest</th>
                    <th>Team</th>
                    <th>Problem</th>
                    <th>Language</th>
                    <th>Status</th>
                    <th>Verdict</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($submissions as $submission)
                <tr>
                    <td>
                        <time class="local-datetime" datetime="{{ $submission->submitted_at->toIso8601String() }}">
                            {{ $submission->submitted_at->format('M j, Y H:i:s') }} UTC
                        </time>
                        @if ($submission->is_sample_run)
                            <span class="badge text-bg-info">Sample run</span>
                        @endif
                    </td>
                    <td>{{ $submission->contest?->name ?? 'Unknown contest' }}</td>
                    <td>{{ $submission->participation?->team?->name ?? 'Unknown team' }}</td>
                    <td>
                        {{ $submission->contestProblem?->label ?? '—' }}
                        · {{ $submission->contestProblem?->problem?->title ?? 'Unknown problem' }}
                    </td>
                    <td>{{ $submission->language }}</td>
                    <td>{{ ucfirst($submission->status) }}</td>
                    <td>{{ $submission->verdict ?? '—' }}</td>
                    <td class="text-end">
                        @if ($submission->contest)
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('admin.contests.submissions.show', [$submission->contest, $submission]) }}">
                                Details
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No matching submissions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $submissions->links() }}</div>

@push('scripts')
    <script>
        const searchInput = document.querySelector('#submission-search');
        let searchTimer;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                document.getElementById('submission-filters').requestSubmit();
            }, 400);
        });
    </script>
@endpush
@endsection
