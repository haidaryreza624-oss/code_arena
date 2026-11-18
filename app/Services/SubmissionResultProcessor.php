<?php

namespace App\Services;

use App\Exceptions\JudgeException;
use App\Models\Submission;
use App\Models\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a judge status response to a submission.
 *
 * Handles the three possible states the judge can be in:
 *   - Queued / Running → update last_judge_check_at, return false
 *   - Completed        → write per-test-case results + finalize submission, return true
 *   - Failed           → mark submission as error/IE, return true
 *
 * Idempotent: if the submission is already finalized, returns false without
 * touching the database. Safe to call from the contestant endpoint, the
 * background poller, and the admin rejudge path without coordination.
 *
 * Ordinal mapping: the judge returns test_result entries with a 1-based
 * `test_case` field. We sent test cases sorted by ordinal, so ordinal N in
 * the response corresponds to test cases[N-1] in our sorted collection.
 * Early stopping means the response may have fewer entries than test cases
 * — the missing cases simply get no SubmissionResult row.
 */
final class SubmissionResultProcessor
{
    public function __construct(private readonly JudgeClient $judge)
    {
    }

    /**
     * @param  array<string, mixed>  $judgeResponse
     * @return bool  True if the submission was newly finalized by this call.
     * @throws JudgeException
     */
    public function apply(Submission $submission, array $judgeResponse): bool
    {
        if ($submission->isFinal()) {
            return false;
        }

        $jobStatus = (string) ($judgeResponse['job_status'] ?? '');

        if ($this->judge->jobIsPending($jobStatus)) {
            return $this->handlePending($submission);
        }

        if ($this->judge->jobIsCompleted($jobStatus)) {
            return $this->handleCompleted($submission, $judgeResponse['result'] ?? []);
        }

        if ($this->judge->jobIsFailed($jobStatus)) {
            return $this->handleFailed($submission);
        }

        throw JudgeException::invalidResponse("Unknown job_status: {$jobStatus}");
    }

    // ================================================================
    // Handlers
    // ================================================================

    private function handlePending(Submission $submission): bool
    {
        $submission->forceFill(['last_judge_check_at' => now()])->save();
        return false;
    }

    /**
     * @param  array<string, mixed>|mixed  $result
     */
    private function handleCompleted(Submission $submission, mixed $result): bool
    {
        if (! is_array($result) || empty($result)) {
            // Judge said Completed but provided no result object — treat as internal error.
            $this->markInternalError($submission);
            return true;
        }

        $judgeVerdict = (string) ($result['status'] ?? 'Internal Error');
        $mappedVerdict = $this->judge->mapVerdict($judgeVerdict);
        $testResults = is_array($result['test_results'] ?? null) ? $result['test_results'] : [];

        if ($mappedVerdict === Submission::VERDICT_ACCEPTED && empty($testResults)) {
            Log::warning('Judge reported Accepted with no test_results', [
                'submission_id' => $submission->id,
                'job_id'        => $submission->judge_job_id,
            ]);
        }

        $problem = $submission->contestProblem?->problem;
        if ($problem === null) {
            throw JudgeException::invalidResponse(
                "Submission #{$submission->id} has no problem attached."
            );
        }

        // Load test cases in the same order they were sent to the judge.
        // For sample-only runs, we filtered to sample cases before sending,
        // so we filter here too — the ordinals must match.
        $tcQuery = $problem->testCases()->orderBy('ordinal');
        if ($submission->is_sample_run) {
            $tcQuery->where('kind', TestCase::KIND_SAMPLE);
        }
        $testCases = $tcQuery->get()->values();

        $maxTimeMs   = null;
        $maxMemoryKb = null;

        DB::transaction(function () use (
            $submission,
            $mappedVerdict,
            $testResults,
            $testCases,
            &$maxTimeMs,
            &$maxMemoryKb,
        ) {
            // Clear any prior partial results (idempotency safety if a previous
            // apply() crashed mid-transaction — belt and braces).
            $submission->results()->delete();

            foreach ($testResults as $tr) {
                if (! is_array($tr)) {
                    continue;
                }

                $ordinal = (int) ($tr['test_case'] ?? 0);
                $testCase = $testCases->get($ordinal - 1);

                if ($testCase === null) {
                    Log::warning('Judge returned unknown test_case ordinal', [
                        'submission_id'   => $submission->id,
                        'ordinal'         => $ordinal,
                        'available_count' => $testCases->count(),
                    ]);
                    continue;
                }

                $timeSec  = (float) ($tr['execution_time'] ?? 0);
                $timeMs   = $this->judge->secondsToMs($timeSec);
                $memoryKb = (int) ($tr['memory_usage'] ?? 0);

                $submission->results()->create([
                    'test_case_id' => $testCase->id,
                    'verdict'      => $this->judge->mapVerdict((string) ($tr['status'] ?? 'Internal Error')),
                    'time_ms'      => $timeMs,
                    'memory_kb'    => $memoryKb > 0 ? $memoryKb : null,
                    'message'      => $this->buildMessage($tr),
                ]);

                if ($maxTimeMs === null || $timeMs > $maxTimeMs) {
                    $maxTimeMs = $timeMs;
                }
                if ($memoryKb > 0 && ($maxMemoryKb === null || $memoryKb > $maxMemoryKb)) {
                    $maxMemoryKb = $memoryKb;
                }
            }

            $submission->forceFill([
                'status'              => Submission::STATUS_DONE,
                'verdict'             => $mappedVerdict,
                'max_time_ms'         => $maxTimeMs,
                'max_memory_kb'       => $maxMemoryKb,
                'judged_at'           => now(),
                'last_judge_check_at' => now(),
            ])->save();
        });

        $this->invalidateScoreboardCache($submission);

        return true;
    }

    private function handleFailed(Submission $submission): bool
    {
        $this->markInternalError($submission);
        return true;
    }

    // ================================================================
    // Helpers
    // ================================================================

    private function markInternalError(Submission $submission): void
    {
        $submission->forceFill([
            'status'              => Submission::STATUS_ERROR,
            'verdict'             => Submission::VERDICT_INTERNAL_ERROR,
            'judged_at'           => now(),
            'last_judge_check_at' => now(),
        ])->save();

        $this->invalidateScoreboardCache($submission);
    }

    /**
     * Extract a message from a per-test-case result.
     * Prefers stderr; falls back to a truncated stdout-diff-like hint if any.
     */
    private function buildMessage(array $testResult): ?string
    {
        $stderr = trim((string) ($testResult['stderr'] ?? ''));
        if ($stderr !== '') {
            return mb_substr($stderr, 0, 2000);
        }

        return null;
    }

    private function invalidateScoreboardCache(Submission $submission): void
    {
        Cache::forget(ScoreboardService::cacheKey($submission->contest_id, false));
        Cache::forget(ScoreboardService::cacheKey($submission->contest_id, true));
    }
}
