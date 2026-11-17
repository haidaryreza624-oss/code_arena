<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\ContestQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContestQuestion>
 */
class ContestQuestionFactory extends Factory
{
    protected $model = ContestQuestion::class;

    public function definition(): array
    {
        return [
            'contest_id'       => Contest::factory(),
            'participation_id' => ContestParticipation::factory(),
            'body'             => fake()->sentence(12) . '?',
            'answer'           => null,
            'answered_by'      => null,
            'answered_at'      => null,
            'visibility'       => ContestQuestion::VISIBILITY_PRIVATE,
        ];
    }

    public function unanswered(): static
    {
        return $this->state(fn () => [
            'answer'      => null,
            'answered_by' => null,
            'answered_at' => null,
        ]);
    }

    public function answeredPublic(): static
    {
        return $this->state(fn () => [
            'answer'      => fake()->paragraph(),
            'answered_at' => now(),
            'visibility'  => ContestQuestion::VISIBILITY_PUBLIC,
        ]);
    }

    public function answeredPrivate(): static
    {
        return $this->state(fn () => [
            'answer'      => fake()->paragraph(),
            'answered_at' => now(),
            'visibility'  => ContestQuestion::VISIBILITY_PRIVATE,
        ]);
    }
}
