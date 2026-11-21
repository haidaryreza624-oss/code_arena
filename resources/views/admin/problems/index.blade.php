@extends('layouts.admin')

@section('title', 'Problems')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Problems</h1>
        <p class="text-muted mb-0">Problem bank and test cases.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('admin.problems.create') }}">Add problem</a>
</div>

<form class="row g-2 mb-3" method="GET" action="{{ route('admin.problems.index') }}">
    <div class="col-md-6 col-lg-4">
        <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search code or title">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary" type="submit">Search</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Limits</th>
                    <th>Test cases</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($problems as $problem)
                <tr>
                    <td><code>{{ $problem->code }}</code></td>
                    <td>{{ $problem->title }}</td>
                    <td>{{ $problem->time_limit_ms }} ms / {{ $problem->memory_limit_mb }} MB</td>
                    <td>{{ $problem->test_cases_count }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.problems.show', $problem) }}">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No problems found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $problems->links() }}</div>
@endsection
