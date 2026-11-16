<?php

namespace Database\Factories;

use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\TestCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubmissionResult>
 */
class SubmissionResultFactory extends Factory
{
    protected $model = SubmissionResult::class;

    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'test_case_id'  => TestCase::factory(),
            'verdict'       => Submission::VERDICT_ACCEPTED,
            'time_ms'       => fake()->numberBetween(10, 1500),
            'memory_kb'     => fake()->numberBetween(8000, 60000),
            'message'       => null,
        ];
    }

    public function forSubmissionAndTestCase(Submission $submission, TestCase $testCase): static
    {
        return $this->state(fn () => [
            'submission_id' => $submission->id,
            'test_case_id'  => $testCase->id,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['verdict' => Submission::VERDICT_ACCEPTED]);
    }

    public function wrongAnswer(): static
    {
        return $this->state(fn () => [
            'verdict' => Submission::VERDICT_WRONG_ANSWER,
            'message' => 'Expected 5, got 6',
        ]);
    }

    public function timeLimit(): static
    {
        return $this->state(fn () => ['verdict' => Submission::VERDICT_TIME_LIMIT]);
    }

    public function runtimeError(): static
    {
        return $this->state(fn () => [
            'verdict' => Submission::VERDICT_RUNTIME_ERROR,
            'message' => 'IndexError: list index out of range',
        ]);
    }
}
