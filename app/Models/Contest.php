<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RUNNING  = 'running';
    public const STATUS_ENDED    = 'ended';

    protected $fillable = [
        'slug',
        'name',
        'status',
        'start_time',
        'end_time',
        'start_at',
        'end_at',
        'freeze-minutes',
        'is_public',
        'approved_at',
        'approved_by',
        'created_by',
    ];

    protected $guarded = [
        'id',
        'active_slot',   // generated column — never mass-assign
    ];

    protected function casts(): array
    {
        return [
            'start_time'     => 'datetime',
            'end_time'       => 'datetime',
            'started_at'     => 'datetime',
            'ended_at'       => 'datetime',
            'approved_at'    => 'datetime',
            'is_public'      => 'boolean',
            'freeze_minutes' => 'integer',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
        ];
    }

    // ---------- Relationships ----------

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(ContestParticipation::class);
    }

    public function contestProblems(): HasMany
    {
        return $this->hasMany(ContestProblem::class)->orderBy('sort_order');
    }

    public function problems(): BelongsToMany
    {
        return $this->belongsToMany(Problem::class, 'contest_problems')
            ->withPivot(['label', 'points', 'sort_order'])
            ->withTimestamps()
            ->orderBy('contest_problems.sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ContestQuestion::class);
    }

    // ---------- State helpers ----------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_RUNNING,
            self::STATUS_ENDED,
        ], true);
    }

    public function acceptsSubmissions(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function startTimeLocked(): bool
    {
        return now()->gte($this->start_time);
    }

    public function endTimeLocked(): bool
    {
        return $this->status !== self::STATUS_DRAFT;
    }

    // ---------- Scopes ----------

    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DRAFT);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function scopeRunning(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_RUNNING);
    }

    public function scopeEnded(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ENDED);
    }

    public function scopeOccupiesActiveSlot(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_APPROVED, self::STATUS_RUNNING]);
    }

    /**
     * The one contest currently in approved/running state (or null).
     * Used to block approvals / creations.
     */
    public static function activeContest(): ?self
    {
        return static::query()->occupiesActiveSlot()->first();
    }

    // ---------- State transitions ----------

    /**
     * Called by middleware/scheduler on every request/tick.
     * Idempotent. Only saves when a transition happens.
     */
    public function syncState(): void
    {
        $changed = false;

        if ($this->status === self::STATUS_APPROVED && now()->gte($this->start_time)) {
            $this->status = self::STATUS_RUNNING;

            // Absorb scheduler jitter if we're close to start_time;
            // otherwise use now() (late approval case).
            $this->started_at = now()->diffInMinutes($this->start_time, true) <= 2
                ? $this->start_time
                : now();

            $changed = true;
        }

        if ($this->status === self::STATUS_RUNNING && now()->gte($this->end_time)) {
            $this->status   = self::STATUS_ENDED;
            $this->ended_at = now();
            $changed        = true;
        }

        if ($changed) {
            $this->save();
        }
    }

    /**
     * Manual start (admin override, typically early).
     * Assumes caller has validated state == APPROVED and no other active contest.
     */
    public function manualStart(): void
    {
        $this->status     = self::STATUS_RUNNING;
        $this->started_at = now();
        $this->save();
    }

    /**
     * Manual end (admin override, typically early).
     * Assumes caller has validated state == RUNNING.
     */
    public function manualEnd(): void
    {
        $this->status   = self::STATUS_ENDED;
        $this->ended_at = now();
        $this->save();
    }
}
