<?php

namespace Database\Factories;

use App\Models\Problem;
use App\Models\TestCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TestCase>
 *
 * Writes real files to the configured test_cases disk after creation.
 * Uses placeholder paths/hashes during insert, then updates with actuals.
 */
class TestCaseFactory extends Factory
{
    protected $model = TestCase::class;

    public function definition(): array
    {
        return [
            'problem_id'        => Problem::factory(),
            'ordinal'           => 1,
            'kind'              => TestCase::KIND_SAMPLE,
            // Placeholders — real values are written in afterCreating
            'input_path'        => 'pending',
            'output_path'       => 'pending',
            'input_sha256'      => str_repeat('0', 64),
            'output_sha256'     => str_repeat('0', 64),
            'input_size_bytes'  => 0,
            'output_size_bytes' => 0,
        ];
    }

    public function sample(): static
    {
        return $this->state(fn () => ['kind' => TestCase::KIND_SAMPLE]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['kind' => TestCase::KIND_HIDDEN]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (TestCase $testCase) {
            $disk          = config('filesystems.test_cases_disk', 'local');
            $inputContent  = "input-"  . Str::random(12) . "\n";
            $outputContent = "output-" . Str::random(12) . "\n";

            $inputPath  = "problems/{$testCase->problem_id}/test-cases/{$testCase->id}.in";
            $outputPath = "problems/{$testCase->problem_id}/test-cases/{$testCase->id}.out";

            Storage::disk($disk)->put($inputPath, $inputContent);
            Storage::disk($disk)->put($outputPath, $outputContent);

            $testCase->forceFill([
                'input_path'        => $inputPath,
                'output_path'       => $outputPath,
                'input_sha256'      => hash('sha256', $inputContent),
                'output_sha256'     => hash('sha256', $outputContent),
                'input_size_bytes'  => strlen($inputContent),
                'output_size_bytes' => strlen($outputContent),
            ])->save();
        });
    }
}
