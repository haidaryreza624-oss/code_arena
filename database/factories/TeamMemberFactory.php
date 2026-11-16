<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeamMember>
 */
class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name'    => fake()->name(),
            'email'   => fake()->unique()->safeEmail(),
            'ordinal' => 0,
        ];
    }
}
