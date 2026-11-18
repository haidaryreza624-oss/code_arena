<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\Submission;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * ICPC-style scoreboard builder.
 *
 * Ranking rules:
 *   1. Solved count — more is better
 *   2. Penalty — less is better
 *      Penalty per solved problem = minutes from contest start to first AC
 *                                 + 20 × (failed attempts before that AC)
 *   3. Teams with same (solved, penalty) share a rank
 *   4. For display order within a rank, sort by team name (stable, deterministic)
 *
 * Freeze:
 *   - When $frozen = true and the contest has freeze_minutes > 0, submissions
 *     with submitted_at >= (end_time - freeze_minutes) are hidden from the
 *     score computation. The team's score reflects only pre-freeze submissions.
 *   - Problems where a team has a freeze-window submission are marked
 *     'frozen' status (unless already solved before the freeze, in which case
 *     they stay 'solved').
 *   - Admins call with $frozen = false — they always see the truth.
 *   - The cache key is per (contest_id, frozen) so both views coexist.
 *
 * Row shape:
 *   [
 *     'rank'             => int,
 *     'participation_id' => int,
 *     'team_id'          => int,
 *     'team_name'        => string,
 *     'username'         => string,
 *     'solved'           => int,
 *     'penalty'          => int,
 *     'last_ac_at'       => string|null   (ISO8601),
 *     'problems'         => [
 *         'A' => [
 *             'status'              => 'solved' | 'attempted' | 'frozen' | 'none',
 *             'attempts'            => int,        // failed attempts before first AC
 *             'accepted_at_minutes' => int|null,   // minutes from start to AC
 *             'is_frozen'           => bool,       // there was freeze-window activity
 *         ],
 *         ...
 *     ],
 *   ]
 */
final class ScoreboardService
{
    /**
     * Cached scoreboard. TTL is short (5s) to smooth over submission bursts
     * without stale data being visible for long. Contestant polling is 10s,
     * so this is well within the perceived-freshness window.
     */
    public function build(Contest $contest, bool $frozen = false, int $ttlSeconds = 5): array
    {
        $key = self::cacheKey($contest->id, $frozen);

        return Cache::remember(
            $key,
            $ttlSeconds,
            fn () => $this->compute($contest, $frozen),
        );
    }

    public static function cacheKey(int $contestId, bool $frozen = false): string
    {
        return "scoreboard:contest:{$contestId}:" . ($frozen ? 'frozen' : 'unfrozen');
    }

    public function forget(Contest $contest): void
    {
        Cache::forget(self::cacheKey($contest->id, false));
        Cache::forget(self::cacheKey($contest->id, true));
    }

    // ================================================================
    // Core computation
    // ================================================================

    private function compute(Contest $contest, bool $frozen): array
    {
        $startedAt = $contest->started_at ?? $contest->start_time;
        if ($startedAt === null) {
            return [];
        }

        $freezeStart = $this->freezeStart($contest, $frozen);

        $participations = $contest->participations()
            ->with('team')
            ->get();

        $contestProblems = $contest->contestProblems()
            ->orderBy('sort_order')
            ->get();

        $submissions = Submission::query()
            ->where('contest_id', $contest->id)
            ->where('is_sample_run', false)
            ->where('status', Submission::STATUS_DONE)
            ->orderBy('submitted_at')
            ->get(['id', 'participation_id', 'contest_problem_id', 'verdict', 'submitted_at']);

        $byKey = $submissions->groupBy(
            fn (Submission $s) => "{$s->participation_id}:{$s->contest_problem_id}"
        );

        $rows = [];
        foreach ($participations as $participation) {
            $rows[] = $this->buildRow(
                participation: $participation,
                contestProblems: $contestProblems,
                byKey: $byKey,
                startedAt: $startedAt,
                freezeStart: $freezeStart,
            );
        }

        $this->sortRows($rows);
        $this->assignRanks($rows);

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ContestProblem> $contestProblems
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Submission>> $byKey
     */
    private function buildRow(
        \App\Models\ContestParticipation $participation,
        $contestProblems,
        $byKey,
        CarbonInterface $startedAt,
        ?CarbonInterface $freezeStart,
    ): array {
        $row = [
            'rank'             => 0,
            'participation_id' => $participation->id,
            'team_id'          => $participation->team_id,
            'team_name'        => $participation->team?->name ?? $participation->username,
            'username'         => $participation->username,
            'solved'           => 0,
            'penalty'          => 0,
            'last_ac_at'       => null,
            'problems'         => [],
        ];

        foreach ($contestProblems as $cp) {
            $problemSubs = $byKey->get("{$participation->id}:{$cp->id}", collect());

            $attempts = 0;
            $firstAcAt = null;
            $hasFreezeActivity = false;

            foreach ($problemSubs as $sub) {
                /** @var Submission $sub */
                $inFreeze = $freezeStart !== null && $sub->submitted_at->gte($freezeStart);

                if ($inFreeze) {
                    $hasFreezeActivity = true;
                    continue; // doesn't count toward score during freeze
                }

                if ($sub->verdict === Submission::VERDICT_ACCEPTED) {
                    $firstAcAt = $sub->submitted_at;
                    break; // first AC wins; later submissions for this problem don't count
                }

                $attempts++;
            }

            if ($firstAcAt !== null) {
                $minutes = (int) $startedAt->diffInMinutes($firstAcAt);
                $row['solved']++;
                $row['penalty'] += $minutes + (20 * $attempts);

                if ($row['last_ac_at'] === null || $firstAcAt->gt($row['last_ac_at'])) {
                    $row['last_ac_at'] = $firstAcAt;
                }

                $row['problems'][$cp->label] = [
                    'status'              => 'solved',
                    'attempts'            => $attempts,
                    'accepted_at_minutes' => $minutes,
                    'is_frozen'           => $hasFreezeActivity,
                ];
            } elseif ($hasFreezeActivity) {
                $row['problems'][$cp->label] = [
                    'status'              => 'frozen',
                    'attempts'            => $attempts,
                    'accepted_at_minutes' => null,
                    'is_frozen'           => true,
                ];
            } elseif ($attempts > 0) {
                $row['problems'][$cp->label] = [
                    'status'              => 'attempted',
                    'attempts'            => $attempts,
                    'accepted_at_minutes' => null,
                    'is_frozen'           => false,
                ];
            } else {
                $row['problems'][$cp->label] = [
                    'status'              => 'none',
                    'attempts'            => 0,
                    'accepted_at_minutes' => null,
                    'is_frozen'           => false,
                ];
            }
        }

        return $row;
    }

    private function sortRows(array &$rows): void
    {
        usort($rows, function (array $a, array $b): int {
            if ($a['solved'] !== $b['solved']) {
                return $b['solved'] <=> $a['solved'];
            }
            if ($a['penalty'] !== $b['penalty']) {
                return $a['penalty'] <=> $b['penalty'];
            }
            // Deterministic display order within a tie — team name ascending.
            return strcmp($a['team_name'], $b['team_name']);
        });
    }

    private function assignRanks(array &$rows): void
    {
        $prevSolved = null;
        $prevPenalty = null;
        $prevRank = 0;

        foreach ($rows as $i => &$row) {
            $position = $i + 1;

            if ($prevSolved === $row['solved'] && $prevPenalty === $row['penalty']) {
                $row['rank'] = $prevRank;
            } else {
                $row['rank'] = $position;
                $prevRank = $position;
            }

            $prevSolved = $row['solved'];
            $prevPenalty = $row['penalty'];

            $row['last_ac_at'] = $row['last_ac_at']?->toIso8601String();
        }
    }

    private function freezeStart(Contest $contest, bool $frozen): ?CarbonInterface
    {
        if (! $frozen || $contest->freeze_minutes <= 0) {
            return null;
        }

        $end = $contest->ended_at ?? $contest->end_time;
        if ($end === null) {
            return null;
        }

        return $end->copy()->subMinutes($contest->freeze_minutes);
    }
}
