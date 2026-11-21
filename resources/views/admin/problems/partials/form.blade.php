<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label" for="code">Code</label>
        <input class="form-control @error('code') is-invalid @enderror" id="code" name="code"
               value="{{ old('code', $problem->code ?? '') }}" maxlength="20" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-9">
        <label class="form-label" for="title">Title</label>
        <input class="form-control @error('title') is-invalid @enderror" id="title" name="title"
               value="{{ old('title', $problem->title ?? '') }}" maxlength="255" required>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="time_limit_ms">Time limit (milliseconds)</label>
        <input class="form-control" id="time_limit_ms" name="time_limit_ms" type="number" min="1"
               value="{{ old('time_limit_ms', $problem->time_limit_ms ?? 1000) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="memory_limit_mb">Memory limit (MB)</label>
        <input class="form-control" id="memory_limit_mb" name="memory_limit_mb" type="number" min="1"
               value="{{ old('memory_limit_mb', $problem->memory_limit_mb ?? 256) }}" required>
    </div>
    <div class="col-12 mb-2">
        <label class="form-label" for="statement_md">Problem statement (Markdown)</label>
        <textarea class="form-control @error('statement_md') is-invalid @enderror"
                  id="statement_md" name="statement_md" rows="14" required>{{ old('statement_md', $problem->statement_md ?? '') }}</textarea>
        @error('statement_md')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
