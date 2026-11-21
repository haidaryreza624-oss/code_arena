@extends('layouts.admin')

@section('title', 'Contests')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Contests</h1>
        <p class="text-muted mb-0">Create contests and manage their lifecycle.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('admin.contests.create') }}">Create contest</a>
</div>

<form class="row g-2 mb-3" method="GET" action="{{ route('admin.contests.index') }}">
    <div class="col-md-4">
        <select class="form-select" name="status">
            <option value="">All statuses</option>
            @foreach (['draft', 'approved', 'running', 'ended'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
    @if (request()->filled('status'))
        <div class="col-auto"><a class="btn btn-link" href="{{ route('admin.contests.index') }}">Clear</a></div>
    @endif
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Contest</th><th>Status</th><th>Schedule</th><th>Problems</th><th>Teams</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($contests as $contest)
                <tr>
                    <td>
                        <strong>{{ $contest->name }}</strong>
                        <div class="small text-muted"><code>{{ $contest->slug }}</code></div>
                    </td>
                    <td><span class="badge text-bg-{{ $contest->status === 'running' ? 'success' : ($contest->status === 'approved' ? 'primary' : ($contest->status === 'ended' ? 'secondary' : 'warning')) }}">{{ ucfirst($contest->status) }}</span></td>
                    <td>
                        <time class="local-datetime" datetime="{{ $contest->start_time->toIso8601String() }}">{{ $contest->start_time->format('M j, Y H:i') }} UTC</time>
                        –
                        <time class="local-datetime" datetime="{{ $contest->end_time->toIso8601String() }}">{{ $contest->end_time->format('M j, Y H:i') }} UTC</time>
                    </td>
                    <td>{{ $contest->contest_problems_count }}</td>
                    <td>{{ $contest->participations_count }}</td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.contests.show', $contest) }}">Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No contests found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $contests->links() }}</div>
@endsection
