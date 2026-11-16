<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'test_case_id',
        'verdict',
        'time_ms',
        'memory_kb',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'time_ms'    => 'integer',
            'memory_kb'  => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    public function isAccepted(): bool
    {
        return $this->verdict === Submission::VERDICT_ACCEPTED;
    }

    /**
     * Whether this result's test-case content may be shown to the
     * contestant who submitted. Sample test cases always show; hidden
     * ones only reveal verdict, time, memory.
     */
    public function contentVisibleToContestant(): bool
    {
        return $this->testCase?->isSample() ?? false;
    }
}
