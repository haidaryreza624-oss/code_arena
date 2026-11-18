<?php

namespace App\Http\Controllers\Contest;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestProblem;
use App\Models\Submission;
use Illuminate\Support\Facades\Auth;

final class ContestantProblemController extends Controller
{
    public function index(Contest $contest)
    {
        $participation = Auth::guard('contestant')->user();

        $problems = $contest->contestProblems()
            ->with('problem')
            ->orderBy('sort_order')
            ->get();

        // Map of contest_problem_id => best status for the current team.
        $statusByContestProblem = [];
        if ($participation) {
            $subs = Submission::query()
                ->where('participation_id', $participation->id)
                ->where('is_sample_run', false)
                ->get(['contest_problem_id', 'verdict', 'status']);

            foreach ($subs as $sub) {
                $prev = $statusByContestProblem[$sub->contest_problem_id] ?? null;
                if ($sub->verdict === 'AC') {
                    $statusByContestProblem[$sub->contest_problem_id] = 'solved';
                } elseif ($prev !== 'solved') {
                    $statusByContestProblem[$sub->contest_problem_id] = 'attempted';
                }
            }
        }

        return view('contest.problems.index', compact('contest', 'problems', 'statusByContestProblem'));
    }

    public function show(Contest $contest, string $label)
    {
        $contestProblem = $contest->contestProblems()
            ->with(['problem.sampleTestCases'])
            ->where('label', strtoupper($label))
            ->firstOrFail();

        $participation = Auth::guard('contestant')->user();

        $mySubmissions = $participation
            ? Submission::query()
                ->where('participation_id', $participation->id)
                ->where('contest_problem_id', $contestProblem->id)
                ->where('is_sample_run', false)
                ->orderByDesc('submitted_at')
                ->limit(20)
                ->get()
            : collect();

        return view('contest.problems.show', [
            'contest'          => $contest,
            'contestProblem'   => $contestProblem,
            'problem'          => $contestProblem->problem,
            'samples'          => $contestProblem->problem->sampleTestCases,
            'mySubmissions'    => $mySubmissions,
        ]);
    }
}
