<?php

namespace App\Services;

use App\Exceptions\JudgeException;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * The only class in the application that talks to the FastAPI judge engine.
 *
 * Everything the rest of the app knows about the judge's wire format lives
 * here: endpoint paths, request shape, response shape, unit conversions,
 * and verdict string mapping. If the judge's contract changes, this is the
 * one file to update.
 *
 * Confirmed from the judge API docs:
 *   POST /api/v1/submissions/           → 202 { job_id, job_status }
 *   GET  /api/v1/submissions/{job_id}   → { job_id, job_status, result|null }
 *   GET  /api/v1/languages/             → { languages: [{id, name, available, needs_compilation}] }
 *
 * Verdict strings: Accepted, Wrong Answer, Time Limit Exceeded,
 * Memory Limit Exceeded, Runtime Error, Compilation Error, Internal Error.
 *
 * Job status strings: Queued, Running, Completed, Failed.
 *
 * Units on the wire:
 *   time_limit     seconds (int)
 *   memory_limit   kilobytes (int)
 *   execution_time seconds (float)
 *   memory_usage   kilobytes (int)
 *
 * Units in our database:
 *   problems.time_limit_ms       milliseconds (int)
 *   problems.memory_limit_mb     megabytes (int)
 *   submissions.max_time_ms      milliseconds (int)
 *   submission_results.time_ms   milliseconds (int)
 *   submission_results.memory_kb kilobytes (int)
 *
 * The conversions happen here and nowhere else.
 */
final class JudgeClient
{
    private string $baseUrl;
    private string $submitPath;
    private string $statusPath;
    private string $languagesPath;
    private int $submitTimeout;
    private int $statusTimeout;

    public function __construct()
    {
        $this->baseUrl       = rtrim((string) config('judge.base_url'), '/');
        $this->submitPath    = (string) config('judge.submit_path');
        $this->statusPath    = (string) config('judge.status_path');
        $this->languagesPath = (string) config('judge.languages_path');
        $this->submitTimeout = (int) config('judge.submit_timeout', 10);
        $this->statusTimeout = (int) config('judge.status_timeout', 30);
    }

    // ================================================================
    // Public API
    // ================================================================

    /**
     * Submit a solution to the judge.
     *
     * @return array{job_id: string, job_status: string}
     * @throws JudgeException
     */
    public function submit(Submission $submission): array
    {
        $problem = $submission->contestProblem?->problem;
        if ($problem === null) {
            throw JudgeException::invalidPayload('Submission has no problem attached.');
        }

        $testCases = $this->buildTestCasesPayload($problem, $submission->is_sample_run);
        if (empty($testCases)) {
            throw JudgeException::invalidPayload('Problem has no test cases to send.');
        }

        $source = $submission->readSource();
        if ($source === '') {
            throw JudgeException::invalidPayload('Submission source is empty.');
        }

        $payload = [
            'language'     => $this->normalizeLanguage($submission->language),
            'source_code'  => $source,
            'test_cases'   => $testCases,
            'time_limit'   => $this->msToSeconds($problem->time_limit_ms),
            'memory_limit' => $this->mbToKb($problem->memory_limit_mb),
        ];

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->submitTimeout)
                ->acceptJson()
                ->asJson()
                ->post($this->submitPath, $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw JudgeException::unreachable($e);
        }

        if (! $response->successful()) {
            throw JudgeException::rejected($response->status(), $response->body());
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['job_id'])) {
            throw JudgeException::invalidResponse('Missing job_id in submit response.');
        }

        return [
            'job_id'     => (string) $data['job_id'],
            'job_status' => (string) ($data['job_status'] ?? 'Queued'),
        ];
    }

    /**
     * Query the status of a job.
     *
     * @return array<string, mixed>  Raw judge response — parsed by SubmissionResultProcessor.
     * @throws JudgeException
     */
    public function status(string $jobId): array
    {
        $path = str_replace('{job_id}', rawurlencode($jobId), $this->statusPath);

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->statusTimeout)
                ->acceptJson()
                ->get($path);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw JudgeException::unreachable($e);
        }

        if (! $response->successful()) {
            throw JudgeException::rejected($response->status(), $response->body());
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw JudgeException::invalidResponse('Status response is not JSON.');
        }

        return $data;
    }

    /**
     * Query the list of languages the judge supports.
     *
     * @return array<int, array{id: int, name: string, available: bool, needs_compilation: bool}>
     * @throws JudgeException
     */
    public function languages(): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->statusTimeout)
                ->acceptJson()
                ->get($this->languagesPath);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw JudgeException::unreachable($e);
        }

        if (! $response->successful()) {
            throw JudgeException::rejected($response->status(), $response->body());
        }

        $data = $response->json();
        if (! is_array($data) || ! isset($data['languages']) || ! is_array($data['languages'])) {
            throw JudgeException::invalidResponse('Languages response is malformed.');
        }

        return $data['languages'];
    }

    // ================================================================
    // Verdict mapping
    // ================================================================

    /**
     * Map a judge verdict string to our internal short code.
     */
    public function mapVerdict(string $judgeVerdict): string
    {
        return match ($judgeVerdict) {
            'Accepted'              => Submission::VERDICT_ACCEPTED,
            'Wrong Answer'          => Submission::VERDICT_WRONG_ANSWER,
            'Time Limit Exceeded'   => Submission::VERDICT_TIME_LIMIT,
            'Memory Limit Exceeded' => Submission::VERDICT_MEMORY_LIMIT,
            'Runtime Error'         => Submission::VERDICT_RUNTIME_ERROR,
            'Compilation Error'     => Submission::VERDICT_COMPILE_ERROR,
            'Internal Error'        => Submission::VERDICT_INTERNAL_ERROR,
            default                 => Submission::VERDICT_INTERNAL_ERROR,
        };
    }

    /**
     * Human-readable display string from our internal short code.
     * Used by Blade views; symmetric to mapVerdict().
     */
    public function displayVerdict(?string $shortCode): string
    {
        return match ($shortCode) {
            Submission::VERDICT_ACCEPTED       => 'Accepted',
            Submission::VERDICT_WRONG_ANSWER   => 'Wrong Answer',
            Submission::VERDICT_TIME_LIMIT     => 'Time Limit Exceeded',
            Submission::VERDICT_MEMORY_LIMIT   => 'Memory Limit Exceeded',
            Submission::VERDICT_RUNTIME_ERROR  => 'Runtime Error',
            Submission::VERDICT_COMPILE_ERROR  => 'Compilation Error',
            Submission::VERDICT_INTERNAL_ERROR => 'Internal Error',
            null                               => '—',
            default                            => $shortCode,
        };
    }

    // ================================================================
    // Job status helpers
    // ================================================================

    public function jobIsPending(string $jobStatus): bool
    {
        return in_array($jobStatus, ['Queued', 'Running'], true);
    }

    public function jobIsCompleted(string $jobStatus): bool
    {
        return $jobStatus === 'Completed';
    }

    public function jobIsFailed(string $jobStatus): bool
    {
        return $jobStatus === 'Failed';
    }

    // ================================================================
    // Test case payload construction
    // ================================================================

    /**
     * @return array<int, array{stdin: string, expected_output: string}>
     * @throws JudgeException
     */
    private function buildTestCasesPayload(Problem $problem, bool $samplesOnly): array
    {
        $query = $problem->testCases()->orderBy('ordinal');
        if ($samplesOnly) {
            $query->where('kind', TestCase::KIND_SAMPLE);
        }

        $testCases = $query->get();

        if ($testCases->isEmpty()) {
            throw JudgeException::invalidPayload(
                $samplesOnly
                    ? 'Problem has no sample test cases.'
                    : 'Problem has no test cases.'
            );
        }

        return $testCases->map(function (TestCase $tc) {
            try {
                return [
                    'stdin'           => $tc->readInput(),
                    'expected_output' => $tc->readOutput(),
                ];
            } catch (\Throwable $e) {
                throw JudgeException::invalidPayload(
                    "Failed to read test case #{$tc->ordinal}: {$e->getMessage()}"
                );
            }
        })->all();
    }

    // ================================================================
    // Unit conversions
    // ================================================================

    private function msToSeconds(int $ms): int
    {
        // Ceil so a 1500ms limit doesn't get truncated to 1s.
        return max(1, (int) ceil($ms / 1000));
    }

    private function mbToKb(int $mb): int
    {
        return $mb * 1024;
    }

    /**
     * Public helper — used by SubmissionResultProcessor when converting
     * execution_time (float seconds) back to milliseconds.
     */
    public function secondsToMs(float $seconds): int
    {
        return (int) round($seconds * 1000);
    }

    // ================================================================
    // Language normalization
    // ================================================================

    /**
     * The judge expects lowercase language names ('python', 'cpp').
     * Accept anything our DB might hold and normalize.
     */
    private function normalizeLanguage(string $language): string
    {
        return strtolower(trim($language));
    }
}
