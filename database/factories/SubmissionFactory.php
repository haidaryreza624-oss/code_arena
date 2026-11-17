<?php

namespace Database\Factories;

use App\Models\ContestParticipation;
use App\Models\ContestProblem;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'contest_id'         => ContestParticipation::factory()->create()->contest_id,
            'participation_id'   => ContestParticipation::factory(),
            'contest_problem_id' => ContestProblem::factory(),
            'language'            => fake()->randomElement(['python', 'cpp']),
            'source_path'         => 'sources/pending-' . Str::random(16),
            'status'              => 'done',
            'verdict'             => Submission::VERDICT_ACCEPTED,
            'max_time_ms'         => fake()->numberBetween(10, 1500),
            'max_memory_kb'       => fake()->numberBetween(8000, 60000),
            'score'               => null,
            'judge_job_id'        => 'job_' . Str::lower(Str::random(12)),
            'is_sample_run'       => false,
            'submitted_at'        => now()->subMinutes(fake()->numberBetween(1, 60)),
            'judged_at'           => now(),
            'rejudge_count'       => 0,
            'last_rejudged_at'    => null,
            'last_rejudged_by'    => null,
        ];
    }

    /**
     * Associate submission with a participation
     * and a problem from the same contest.
     */
    public function forParticipation(
        ContestParticipation $participation,
        ContestProblem $contestProblem,
    ): static {
        if ((int) $participation->contest_id !== (int) $contestProblem->contest_id) {
            throw new \InvalidArgumentException(
                'Participation and contest problem must belong to the same contest.'
            );
        }

        return $this->state(fn () => [
            'contest_id'         => $participation->contest_id,
            'participation_id'   => $participation->id,
            'contest_problem_id' => $contestProblem->id,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status'        => 'done',
            'verdict'       => Submission::VERDICT_ACCEPTED,
            'max_time_ms'   => fake()->numberBetween(10, 800),
            'max_memory_kb' => fake()->numberBetween(8000, 40000),
            'judged_at'     => now(),
        ]);
    }

    public function wrongAnswer(): static
    {
        return $this->state(fn () => [
            'status'    => 'done',
            'verdict'   => Submission::VERDICT_WRONG_ANSWER,
            'judged_at' => now(),
        ]);
    }

    public function timeLimit(): static
    {
        return $this->state(fn () => [
            'status'    => 'done',
            'verdict'   => Submission::VERDICT_TIME_LIMIT,
            'judged_at' => now(),
        ]);
    }

    public function compileError(): static
    {
        return $this->state(fn () => [
            'status'        => 'done',
            'verdict'       => Submission::VERDICT_COMPILE_ERROR,
            'max_time_ms'   => null,
            'max_memory_kb' => null,
            'judged_at'     => now(),
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'status'        => 'queued',
            'verdict'       => null,
            'max_time_ms'   => null,
            'max_memory_kb' => null,
            'judged_at'     => null,
            'judge_job_id'  => null,
        ]);
    }

    public function judging(): static
    {
        return $this->state(fn () => [
            'status'        => 'judging',
            'verdict'       => null,
            'max_time_ms'   => null,
            'max_memory_kb' => null,
            'judged_at'     => null,
        ]);
    }

    public function judgeError(): static
    {
        return $this->state(fn () => [
            'status'        => 'error',
            'verdict'       => null,
            'max_time_ms'   => null,
            'max_memory_kb' => null,
            'judged_at'     => null,
        ]);
    }

    public function sampleRun(): static
    {
        return $this->state(fn () => [
            'is_sample_run' => true,
        ]);
    }

    /**
     * Create a source file after the submission is created.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Submission $submission) {
            $disk = config('filesystems.sources_disk', 'local');

            $extension = match ($submission->language) {
                'cpp'    => 'cpp',
                'python' => 'py',
                default  => 'txt',
            };

            $content = match ($submission->language) {
                'cpp' => "#include <iostream>\nusing namespace std;\n\nint main() {\n    cout << \"hello\" << endl;\n    return 0;\n}\n",

                'python' => "print('hello')\n",

                default => '',
            };

            $path = "sources/{$submission->contest_id}/{$submission->participation_id}/{$submission->id}.{$extension}";

            Storage::disk($disk)->put($path, $content);

            $submission->forceFill([
                'source_path' => $path,
            ])->save();
        });
    }
}
