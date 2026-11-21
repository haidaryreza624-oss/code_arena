@extends('layouts.admin')

@section('title', 'Submissions · '.$contest->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Submissions</h1>
        <p class="text-muted mb-0">{{ $contest->name }}</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.contests.show', $contest) }}">Back to contest</a>
</div>

<form id="submission-filters" class="row g-2 mb-3" method="GET"
      action="{{ route('admin.contests.submissions.index', $contest) }}">
    <div class="col-lg-4">
        <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search team or username">
    </div>
    <div class="col-md-4 col-lg-2">
        <select class="form-select" name="status">
            <option value="">All statuses</option>
            @foreach (['queued', 'judging', 'done', 'error'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 col-lg-2">
        <select class="form-select" name="problem_id">
            <option value="">All problems</option>
            @foreach ($problems as $contestProblem)
                <option value="{{ $contestProblem->problem_id }}" @selected((string) request('problem_id') === (string) $contestProblem->problem_id)>
                    {{ $contestProblem->label }} · {{ $contestProblem->problem->title }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 col-lg-2">
        <select class="form-select" name="language">
            <option value="">All languages</option>
            @foreach ($languages as $language)
                <option value="{{ $language }}" @selected(request('language') === $language)>{{ $language }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Submitted</th><th>Team</th><th>Problem</th><th>Language</th><th>Status</th><th>Verdict</th><th></th></tr></thead>
            <tbody>
            @forelse ($submissions as $submission)
                <tr>
                    <td>{{ $submission->submitted_at->format('M j H:i:s') }}</td>
                    <td>{{ $submission->participation->team->name }}</td>
                    <td>{{ $submission->contestProblem->label }} · {{ $submission->contestProblem->problem->title }}</td>
                    <td>{{ $submission->language }}</td>
                    <td>{{ ucfirst($submission->status) }}</td>
                    <td>{{ $submission->verdict ?? '—' }}</td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.contests.submissions.show', [$contest, $submission]) }}">Details</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No matching submissions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $submissions->links() }}</div>

@push('scripts')
<script>
const searchInput = document.querySelector('#submission-filters input[name="search"]');
let searchTimer;
searchInput?.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
        document.getElementById('submission-filters').requestSubmit();
    }, 400);
});
</script>
@endpush
@endsection
