@extends('layouts.team')

@section('title', 'Scoreboard · '.$contest->name)

@if ($contest->isRunning())
    @push('head')
        <meta http-equiv="refresh" content="10">
    @endpush
@endif

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="text-decoration-none" href="{{ route('team.dashboard') }}">← Contest dashboard</a>
        <h1 class="h2 mt-2 mb-1">Scoreboard</h1>
        <p class="text-muted mb-0">{{ $contest->name }} · {{ $contest->isRunning() ? 'Live' : 'Final' }}</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('team.questions') }}">Questions and answers</a>
</div>

@if ($contest->isRunning() && (int) $contest->{'freeze-minutes'} > 0)
    <div class="alert alert-info">The scoreboard is frozen and hides submissions after the freeze cutoff.</div>
@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Rank</th><th>Team</th>@foreach ($contest->contestProblems as $problem)<th>{{ $problem->label }}</th>@endforeach<th>Points</th><th>Solved</th><th>Penalty</th></tr></thead>
            <tbody>
            @forelse ($standings as $index => $standing)
                <tr @class(['table-primary' => $standing['team']?->id === auth('contestant')->user()?->team_id])>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $standing['team']?->name ?? 'Unknown team' }}</td>
                    @foreach ($contest->contestProblems as $problem)
                        <td>{{ $standing['problem_scores'][$problem->id] ?? 0 }}</td>
                    @endforeach
                    <td class="fw-semibold">{{ $standing['points'] }}</td>
                    <td>{{ $standing['solved'] }}</td>
                    <td>{{ $standing['penalty'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $contest->contestProblems->count() + 5 }}" class="text-center text-muted py-4">No teams have joined.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
