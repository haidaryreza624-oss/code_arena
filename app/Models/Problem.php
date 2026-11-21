<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Problem extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'statement_md',
        'time_limit_ms',
        'memory_limit_mb',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'time_limit_ms'   => 'integer',
            'memory_limit_mb' => 'integer',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }

    // ---------- Relationships ----------

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class)->orderBy('ordinal');
    }

    public function sampleTestCases(): HasMany
    {
        return $this->hasMany(TestCase::class)->where('kind', TestCase::KIND_SAMPLE)->orderBy('ordinal');
    }

    public function hiddenTestCases(): HasMany
    {
        return $this->hasMany(TestCase::class)->where('kind', TestCase::KIND_HIDDEN)->orderBy('ordinal');
    }

    public function contestProblems(): HasMany
    {
        return $this->hasMany(ContestProblem::class);
    }

    public function contests(): BelongsToMany
    {
        return $this->belongsToMany(Contest::class, 'contest_problems')
            ->withPivot(['label', 'points', 'sort_order'])
            ->withTimestamps();
    }

    // ---------- Helpers ----------

    /**
     * Problems are locked only while an assigned contest is approved or running.
     */
    public function isInUse(): bool
    {
        return $this->contestProblems()
            ->whereHas('contest', function (Builder $q) {
                $q->whereIn('status', [
                    Contest::STATUS_APPROVED,
                    Contest::STATUS_RUNNING,
                ]);
            })
            ->exists();
    }

    public function nextTestCaseOrdinal(): int
    {
        return (int) $this->testCases()->max('ordinal') + 1;
    }
}
