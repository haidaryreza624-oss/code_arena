<?php

namespace App\Services\Judge;

use App\Models\Submission;
use App\Models\TestCase;

class SubmissionJudgeStatusSync
{
    /**
     * @param  array{job_id: string, job_status: string, result: array<string, mixed>|null}  $status
     */
    public function refresh(Submission $submission, array $status): void
    {
        $jobStatus = strtolower($status['job_status']);
        if (in_array($jobStatus, ['queued', 'running'], true)) {
            $submission->forceFill([
                'status' => $jobStatus === 'running' ? Submission::STATUS_JUDGING : Submission::STATUS_QUEUED,
            ])->save();

            return;
        }

        if ($jobStatus === 'failed') {
            $submission->forceFill([
                'status' => Submission::STATUS_ERROR,
                'verdict' => Submission::VERDICT_INTERNAL_ERROR,
                'judged_at' => now(),
            ])->save();

            return;
        }

        if ($jobStatus !== 'completed' || $status['result'] === null) {
            throw JudgeException::invalidResponse('completed jobs must include a result.');
        }

        $result = $status['result'];
        $verdict = $this->mapVerdict((string) ($result['status'] ?? 'Internal Error'));
        $submission->loadMissing('contestProblem.problem.testCases');
        $testCases = $submission->contestProblem->problem->testCases->keyBy('ordinal');
        $maxTime = 0;
        $maxMemory = 0;

        foreach (($result['test_results'] ?? []) as $testResult) {
            $testCase = $testCases->get((int) ($testResult['test_case'] ?? 0));
            if ($testCase === null) {
                continue;
            }

            $timeMs = isset($testResult['execution_time'])
                ? (int) round(((float) $testResult['execution_time']) * 1000)
                : null;
            $memoryKb = isset($testResult['memory_usage']) ? (int) $testResult['memory_usage'] : null;
            $maxTime = max($maxTime, $timeMs ?? 0);
            $maxMemory = max($maxMemory, $memoryKb ?? 0);
            $submission->results()->updateOrCreate(
                ['test_case_id' => $testCase->id],
                [
                    'verdict' => $this->mapVerdict((string) ($testResult['status'] ?? 'Internal Error')),
                    'time_ms' => $timeMs,
                    'memory_kb' => $memoryKb,
                    'message' => $testResult['stderr'] ?? null,
                ]
            );
        }

        $submission->forceFill([
            'status' => Submission::STATUS_DONE,
            'verdict' => $verdict,
            'max_time_ms' => $maxTime,
            'max_memory_kb' => $maxMemory,
            'judged_at' => now(),
        ])->save();
    }

    private function mapVerdict(string $judgeVerdict): string
    {
        return match (strtolower($judgeVerdict)) {
            'accepted' => Submission::VERDICT_ACCEPTED,
            'wrong answer' => Submission::VERDICT_WRONG_ANSWER,
            'time limit exceeded' => Submission::VERDICT_TIME_LIMIT,
            'memory limit exceeded' => Submission::VERDICT_MEMORY_LIMIT,
            'runtime error' => Submission::VERDICT_RUNTIME_ERROR,
            'compilation error' => Submission::VERDICT_COMPILE_ERROR,
            default => Submission::VERDICT_INTERNAL_ERROR,
        };
    }
}
