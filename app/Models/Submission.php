<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Submission extends Model
{
    use HasFactory;

    // ---------- Status constants ----------
    public const STATUS_QUEUED  = 'queued';
    public const STATUS_JUDGING = 'judging';
    public const STATUS_DONE    = 'done';
    public const STATUS_ERROR   = 'error';

    // ---------- Verdict constants ----------
    public const VERDICT_ACCEPTED       = 'AC';
    public const VERDICT_WRONG_ANSWER   = 'WA';
    public const VERDICT_TIME_LIMIT     = 'TLE';
    public const VERDICT_MEMORY_LIMIT   = 'MLE';
    public const VERDICT_RUNTIME_ERROR  = 'RE';
    public const VERDICT_COMPILE_ERROR  = 'CE';
    public const VERDICT_INTERNAL_ERROR = 'IE';

    protected $fillable = [
        'contest_id',
        'participation_id',
        'contest_problem_id',
        'language',
        'source_path',
        'status',
        'verdict',
        'max_time_ms',
        'max_memory_kb',
        'score',
        'judge_job_id',
        'is_sample_run',
        'submitted_at',
        'judged_at',
        'rejudge_count',
        'last_rejudged_at',
        'last_rejudged_by',
    ];

    protected function casts(): array
    {
        return [
            'max_time_ms'      => 'integer',
            'max_memory_kb'    => 'integer',
            'score'            => 'decimal:2',
            'is_sample_run'    => 'boolean',
            'rejudge_count'    => 'integer',
            'submitted_at'     => 'datetime',
            'judged_at'        => 'datetime',
            'last_rejudged_at' => 'datetime',
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
        ];
    }

    // ---------- Relationships ----------

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(ContestParticipation::class, 'participation_id');
    }

    public function contestProblem(): BelongsTo
    {
        return $this->belongsTo(ContestProblem::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SubmissionResult::class);
    }

    public function lastRejudgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_rejudged_by');
    }

    // ---------- Convenience accessors ----------

    public function problem(): ?Problem
    {
        return $this->contestProblem?->problem;
    }

    public function label(): ?string
    {
        return $this->contestProblem?->label;
    }

    public function team(): ?Team
    {
        return $this->participation?->team;
    }

    // ---------- State helpers ----------

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_ERROR], true);
    }

    public function isAccepted(): bool
    {
        return $this->verdict === self::VERDICT_ACCEPTED;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_JUDGING], true);
    }

    /**
     * Duration from contest start (penalty base), in whole minutes.
     */
    public function minutesSinceContestStart(): int
    {
        $start = $this->contest?->start_at ?? $this->contest?->start_time;

        return $start ? (int) $start->diffInMinutes($this->submitted_at) : 0;
    }

    // ---------- Storage helper ----------

    public function readSource(): string
    {
        return Storage::disk(config('filesystems.sources_disk', 'local'))
            ->get($this->source_path);
    }

    // ---------- Scopes ----------

    public function scopeAccepted(Builder $q): Builder
    {
        return $q->where('verdict', self::VERDICT_ACCEPTED);
    }

    public function scopeJudged(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DONE);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_QUEUED, self::STATUS_JUDGING]);
    }

    public function scopeForParticipation(Builder $q, int $participationId): Builder
    {
        return $q->where('participation_id', $participationId);
    }

    public function scopeForContestProblem(Builder $q, int $contestProblemId): Builder
    {
        return $q->where('contest_problem_id', $contestProblemId);
    }

    public function scopeScoreboard(Builder $q, int $contestId): Builder
    {
        return $q
            ->where('contest_id', $contestId)
            ->where('is_sample_run', false)
            ->where('status', self::STATUS_DONE);
    }
}
