@extends('layouts.admin')

@section('title', $contest->name)

@if ($contest->isApproved() || $contest->isRunning())
    @push('head')
        <meta http-equiv="refresh" content="10">
    @endpush
@endif

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h1 class="h2 mb-0">{{ $contest->name }}</h1>
            <span class="badge text-bg-{{ $contest->isRunning() ? 'success' : ($contest->isApproved() ? 'primary' : ($contest->isEnded() ? 'secondary' : 'warning')) }}">
                {{ ucfirst($contest->status) }}
            </span>
        </div>
        <div class="text-muted mt-1">
            <code>{{ $contest->slug }}</code>
            · <time class="local-datetime" datetime="{{ $contest->start_time->toIso8601String() }}">{{ $contest->start_time->format('M j, Y H:i') }} UTC</time>
            – <time class="local-datetime" datetime="{{ $contest->end_time->toIso8601String() }}">{{ $contest->end_time->format('M j, Y H:i') }} UTC</time>
            · {{ $contest->is_public ? 'Public scoreboard' : 'Private scoreboard' }}
        </div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.contests.index') }}">All contests</a>
</div>

@foreach ($judgeErrors as $judgeError)
    <div class="alert alert-danger" role="alert">Could not refresh a pending submission from the judge: {{ $judgeError }}</div>
@endforeach

@if ($contest->isDraft())
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Draft contest settings</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.contests.update', $contest) }}">
                @csrf
                @method('PUT')
                @include('admin.contests.partials.form', ['contest' => $contest])
                <button class="btn btn-primary mt-3" type="submit">Save settings</button>
            </form>
        </div>
    </div>
@else
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Problems</div><div class="fs-4">{{ $contest->contestProblems->count() }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Teams</div><div class="fs-4">{{ $contest->participations->count() }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Submissions</div><div class="fs-4">{{ $contest->submissions()->count() }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Questions</div><div class="fs-4">{{ $contest->questions->count() }}</div></div></div></div>
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-7">
        <section class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Assigned problems</h2>
                <span class="badge text-bg-light">{{ $contest->contestProblems->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Label</th><th>Problem</th><th>Points</th>@if ($contest->isDraft())<th></th>@endif</tr></thead>
                    <tbody>
                    @forelse ($contest->contestProblems as $contestProblem)
                        <tr>
                            <td><span class="badge text-bg-dark">{{ $contestProblem->label }}</span></td>
                            <td>
                                <a href="{{ route('admin.problems.show', $contestProblem->problem) }}">
                                    <strong>{{ $contestProblem->problem->code }}</strong> · {{ $contestProblem->problem->title }}
                                </a>
                            </td>
                            <td>{{ $contestProblem->points }}</td>
                            @if ($contest->isDraft())
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.contests.problems.unassign', [$contest, $contestProblem]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Unassign</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $contest->isDraft() ? 4 : 3 }}" class="text-center text-muted py-4">No problems assigned.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($contest->isDraft())
                <div class="card-body border-top">
                    <h3 class="h6">Assign a problem from the bank</h3>
                    <form class="row g-2 mb-3" method="GET" action="{{ route('admin.contests.show', $contest) }}">
                        <div class="col">
                            <input class="form-control" name="problem_search" value="{{ request('problem_search') }}" placeholder="Filter by code or title">
                        </div>
                        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
                    </form>
                    <form method="POST" action="{{ route('admin.contests.problems.assign', $contest) }}">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label" for="problem_id">Problem</label>
                                <select class="form-select" name="problem_id" id="problem_id" required>
                                    <option value="">Select a problem</option>
                                    @foreach ($availableProblems as $problem)
                                        <option value="{{ $problem->id }}">{{ $problem->code }} — {{ $problem->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="problem-points">Points</label>
                                <input class="form-control" id="problem-points" name="points" type="number" min="0" max="65535" value="100" required>
                            </div>
                            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Assign</button></div>
                        </div>
                    </form>
                </div>
            @endif
        </section>

        <section class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Participating teams</h2>
                <span class="badge text-bg-light">{{ $contest->participations->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Team</th><th>Members</th><th>Username</th><th>Credentials</th>@if ($contest->isDraft())<th></th>@endif</tr></thead>
                    <tbody>
                    @forelse ($contest->participations as $participation)
                        <tr>
                            <td><a href="{{ route('admin.teams.show', $participation->team) }}">{{ $participation->team->name }}</a></td>
                            <td>{{ $participation->team->members->count() }}</td>
                            <td><code>{{ $participation->username }}</code></td>
                            <td>
                                @if ($contest->isDraft() || $contest->isApproved())
                                    <form method="POST" target="_blank" action="{{ route('admin.contests.teams.credentials', [$contest, $participation]) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary" type="submit">
                                            {{ $participation->hasGeneratedCredentials() ? 'Regenerate / print' : 'Generate / print' }}
                                        </button>
                                    </form>
                                @else
                                    {{ $participation->credentials_generated_at?->format('M j, H:i') ?? 'Not generated' }}
                                @endif
                            </td>
                            @if ($contest->isDraft())
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.contests.teams.unassign', [$contest, $participation]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Unassign</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $contest->isDraft() ? 5 : 4 }}" class="text-center text-muted py-4">No teams assigned.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($contest->isDraft())
                <div class="card-body border-top">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h3 class="h6 mb-1">Assign teams from the team bank</h3>
                            <p class="small text-muted mb-0">Filter by name, select one or several teams, then assign.</p>
                        </div>
                    </div>
                    <form class="row g-2 mb-3" method="GET" action="{{ route('admin.contests.show', $contest) }}">
                        <div class="col">
                            <input class="form-control" name="team_search" value="{{ request('team_search') }}" placeholder="Filter teams by name">
                        </div>
                        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
                    </form>
                    <form method="POST" action="{{ route('admin.contests.teams.assign', $contest) }}">
                        @csrf
                        <div class="border rounded p-2 mb-3" style="max-height: 16rem; overflow-y: auto">
                            @forelse ($availableTeams as $team)
                                <label class="d-flex align-items-center gap-2 border-bottom py-2">
                                    <input class="form-check-input m-0" type="checkbox" name="team_ids[]" value="{{ $team->id }}">
                                    <span class="flex-grow-1">{{ $team->name }}</span>
                                    <span class="small text-muted">{{ $team->members_count }} members</span>
                                </label>
                            @empty
                                <p class="text-muted p-2 mb-0">No matching unassigned teams.</p>
                            @endforelse
                        </div>
                        <button class="btn btn-primary" type="submit">Assign selected teams</button>
                    </form>
                </div>
            @endif
        </section>

        @if ($contest->isRunning() || $contest->isEnded())
            <section class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Submissions</h2>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.contests.submissions.index', $contest) }}">Manage all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Team</th><th>Problem</th><th>Language</th><th>Status</th><th>Submitted</th></tr></thead>
                        <tbody>
                        @forelse ($submissions as $submission)
                            <tr>
                                <td><a href="{{ route('admin.teams.show', $submission->participation->team) }}">{{ $submission->participation->team->name }}</a></td>
                                <td>{{ $submission->contestProblem->label }} · {{ $submission->contestProblem->problem->title }}</td>
                                <td>{{ $submission->language }}</td>
                                <td>{{ $submission->verdict ?? ucfirst($submission->status) }}</td>
                                <td><a href="{{ route('admin.contests.submissions.show', [$contest, $submission]) }}">{{ $submission->submitted_at->format('H:i:s') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No submissions yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($contest->isRunning() || $contest->isEnded())
            <section class="card shadow-sm mb-4">
                <div class="card-header"><h2 class="h5 mb-0">Questions</h2></div>
                <div class="list-group list-group-flush">
                    @forelse ($contest->questions as $question)
                        <a class="list-group-item list-group-item-action d-flex justify-content-between gap-3"
                           href="{{ route('admin.contests.questions.show', [$contest, $question]) }}">
                            <span class="text-truncate">{{ \Illuminate\Support\Str::limit($question->body, 100) }}
                                <span class="small text-muted">· {{ $question->participation->team->name }}</span>
                            </span>
                            <span class="badge text-bg-{{ $question->isAnswered() ? 'success' : 'warning' }}">{{ $question->isAnswered() ? 'Answered' : 'Open' }}</span>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">No questions.</div>
                    @endforelse
                </div>
            </section>
        @endif
    </div>

    <div class="col-xl-5">
        <section class="card shadow-sm mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Contest actions</h2></div>
            <div class="card-body d-grid gap-2">
                @if ($contest->isDraft())
                    <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                       href="{{ route('admin.contests.problems.print', $contest) }}">Print contest problems</a>
                    <form method="POST" target="_blank" action="{{ route('admin.contests.credentials', $contest) }}">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit">Generate / print all credentials</button>
                    </form>
                    <form method="POST" action="{{ route('admin.contests.approve', $contest) }}">
                        @csrf
                        <button class="btn btn-primary w-100" type="submit"
                                onclick="return confirm('Close registration and approve this contest?')">
                            Close registration and approve
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.contests.destroy', $contest) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger w-100" type="submit"
                                onclick="return confirm('Delete this draft contest?')">Delete draft</button>
                    </form>
                @elseif ($contest->isApproved())
                    <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                       href="{{ route('admin.contests.problems.print', $contest) }}">Print contest problems</a>
                    <form method="POST" target="_blank" action="{{ route('admin.contests.credentials', $contest) }}">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit">Regenerate / print all credentials</button>
                    </form>
                    <form method="POST" action="{{ route('admin.contests.start', $contest) }}">
                        @csrf
                        <button class="btn btn-success w-100" type="submit"
                                onclick="return confirm('Start the contest now?')">Start contest now</button>
                    </form>
                @elseif ($contest->isRunning())
                    <form class="border rounded p-3" method="POST" action="{{ route('admin.contests.freeze', $contest) }}">
                        @csrf
                        @method('PUT')
                        <label class="form-label" for="freeze-control">Freeze minutes before end</label>
                        <div class="input-group">
                            <input class="form-control" id="freeze-control" name="freeze-minutes" type="number"
                                   min="0" max="65535" value="{{ $contest->{'freeze-minutes'} }}" required>
                            <button class="btn btn-outline-primary" type="submit">Save</button>
                        </div>
                        <div class="form-text">Set 0 to unfreeze the scoreboard.</div>
                    </form>
                    <form method="POST" action="{{ route('admin.contests.freeze-now', $contest) }}">
                        @csrf
                        <button class="btn btn-outline-warning w-100" type="submit">Freeze scoreboard now</button>
                    </form>
                    <form method="POST" action="{{ route('admin.contests.end', $contest) }}">
                        @csrf
                        <button class="btn btn-danger w-100" type="submit"
                                onclick="return confirm('End the contest now?')">End contest</button>
                    </form>
                @else
                    <p class="text-muted small mb-0">This contest is read-only.</p>
                @endif
            </div>
        </section>

        @if ($contest->isRunning() || $contest->isEnded())
            <section class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Scoreboard</h2>
                    <div class="d-flex gap-2">
                        @if ((int) $contest->{'freeze-minutes'} > 0)
                            <span class="badge text-bg-warning">Frozen</span>
                        @endif
                        <span class="badge text-bg-{{ $contest->is_public ? 'success' : 'secondary' }}">{{ $contest->is_public ? 'Public' : 'Private' }}</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>#</th><th>Team</th><th>Score</th><th>Solved</th><th>Penalty</th></tr></thead>
                        <tbody>
                        @forelse ($scoreboard as $rank => $row)
                            <tr>
                                <td>{{ $rank + 1 }}</td>
                                <td><a href="{{ route('admin.teams.show', $row['team']) }}">{{ $row['team']->name }}</a></td>
                                <td>{{ $row['points'] }}</td>
                                <td>{{ $row['solved'] }}</td>
                                <td>{{ $row['penalty'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No teams.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</div>

@if ($contest->isDraft())
    <div class="alert alert-info mt-2 mb-0">
        Approval requires at least one problem, at least one team, and no other approved or running contest.
    </div>
@endif
@endsection
