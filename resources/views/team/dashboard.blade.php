@extends('layouts.team')

@section('title', $contest->name)

@if ($contest->isApproved() || $contest->isRunning())
    @push('head')
        <meta http-equiv="refresh" content="15">
    @endpush
@endif

@section('content')
@if ($contest->isApproved())
    <div class="d-flex flex-column justify-content-center align-items-center text-center" style="min-height: 75vh">
        <p class="text-uppercase text-primary fw-semibold mb-2">Contest starts in</p>
        <div id="contest-countdown" class="display-1 fw-bold my-4"
             data-remaining-seconds="{{ max(0, (int) now()->diffInSeconds($contest->start_time, false)) }}">--:--:--</div>
        <div id="contest-status-error" class="text-danger small" role="alert"></div>
    </div>
@else
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h1 class="h2 mb-0">{{ $contest->name }}</h1>
                <span class="badge text-bg-{{ $contest->isRunning() ? 'success' : 'secondary' }}">
                    {{ $contest->isRunning() ? 'Running' : 'Ended' }}
                </span>
            </div>
            <p class="text-muted mt-2 mb-0">
                Team: <strong>{{ $participation->team->name }}</strong>
                · <time class="local-datetime" datetime="{{ $contest->start_time->toIso8601String() }}">
                    {{ $contest->start_time->format('M j H:i') }} UTC
                </time>
                —
                <time class="local-datetime" datetime="{{ $contest->end_time->toIso8601String() }}">
                    {{ $contest->end_time->format('M j H:i') }} UTC
                </time>
            </p>
        </div>
        <div class="text-end">
            @if ($contest->isRunning())
                <div class="small text-muted">Time remaining</div>
                <div id="contest-countdown" class="fs-4 fw-semibold"
                     data-remaining-seconds="{{ max(0, (int) now()->diffInSeconds($contest->end_time, false)) }}">--:--:--</div>
            @endif
            <form method="POST" action="{{ route('team.logout') }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary mt-2" type="submit">Log out</button>
            </form>
        </div>
    </header>

    @if ($contest->isEnded())
        <div class="alert alert-secondary">The contest has ended. Submissions and questions are closed.</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Problems</h2>
                    <span class="badge text-bg-light">{{ $problems->count() }}</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($problems as $contestProblem)
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                           href="{{ route('team.problems.show', $contestProblem) }}">
                            <span><span class="badge text-bg-dark me-2">{{ $contestProblem->label }}</span>
                                <strong>{{ $contestProblem->problem->title }}</strong></span>
                            <span class="text-muted">{{ $contestProblem->points }} points</span>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">No problems are assigned.</div>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <div class="d-grid gap-3">
                @if ($contest->is_public)
                    <a class="btn btn-outline-primary" href="{{ route('team.scoreboard') }}">View live scoreboard</a>
                @endif
                <a class="btn btn-outline-primary" href="{{ route('team.questions') }}">
                    {{ $contest->isRunning() ? 'Ask a question / view answers' : 'View answered questions' }}
                </a>
                <section class="card shadow-sm">
                    <div class="card-header"><h2 class="h5 mb-0">Recent submissions</h2></div>
                    <div class="list-group list-group-flush">
                        @forelse ($recentSubmissions as $submission)
                            <a class="list-group-item list-group-item-action d-flex justify-content-between"
                               href="{{ route('team.submissions.show', $submission) }}">
                                <span>{{ $submission->contestProblem->label }} · {{ $submission->contestProblem->problem->title }}</span>
                                <span>{{ $submission->verdict ?? ucfirst($submission->status) }}</span>
                            </a>
                        @empty
                            <div class="list-group-item text-muted">No submissions yet.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
@endif
@endsection

@if ($contest->isApproved() || $contest->isRunning())
    @push('scripts')
        <script>
            (() => {
                const timer = document.getElementById('contest-countdown');
                const statusError = document.getElementById('contest-status-error');
                let remaining = Number(timer?.dataset.remainingSeconds ?? 0);

                const renderCountdown = () => {
                    if (!timer) return;
                    const hours = Math.floor(remaining / 3600);
                    const minutes = Math.floor((remaining % 3600) / 60);
                    const seconds = remaining % 60;
                    timer.textContent = [hours, minutes, seconds]
                        .map((part) => String(part).padStart(2, '0')).join(':');
                    if (remaining > 0) remaining--;
                };
                renderCountdown();
                setInterval(renderCountdown, 1000);

                const checkStatus = async () => {
                    try {
                        const response = await fetch(@json(route('team.status')), {
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store',
                        });
                        if (!response.ok) throw new Error('Status check failed.');
                        const data = await response.json();
                        if (statusError) statusError.textContent = '';
                        if (data.status === 'running' || data.status === 'ended') {
                            window.location.reload();
                            return;
                        }
                        remaining = data.remaining_seconds;
                    } catch (error) {
                        if (statusError) statusError.textContent = 'Contest status check failed. Retrying…';
                    }
                };
                setInterval(checkStatus, 5000);
            })();
        </script>
    @endpush
@endif
