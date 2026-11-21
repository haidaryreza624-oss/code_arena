@extends('layouts.admin')

@section('title', 'Contest question')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Contest question</h1>
        <p class="text-muted mb-0">{{ $contest->name }} · {{ $question->participation->team->name }} · {{ $question->created_at->format('M j, Y H:i') }}</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.contests.show', $contest) }}">Back to contest</a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Question</div>
    <div class="card-body">
        <p class="mb-3" style="white-space: pre-wrap">{{ $question->body }}</p>
        <span class="badge text-bg-{{ $question->isPublic() ? 'success' : 'secondary' }}">{{ ucfirst($question->visibility) }}</span>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Answer</div>
    <div class="card-body">
        @if ($contest->isRunning())
            <form method="POST" action="{{ route('admin.contests.questions.answer', [$contest, $question]) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="answer">Answer</label>
                    <textarea class="form-control @error('answer') is-invalid @enderror" id="answer"
                              name="answer" rows="6" required>{{ old('answer', $question->answer) }}</textarea>
                    @error('answer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="visibility">Visibility</label>
                    <select class="form-select" id="visibility" name="visibility" required>
                        <option value="private" @selected(old('visibility', $question->visibility) === 'private')>Private to this team</option>
                        <option value="public" @selected(old('visibility', $question->visibility) === 'public')>Public after answering</option>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Save answer</button>
            </form>
        @else
            <p class="mb-2" style="white-space: pre-wrap">{{ $question->answer ?? 'Not answered' }}</p>
            <p class="small text-muted mb-0">
                {{ $question->answered_at?->format('M j, Y H:i') ?? 'No answer submitted' }}
                @if ($question->answeredBy) · {{ $question->answeredBy->name }} @endif
                · {{ ucfirst($question->visibility) }}
            </p>
        @endif
    </div>
</div>
@endsection
