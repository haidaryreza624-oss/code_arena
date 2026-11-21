@extends('layouts.admin')

@section('title', $team->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">{{ $team->name }}</h1>
        @if ($inActiveContest)
            <span class="badge text-bg-warning">Participating in an active contest</span>
        @else
            <span class="badge text-bg-secondary">No active contest</span>
        @endif
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.teams.index') }}">Back to teams</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Members</div><div class="fs-4">{{ $stats['members'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Contests</div><div class="fs-4">{{ $stats['contests'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Active contests</div><div class="fs-4">{{ $stats['active_contests'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Submissions</div><div class="fs-4">{{ $stats['submissions'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Accepted</div><div class="fs-4">{{ $stats['accepted'] }}</div></div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Questions</div><div class="fs-4">{{ $stats['questions'] }}</div></div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Members</span>
                @unless ($inActiveContest)
                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#add-member">
                        Add member
                    </button>
                @endunless
            </div>
            @if ($inActiveContest)
                <div class="card-body pb-0">
                    <div class="alert alert-warning mb-3">
                        Members are locked while this team is participating in an approved or running contest.
                    </div>
                </div>
            @endif
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            @unless ($inActiveContest)
                                <th class="text-end">Actions</th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($team->members as $member)
                        <tr>
                            <td>{{ $member->ordinal }}</td>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            @unless ($inActiveContest)
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#edit-member-{{ $member->id }}">
                                    Edit
                                </button>
                                <form class="d-inline" method="POST"
                                      action="{{ route('admin.teams.members.destroy', [$team, $member]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit"
                                            onclick="return confirm('Delete this member?')">Delete</button>
                                </form>
                            </td>
                            @endunless
                        </tr>
                        @unless ($inActiveContest)
                        <tr>
                            <td colspan="4" class="border-0 p-0">
                                <div class="collapse" id="edit-member-{{ $member->id }}">
                                    <form class="border-top p-3" method="POST"
                                          action="{{ route('admin.teams.members.update', [$team, $member]) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label">Name</label>
                                                <input class="form-control @error('name', 'editMember'.$member->id) is-invalid @enderror"
                                                       name="name"
                                                       value="{{ $errors->getBag('editMember'.$member->id)->any() ? old('name', $member->name) : $member->name }}"
                                                       required>
                                                @error('name', 'editMember'.$member->id)
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Email</label>
                                                <input class="form-control @error('email', 'editMember'.$member->id) is-invalid @enderror"
                                                       name="email" type="email"
                                                       value="{{ $errors->getBag('editMember'.$member->id)->any() ? old('email', $member->email) : $member->email }}"
                                                       required>
                                                @error('email', 'editMember'.$member->id)
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Order</label>
                                                <input class="form-control" name="ordinal" type="number" min="0" value="{{ $member->ordinal }}" required>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button class="btn btn-primary w-100" type="submit">Save</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endunless
                    @empty
                        <tr><td colspan="{{ $inActiveContest ? 3 : 4 }}" class="text-center text-muted py-4">No members.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Contest history</div>
            <div class="card-body">
                @forelse ($team->participations as $participation)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <strong>{{ $participation->contest->name }}</strong>
                            <div class="small text-muted">{{ $participation->username }} · Seat {{ $participation->{'seat-number'} ?? '—' }}</div>
                        </div>
                        <span class="badge text-bg-secondary">{{ ucfirst($participation->contest->status) }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0">No contest participation yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Team information</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.teams.update', $team) }}">
                    @csrf
                    @method('PUT')
                    <label class="form-label" for="team-name-detail">Name</label>
                    <input class="form-control mb-3" id="team-name-detail" name="name"
                           value="{{ old('name', $team->name) }}" maxlength="255" required>
                    <button class="btn btn-primary" type="submit">Save team</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Danger zone</div>
            <div class="card-body">
                @if ($inActiveContest)
                    <p class="text-muted">This team cannot be deleted while participating in an active contest.</p>
                    <button class="btn btn-outline-danger" type="button" disabled>Delete team</button>
                @else
                    <p class="text-muted">Deleting this team removes its participation records and submissions.</p>
                    <form method="POST" action="{{ route('admin.teams.destroy', $team) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger" type="submit"
                                onclick="return confirm('Delete this team and its participation records?')">
                            Delete team
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@unless ($inActiveContest)
<div class="modal fade" id="add-member" tabindex="-1" aria-labelledby="add-member-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.teams.members.store', $team) }}">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="add-member-label">Add team member</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="member-name">Name</label>
                        <input class="form-control @error('name', 'addMember') is-invalid @enderror"
                               id="member-name" name="name" maxlength="255"
                               value="{{ old('name') }}" required>
                        @error('name', 'addMember')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="member-email">Email</label>
                        <input class="form-control @error('email', 'addMember') is-invalid @enderror"
                               id="member-email" name="email" type="email" maxlength="255"
                               value="{{ old('email') }}" required>
                        @error('email', 'addMember')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="form-label" for="member-ordinal">Order</label>
                        <input class="form-control" id="member-ordinal" name="ordinal" type="number" min="0"
                               value="{{ old('ordinal', $team->members->count()) }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add member</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endunless
@push('scripts')
<script>
@unless ($inActiveContest)
@if ($errors->getBag('addMember')->any())
    bootstrap.Modal.getOrCreateInstance(document.getElementById('add-member')).show();
@endif

@foreach ($team->members as $member)
    @if ($errors->getBag('editMember'.$member->id)->any())
        document.getElementById('edit-member-{{ $member->id }}')?.classList.add('show');
    @endif
@endforeach
@endunless
</script>
@endpush
@endsection
