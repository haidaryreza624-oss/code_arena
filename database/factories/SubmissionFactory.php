<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\ContestProblem;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submission>
 *
 * Default creates an "Accepted / done" submission. Use states for other
 * verdicts. For realistic test data where all foreign keys belong to the
 * same contest, use forParticipation($participation, $contestProblem).
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        $jobId = 'job_' . Str::lower(Str::random(12));

        return [
            'contest_id'          => Contest::factory(),
            'participation_id'    => ContestParticipation::factory(),
            'contest_problem_id'  => ContestProblem::factory(),
            'language'            => fake()->randomElement(['python', 'cpp']),
            'source_path'         => 'sources/pending-' . Str::random(16) . '.txt',
            'status'              => Submission::STATUS_DONE,
            'verdict'             => Submission::VERDICT_ACCEPTED,
            'max_time_ms'         => fake()->numberBetween(10, 1500),
            'max_memory_kb'       => fake()->numberBetween(8000, 60000),
            'score'               => null,
            'judge_job_id'        => $jobId,
            'is_sample_run'       => false,
            'submitted_at'        => now()->subMinutes(fake()->numberBetween(1, 60)),
            'judged_at'           => now()->subSeconds(fake()->numberBetween(1, 30)),
            'rejudge_count'       => 0,
            'last_rejudged_at'    => null,
            'last_rejudged_by'    => null,
            'last_judge_check_at' => null,
        ];
    }

    /**
     * Tie every FK to the same contest (realistic case).
     */
    public function forParticipation(
        ContestParticipation $participation,
        ContestProblem $contestProblem,
    ): static {
        return $this->state(fn () => [
            'contest_id'         => $participation->contest_id,
            'participation_id'   => $participation->id,
            'contest_problem_id' => $contestProblem->id,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status'        => Submission::STATUS_DONE,
            'verdict'       => Submission::VERDICT_ACCEPTED,
            'max_time_ms'   => fake()->numberBetween(10, 800),
            'max_memory_kb' => fake()->numberBetween(8000, 40000),
            'judged_at'     => now(),
        ]);
    }

    public function wrongAnswer(): static
    {
        return $this->state(fn () => [
            'status'        => Submission::STATUS_DONE,
            'verdict'       => Submission::VERDICT_WRONG_ANSWER,
            'judged_at'     => now(),
        ]);
    }

    public function timeLimit(): static
    {
        return $this->state(fn () => [
            'status'        => Submission::STATUS_DONE,
            'verdict'       => Submission::VERDICT_TIME_LIMIT,
            'judged_at'     => now(),
        ]);
    }

    public function compileError(): static
    {
        return $this->state(fn () => [
            'status'        => Submission::STATUS_DONE,
            'verdict'       => Submission::VERDICT_COMPILE_ERROR,
            'max_time_ms'   => null,
            'max_memory_kb' => null,
            'judged_at'     => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status'              => Submission::STATUS_JUDGING,
            'verdict'             => null,
            'max_time_ms'         => null,
            'max_memory_kb'       => null,
            'judged_at'           => null,
            'last_judge_check_at' => null,
        ]);
    }

    public function sampleRun(): static
    {
        return $this->state(fn () => ['is_sample_run' => true]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Submission $submission) {
            $disk    = config('filesystems.sources_disk', 'local');
            $content = "// fake source\nprint('hello')\n";
            $path    = "sources/{$submission->contest_id}/{$submission->participation_id}/{$submission->id}.txt";

            Storage::disk($disk)->put($path, $content);
            $submission->forceFill(['source_path' => $path])->save();
        });
    }
}
