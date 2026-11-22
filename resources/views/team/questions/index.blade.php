@extends('layouts.team')

@section('title', 'Questions · '.$contest->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a class="text-decoration-none" href="{{ route('team.dashboard') }}">← Contest dashboard</a>
        <h1 class="h2 mt-2 mb-1">Questions and answers</h1>
        <p class="text-muted mb-0">{{ $contest->name }}</p>
    </div>
</div>

@if ($contest->isRunning())
    <section class="card shadow-sm mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Ask the admins</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('team.questions.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="contest_problem_id">Problem</label>
                    <select class="form-select @error('contest_problem_id') is-invalid @enderror"
                            id="contest_problem_id" name="contest_problem_id" required>
                        <option value="">Select a problem</option>
                        @foreach ($problems as $problem)
                            <option value="{{ $problem->id }}" @selected((string) old('contest_problem_id') === (string) $problem->id)>
                                {{ $problem->label }} — {{ $problem->problem->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('contest_problem_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="body">Your question</label>
                    <textarea class="form-control @error('body') is-invalid @enderror" id="body"
                              name="body" rows="4" maxlength="10000" required>{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="visibility">Visibility</label>
                    <select class="form-select @error('visibility') is-invalid @enderror"
                            id="visibility" name="visibility" required>
                        <option value="private" @selected(old('visibility', 'private') === 'private')>Private — only our team and admins</option>
                        <option value="public" @selected(old('visibility') === 'public')>Public — other teams can see after it is answered</option>
                    </select>
                    @error('visibility')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary" type="submit">Send question</button>
            </form>
        </div>
    </section>
@else
    <div class="alert alert-secondary">The contest has ended; new questions are closed.</div>
@endif

<section class="card shadow-sm">
    <div class="card-header"><h2 class="h5 mb-0">Questions visible to your team</h2></div>
    <div class="card-body">
        @forelse ($questions as $question)
            <article class="border-bottom pb-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <strong>
                        {{ $question->contestProblem?->label ?? 'Question' }}
                        @if ($question->contestProblem)
                            · {{ $question->contestProblem->problem->title }}
                        @endif
                    </strong>
                    <span class="small text-muted">
                        {{ $question->participation->team->name }}
                        · {{ $question->created_at->format('M j, H:i') }}
                        · {{ ucfirst($question->visibility) }}
                    </span>
                </div>
                <p class="mb-2" style="white-space: pre-wrap">{{ $question->body }}</p>
                @if ($question->isAnswered())
                    <div class="rounded bg-light p-3">
                        <div class="small fw-semibold text-success mb-1">Admin answer</div>
                        <div style="white-space: pre-wrap">{{ $question->answer }}</div>
                    </div>
                @else
                    <span class="badge text-bg-warning">Awaiting answer</span>
                @endif
            </article>
        @empty
            <p class="text-muted mb-0">No questions are visible yet.</p>
        @endforelse
    </div>
</section>
@endsection
