@extends('layouts.admin')

@section('title', 'Teams')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Teams</h1>
        <p class="text-muted mb-0">Manage teams, members, and contest participation.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#import-teams">
            Import CSV
        </button>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#create-team">
            Add team
        </button>
    </div>
</div>

<form class="row g-2 mb-3" method="GET" action="{{ route('admin.teams.index') }}">
    <div class="col-md-4">
        <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search team name">
    </div>
    <div class="col-md-4">
        <select class="form-select" name="contest_id">
            <option value="">All contests</option>
            @foreach ($contests as $contest)
                <option value="{{ $contest->id }}" @selected((string) request('contest_id') === (string) $contest->id)>
                    {{ $contest->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Filter</button>
        @if (request()->hasAny(['search', 'contest_id']))
            <a class="btn btn-link" href="{{ route('admin.teams.index') }}">Clear</a>
        @endif
    </div>
</form>

<form id="bulk-team-form" method="POST" action="{{ route('admin.teams.bulk-delete') }}">
    @csrf
    @if (request('search'))
        <input type="hidden" name="search" value="{{ request('search') }}">
    @endif
    @if (request('contest_id'))
        <input type="hidden" name="contest_id" value="{{ request('contest_id') }}">
    @endif

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="form-check">
                <input class="form-check-input" id="select-all-teams" type="checkbox">
                <label class="form-check-label" for="select-all-teams">Select all</label>
            </div>
            <button class="btn btn-sm btn-outline-danger" type="submit"
                    onclick="return confirm('Delete selected teams? Active-contest teams will be skipped.')">
                Delete selected
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 48px"></th>
                        <th>Team</th>
                        <th>Members</th>
                        <th>Contests</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($teams as $team)
                    @php
                        $active = $team->isInActiveContest();
                    @endphp
                    <tr>
                        <td>
                            <input class="form-check-input team-checkbox" name="team_ids[]" type="checkbox"
                                   value="{{ $team->id }}">
                        </td>
                        <td><strong>{{ $team->name }}</strong></td>
                        <td>{{ $team->members_count }}</td>
                        <td>{{ $team->participations->count() }}</td>
                        <td>
                            @if ($active)
                                <span class="badge text-bg-warning">Active contest</span>
                            @else
                                <span class="badge text-bg-secondary">Available</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.teams.show', $team) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No teams found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

<div class="mt-3">{{ $teams->links() }}</div>

<div class="modal fade" id="create-team" tabindex="-1" aria-labelledby="create-team-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.teams.store') }}">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="create-team-label">Add team</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="team-name">Team name</label>
                    <input class="form-control @error('name') is-invalid @enderror" id="team-name"
                           name="name" value="{{ old('name') }}" maxlength="255" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="import-teams" tabindex="-1" aria-labelledby="import-teams-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.teams.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="import-teams-label">Import teams from CSV</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Required header: <code>team_name</code>. Optional headers:
                        <code>member_name</code> and <code>member_email</code>.
                    </p>
                    <label class="form-label" for="csv-file">CSV file</label>
                    <input class="form-control" id="csv-file" name="csv_file" type="file"
                           accept=".csv,.txt,text/csv" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const selectAll = document.getElementById('select-all-teams');
const checkboxes = document.querySelectorAll('.team-checkbox');
selectAll?.addEventListener('change', function () {
    checkboxes.forEach(function (checkbox) {
        checkbox.checked = selectAll.checked;
    });
});
</script>
@endpush
@endsection
