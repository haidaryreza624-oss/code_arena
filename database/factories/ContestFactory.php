<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contest>
 *
 * IMPORTANT: The `active_slot` generated column enforces a UNIQUE
 * constraint across APPROVED and RUNNING contests. Do NOT call
 * ->approved()->create() or ->running()->create() more than once
 * in the same test without ending the previous one first — otherwise
 * the second insert throws a unique-constraint violation.
 */
class ContestFactory extends Factory
{
    protected $model = Contest::class;

    public function definition(): array
    {
        $start = now()->addDay();
        $end   = (clone $start)->addHours(3);

        return [
            'slug'           => Str::slug(fake()->unique()->words(3, true)) . '-' . Str::random(4),
            'name'           => fake()->unique()->catchPhrase(),
            'status'         => Contest::STATUS_DRAFT,
            'start_time'     => $start,
            'end_time'       => $end,
            'started_at'     => null,
            'ended_at'       => null,
            'freeze_minutes' => 0,
            'is_public'      => true,
            'approved_at'    => null,
            'approved_by'    => null,
            'created_by'     => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status'      => Contest::STATUS_DRAFT,
            'started_at'  => null,
            'ended_at'    => null,
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status'      => Contest::STATUS_APPROVED,
            'start_time'  => now()->addHour(),
            'end_time'    => now()->addHours(4),
            'started_at'  => null,
            'ended_at'    => null,
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function running(): static
    {
        return $this->state(function () {
            $startedAt = now()->subHour();
            return [
                'status'     => Contest::STATUS_RUNNING,
                'start_time' => $startedAt,
                'end_time'   => $startedAt->copy()->addHours(3),
                'started_at' => $startedAt,
                'ended_at'   => null,
                'approved_at'=> $startedAt->copy()->subHour(),
                'approved_by'=> User::factory(),
            ];
        });
    }

    public function ended(): static
    {
        return $this->state(function () {
            $startedAt = now()->subHours(5);
            return [
                'status'     => Contest::STATUS_ENDED,
                'start_time' => $startedAt,
                'end_time'   => $startedAt->copy()->addHours(3),
                'started_at' => $startedAt,
                'ended_at'   => $startedAt->copy()->addHours(3),
                'approved_at'=> $startedAt->copy()->subHour(),
                'approved_by'=> User::factory(),
            ];
        });
    }
}
