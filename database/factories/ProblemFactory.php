<?php

namespace Database\Factories;

use App\Models\Problem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Problem>
 */
class ProblemFactory extends Factory
{
    protected $model = Problem::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'code'            => strtoupper(fake()->unique()->bothify('???-####')),
            'title'           => $title,
            'statement_md'    => "# {$title}\n\n" . fake()->paragraphs(2, true),
            'time_limit_ms'   => fake()->randomElement([1000, 1500, 2000]),
            'memory_limit_mb' => fake()->randomElement([128, 256, 512]),
            'created_by'      => User::factory(),
        ];
    }
}
