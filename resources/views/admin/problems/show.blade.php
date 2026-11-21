@extends('layouts.admin')

@section('title', $problem->code)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h1 class="h2 mb-0">{{ $problem->title }}</h1>
            <code>{{ $problem->code }}</code>
        </div>
        <p class="text-muted mb-0 mt-1">{{ $problem->time_limit_ms }} ms / {{ $problem->memory_limit_mb }} MB</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.problems.index') }}">Back to problems</a>
</div>

@if ($inUse)
    <div class="alert alert-warning">
        This problem is locked because it is used in a contest. Updates and test-case changes are disabled.
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Statement</div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap">{{ $problem->statement_md }}</pre>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Test cases</span>
                @unless ($inUse)
                    <button class="btn btn-sm btn-primary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#add-test-case">
                        Add test case
                    </button>
                @endunless
            </div>
            <div class="card-body">
                <div id="add-test-case" class="collapse mb-4">
                    <form method="POST" action="{{ route('admin.problems.test-cases.store', $problem) }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" for="ordinal">Ordinal</label>
                                <input class="form-control" id="ordinal" name="ordinal" type="number" min="1"
                                       value="{{ $problem->nextTestCaseOrdinal() }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="kind">Kind</label>
                                <select class="form-select" id="kind" name="kind" required>
                                    <option value="sample">Sample</option>
                                    <option value="hidden">Hidden</option>
                                </select>
                            </div>
                            <div class="col-md-6"></div>
                            <div class="col-md-6">
                                <label class="form-label" for="input">Input</label>
                                <textarea class="form-control font-monospace" id="input" name="input" rows="7" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="output">Output</label>
                                <textarea class="form-control font-monospace" id="output" name="output" rows="7" required></textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit">Save test case</button>
                                <button class="btn btn-outline-secondary" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#add-test-case">Discard</button>
                            </div>
                        </div>
                    </form>
                </div>

                @forelse ($problem->testCases as $testCase)
                    <details class="border rounded mb-2">
                        <summary class="px-3 py-2 d-flex align-items-center gap-2">
                            <span class="fw-semibold">Case {{ $testCase->ordinal }}</span>
                            <span class="badge {{ $testCase->isSample() ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ ucfirst($testCase->kind) }}
                            </span>
                            <span class="text-muted small ms-auto">{{ $testCase->input_size_bytes }} / {{ $testCase->output_size_bytes }} bytes</span>
                        </summary>
                        <div class="border-top p-3">
                            @unless ($inUse)
                                <form method="POST"
                                      action="{{ route('admin.problems.test-cases.update', [$problem, $testCase]) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Ordinal</label>
                                            <input class="form-control" name="ordinal" type="number" min="1"
                                                   value="{{ $testCase->ordinal }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Kind</label>
                                            <select class="form-select" name="kind" required>
                                                <option value="sample" @selected($testCase->isSample())>Sample</option>
                                                <option value="hidden" @selected($testCase->isHidden())>Hidden</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6"></div>
                                        <div class="col-md-6">
                                            <label class="form-label">Input</label>
                                            <textarea class="form-control font-monospace" name="input" rows="7" required>{{ $testCase->input_content }}</textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Output</label>
                                            <textarea class="form-control font-monospace" name="output" rows="7" required>{{ $testCase->output_content }}</textarea>
                                        </div>
                                        <div class="col-12 d-flex gap-2">
                                            <button class="btn btn-primary" type="submit">Save</button>
                                            <button class="btn btn-outline-secondary js-discard-test-case" type="button">Discard</button>
                                            <button class="btn btn-outline-danger ms-auto" type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#delete-test-case-{{ $testCase->id }}">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            @else
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <strong>Input</strong>
                                        <pre class="mt-2 border rounded p-2 mb-0">{{ $testCase->input_content }}</pre>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Output</strong>
                                        <pre class="mt-2 border rounded p-2 mb-0">{{ $testCase->output_content }}</pre>
                                    </div>
                                </div>
                            @endunless
                        </div>
                    </details>

                    @unless ($inUse)
                        <div class="modal fade" id="delete-test-case-{{ $testCase->id }}" tabindex="-1"
                             aria-labelledby="delete-test-case-label-{{ $testCase->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST"
                                          action="{{ route('admin.problems.test-cases.destroy', [$problem, $testCase]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <div class="modal-header">
                                            <h2 class="modal-title fs-5" id="delete-test-case-label-{{ $testCase->id }}">
                                                Delete test case {{ $testCase->ordinal }}?
                                            </h2>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endunless
                @empty
                    <p class="text-muted mb-0">No test cases.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Contest usage</div>
            <div class="card-body">
                @forelse ($problem->contestProblems as $contestProblem)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span>{{ $contestProblem->contest->name }}</span>
                        <span class="badge text-bg-secondary">{{ ucfirst($contestProblem->contest->status) }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0">This problem is not used in any contest.</p>
                @endforelse
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Actions</div>
            <div class="card-body d-flex flex-wrap gap-2">
                @if ($inUse)
                    <button class="btn btn-outline-secondary" type="button" disabled>Edit problem</button>
                    <button class="btn btn-outline-danger" type="button" disabled>Delete problem</button>
                @else
                    <a class="btn btn-outline-primary" href="{{ route('admin.problems.edit', $problem) }}">Edit problem</a>
                    <button class="btn btn-outline-danger" type="button"
                            data-bs-toggle="modal" data-bs-target="#delete-problem">
                        Delete problem
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

@unless ($inUse)
    <div class="modal fade" id="delete-problem" tabindex="-1" aria-labelledby="delete-problem-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.problems.destroy', $problem) }}">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="delete-problem-label">Delete {{ $problem->code }}?</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        This deletes the problem and all of its test-case files.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete problem</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endunless

@push('scripts')
<script>
document.querySelectorAll('.js-discard-test-case').forEach(function (button) {
    button.addEventListener('click', function () {
        const form = button.closest('form');
        form.reset();
        button.closest('details').removeAttribute('open');
    });
});
</script>
@endpush
@endsection
