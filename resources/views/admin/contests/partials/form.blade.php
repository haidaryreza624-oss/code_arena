<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="name">Contest name</label>
        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name"
               value="{{ old('name', $contest->name ?? '') }}" maxlength="255" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="slug">URL slug</label>
        <input class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug"
               value="{{ old('slug', $contest->slug ?? '') }}" maxlength="100" pattern="[A-Za-z0-9_-]+" required>
        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="start_time_local">Scheduled start (your local time)</label>
        <input id="start_time_utc" name="start_time" type="hidden"
               value="{{ old('start_time', isset($contest) ? $contest->start_time->toIso8601String() : '') }}">
        <input class="form-control @error('start_time') is-invalid @enderror" id="start_time_local"
               data-utc-target="start_time_utc" type="datetime-local" required>
        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="end_time_local">Scheduled end (your local time)</label>
        <input id="end_time_utc" name="end_time" type="hidden"
               value="{{ old('end_time', isset($contest) ? $contest->end_time->toIso8601String() : '') }}">
        <input class="form-control @error('end_time') is-invalid @enderror" id="end_time_local"
               data-utc-target="end_time_utc" type="datetime-local" required>
        @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="freeze-minutes">Scoreboard freeze (minutes before scheduled end)</label>
        <input class="form-control @error('freeze-minutes') is-invalid @enderror" id="freeze-minutes"
               name="freeze-minutes" type="number" min="0" max="65535"
               value="{{ old('freeze-minutes', $contest->{'freeze-minutes'} ?? 0) }}" required>
        @error('freeze-minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check mb-2">
            <input class="form-check-input" id="is_public" name="is_public" type="checkbox" value="1"
                   @checked(old('is_public', $contest->is_public ?? true))>
            <label class="form-check-label" for="is_public">Public scoreboard</label>
        </div>
    </div>
</div>
<p class="form-text">Scheduled times are converted from your browser's local timezone and stored consistently.</p>
