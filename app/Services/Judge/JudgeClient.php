<?php

namespace App\Services\Judge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class JudgeClient
{
    private const MINIMUM_TIME_LIMIT_SECONDS = 2;

    /**
     * @param  list<array{stdin: string, expected_output: string}>  $testCases
     * @return array{job_id: string, job_status: string}
     */
    public function submit(
        string $language,
        string $sourceCode,
        array $testCases,
        int $timeLimit,
        int $memoryLimit,
    ): array {
        $timeLimit = max(self::MINIMUM_TIME_LIMIT_SECONDS, $timeLimit);

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('judge.submit_timeout', 10))
                ->post((string) config('judge.submit_path'), [
                    'language' => $language,
                    'source_code' => $sourceCode,
                    'test_cases' => $testCases,
                    'time_limit' => $timeLimit,
                    'memory_limit' => $memoryLimit,
                ]);
        } catch (ConnectionException $exception) {
            throw JudgeException::unreachable($exception);
        }

        if ($response->status() !== 202) {
            throw JudgeException::requestFailed($response->status());
        }

        $data = $this->jsonObject($response);
        if (! isset($data['job_id'], $data['job_status'])
            || ! is_string($data['job_id'])
            || ! is_string($data['job_status'])) {
            throw JudgeException::invalidResponse('expected string job_id and job_status fields.');
        }

        return [
            'job_id' => $data['job_id'],
            'job_status' => $data['job_status'],
        ];
    }

    /**
     * @return array{job_id: string, job_status: string, result: array<string, mixed>|null}
     */
    public function status(string $jobId): array
    {
        $path = str_replace(
            '{job_id}',
            rawurlencode($jobId),
            (string) config('judge.status_path')
        );

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->timeout((int) config('judge.status_timeout', 30))
                ->get($path);
        } catch (ConnectionException $exception) {
            throw JudgeException::unreachable($exception);
        }

        if (! $response->successful()) {
            throw JudgeException::requestFailed($response->status());
        }

        $data = $this->jsonObject($response);
        if (! isset($data['job_id'], $data['job_status'])
            || ! is_string($data['job_id'])
            || ! is_string($data['job_status'])
            || ! (array_key_exists('result', $data) && (is_array($data['result']) || $data['result'] === null))) {
            throw JudgeException::invalidResponse('expected job_id, job_status, and nullable result fields.');
        }

        return [
            'job_id' => $data['job_id'],
            'job_status' => $data['job_status'],
            'result' => $data['result'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function languages(bool $refresh = false): array
    {
        $cacheKey = 'judge.languages';
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            (int) config('judge.languages_ttl', 300),
            function (): array {
                try {
                    $response = Http::baseUrl($this->baseUrl())
                        ->acceptJson()
                        ->timeout((int) config('judge.status_timeout', 30))
                        ->get((string) config('judge.languages_path'));
                } catch (ConnectionException $exception) {
                    throw JudgeException::unreachable($exception);
                }

                if (! $response->successful()) {
                    throw JudgeException::requestFailed($response->status());
                }

                $data = $this->jsonObject($response);
                if (! isset($data['languages']) || ! is_array($data['languages'])) {
                    throw JudgeException::invalidResponse('expected a languages array.');
                }

                return array_values($data['languages']);
            }
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('judge.base_url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(Response $response): array
    {
        $data = $response->json();
        if (! is_array($data)) {
            throw JudgeException::invalidResponse('expected a JSON object.');
        }

        return $data;
    }
}
