<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;

class ContestScoreboard
{
    /**
     * @return list<array{team: \App\Models\Team|null, points: int, solved: int, penalty: int, problem_scores: array<int, int>}>
     */
    public function standings(Contest $contest): array
    {
        $contestProblems = $contest->contestProblems()->with('problem')->get();
        $cutoff = (int) $contest->{'freeze-minutes'} > 0
            ? $contest->end_time->copy()->subMinutes((int) $contest->{'freeze-minutes'})
            : null;
        $rows = [];

        foreach ($contest->participations()->with('team')->get() as $participation) {
            $submissions = $participation->submissions()
                ->where('contest_id', $contest->id)
                ->where('is_sample_run', false)
                ->where('status', Submission::STATUS_DONE)
                ->when($cutoff !== null, fn (Builder $query) => $query->where('submitted_at', '<=', $cutoff))
                ->orderBy('submitted_at')
                ->get()
                ->groupBy('contest_problem_id');

            $points = 0;
            $solved = 0;
            $penalty = 0;
            $problemScores = [];

            foreach ($contestProblems as $contestProblem) {
                $problemScores[$contestProblem->id] = 0;
                $wrongAttempts = 0;
                foreach ($submissions->get($contestProblem->id, collect()) as $submission) {
                    if ($submission->verdict === Submission::VERDICT_ACCEPTED) {
                        $points += $contestProblem->points;
                        $solved++;
                        $problemScores[$contestProblem->id] = $contestProblem->points;
                        $penalty += $submission->minutesSinceContestStart() + (20 * $wrongAttempts);
                        break;
                    }

                    if (in_array($submission->verdict, [
                        Submission::VERDICT_WRONG_ANSWER,
                        Submission::VERDICT_TIME_LIMIT,
                        Submission::VERDICT_MEMORY_LIMIT,
                        Submission::VERDICT_RUNTIME_ERROR,
                    ], true)) {
                        $wrongAttempts++;
                    }
                }
            }

            $rows[] = [
                'team' => $participation->team,
                'points' => $points,
                'solved' => $solved,
                'penalty' => $penalty,
                'problem_scores' => $problemScores,
            ];
        }

        usort($rows, fn (array $a, array $b) => [$b['points'], $b['solved'], $a['penalty']]
            <=> [$a['points'], $a['solved'], $b['penalty']]);

        return $rows;
    }
}
