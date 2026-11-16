<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestQuestion extends Model
{
    use HasFactory;

    public const VISIBILITY_PUBLIC  = 'public';
    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        'contest_id',
        'participation_id',
        'body',
        'answer',
        'answered_by',
        'answered_at',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
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

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    // ---------- State helpers ----------

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    /**
     * Whether a *different* team may see this question+answer.
     * Private stays private regardless of state; public is only
     * visible once answered (unresolved questions don't leak).
     */
    public function isVisibleToOtherTeams(): bool
    {
        return $this->isAnswered() && $this->isPublic();
    }

    // ---------- Scopes ----------

    public function scopeUnanswered(Builder $q): Builder
    {
        return $q->whereNull('answered_at');
    }

    public function scopeAnswered(Builder $q): Builder
    {
        return $q->whereNotNull('answered_at');
    }

    public function scopeVisibleToTeams(Builder $q, int $participationId): Builder
    {
        return $q->where(function (Builder $q) use ($participationId) {
            $q->where('participation_id', $participationId)
              ->orWhere(function (Builder $q) {
                  $q->where('visibility', self::VISIBILITY_PUBLIC)
                    ->whereNotNull('answered_at');
              });
        });
    }
}
