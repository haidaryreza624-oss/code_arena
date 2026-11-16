<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContestParticipation>
 */
class ContestParticipationFactory extends Factory
{
    protected $model = ContestParticipation::class;

    protected static ?string $passwordHash = null;

    public function definition(): array
    {
        return [
            'contest_id'               => Contest::factory(),
            'team_id'                  => Team::factory(),
            'username'                 => 'team-' . fake()->unique()->numerify('#####'),
            'password'                 => static::$passwordHash ??= Hash::make('password'),
            'seat_number'              => fake()->optional(0.7)->numberBetween(1, 100),
            'credentials_generated_at' => null,
        ];
    }

    public function forContest(Contest $contest): static
    {
        return $this->state(fn () => ['contest_id' => $contest->id]);
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn () => ['team_id' => $team->id]);
    }

    public function withGeneratedCredentials(): static
    {
        return $this->state(fn () => [
            'credentials_generated_at' => now(),
        ]);
    }
}
