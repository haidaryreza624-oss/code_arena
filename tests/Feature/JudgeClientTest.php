<?php

namespace Tests\Feature;

use App\Services\Judge\JudgeClient;
use App\Services\Judge\JudgeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JudgeClientTest extends TestCase
{
    public function test_it_submits_source_and_test_cases_to_the_judge(): void
    {
        config(['judge.base_url' => 'http://judge.test']);
        Http::fake([
            'judge.test/api/v1/submissions/' => Http::response([
                'job_id' => 'job_123',
                'job_status' => 'Queued',
            ], 202),
        ]);

        $result = app(JudgeClient::class)->submit(
            'python',
            "print(input())",
            [['stdin' => "hello\n", 'expected_output' => "hello\n"]],
            2,
            128000
        );

        $this->assertSame(['job_id' => 'job_123', 'job_status' => 'Queued'], $result);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'http://judge.test/api/v1/submissions/'
                && $request['language'] === 'python'
                && $request['source_code'] === "print(input())"
                && $request['test_cases'][0]['stdin'] === "hello\n"
                && $request['time_limit'] === 2
                && $request['memory_limit'] === 128000;
        });
    }

    public function test_it_raises_time_limits_below_two_seconds_to_the_minimum(): void
    {
        config(['judge.base_url' => 'http://judge.test']);
        Http::fake([
            'judge.test/api/v1/submissions/' => Http::response([
                'job_id' => 'job_short_limit',
                'job_status' => 'Queued',
            ], 202),
        ]);

        app(JudgeClient::class)->submit('python', 'print(1)', [], 1, 128000);

        Http::assertSent(fn ($request) => $request['time_limit'] === 2);
    }

    public function test_it_fetches_job_status_using_the_encoded_job_id(): void
    {
        config(['judge.base_url' => 'http://judge.test']);
        Http::fake([
            'judge.test/api/v1/submissions/job%2F123' => Http::response([
                'job_id' => 'job/123',
                'job_status' => 'Completed',
                'result' => ['status' => 'Accepted'],
            ]),
        ]);

        $result = app(JudgeClient::class)->status('job/123');

        $this->assertSame('Completed', $result['job_status']);
        $this->assertSame(['status' => 'Accepted'], $result['result']);
    }

    public function test_it_caches_supported_languages_until_explicitly_refreshed(): void
    {
        config(['judge.base_url' => 'http://judge.test']);
        Cache::flush();
        Http::fake([
            'judge.test/api/v1/languages/' => Http::sequence()
                ->push(['languages' => [['name' => 'Python', 'available' => true]]])
                ->push(['languages' => [['name' => 'C++', 'available' => true]]]),
        ]);

        $client = app(JudgeClient::class);
        $this->assertSame('Python', $client->languages()[0]['name']);
        $this->assertSame('Python', $client->languages()[0]['name']);
        $this->assertSame('C++', $client->languages(refresh: true)[0]['name']);

        Http::assertSentCount(2);
    }

    public function test_it_throws_a_clear_exception_for_invalid_responses(): void
    {
        config(['judge.base_url' => 'http://judge.test']);
        Http::fake([
            'judge.test/api/v1/submissions/' => Http::response(['job_status' => 'Queued'], 202),
        ]);

        $this->expectException(JudgeException::class);
        app(JudgeClient::class)->submit('python', 'print(1)', [], 2, 128000);
    }
}
