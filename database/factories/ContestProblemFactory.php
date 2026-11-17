<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\ContestProblem;
use App\Models\Problem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContestProblem>
 *
 * Auto-assigns the next free A/B/C/... label within the contest, unless
 * the caller explicitly sets `label`.
 */
class ContestProblemFactory extends Factory
{
    protected $model = ContestProblem::class;

    public function definition(): array
    {
        return [
            'contest_id' => Contest::factory(),
            'problem_id' => Problem::factory(),
            'label'      => '', // filled in afterMaking
            'points'     => 0,
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ContestProblem $cp) {
            // Only auto-fill if the caller didn't set a label.
            if (! empty($cp->label)) {
                return;
            }

            $taken = ContestProblem::query()
                ->where('contest_id', $cp->contest_id)
                ->pluck('label')
                ->all();

            foreach (range('A', 'Z') as $letter) {
                if (! in_array($letter, $taken, true)) {
                    $cp->label = $letter;
                    break;
                }
            }

            if (empty($cp->label)) {
                $cp->label = 'AA'; // fallback for contests with >26 problems
            }
        });
    }
}
