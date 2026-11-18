<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Submission;
use App\Services\JudgeClient;
use Illuminate\Http\Request;

final class AdminSubmissionController extends Controller
{
    public function index(Request $request, Contest $contest)
    {
        $query = $contest->submissions()
            ->with(['participation.team', 'contestProblem.problem'])
            ->orderByDesc('submitted_at');

        if ($filter = $request->query('status')) {
            if ($filter === 'pending') {
                $query->whereIn('status', [Submission::STATUS_QUEUED, Submission::STATUS_JUDGING]);
            } else {
                $query->where('status', $filter);
            }
        }

        if ($verdict = $request->query('verdict')) {
            $query->where('verdict', $verdict);
        }

        if ($problem = $request->query('problem')) {
            $query->whereHas('contestProblem', fn ($q) => $q->where('label', $problem));
        }

        $submissions = $query->paginate(50)->withQueryString();

        return view('admin.contests.tabs.submissions', compact('contest', 'submissions'));
    }

    public function show(Contest $contest, Submission $submission)
    {
        abort_unless($submission->contest_id === $contest->id, 404);

        $submission->load(['participation.team.members', 'contestProblem.problem', 'results.testCase']);

        return view('admin.submissions.show', compact('contest', 'submission'));
    }

    public function rejudge(Request $request, Contest $contest, Submission $submission, JudgeClient $judge)
    {
        abort_unless($submission->contest_id === $contest->id, 404);
        abort_unless($submission->isFinal(), 422, 'Only finalized submissions can be rejudged.');

        $submission->results()->delete();

        $submission->update([
            'status'           => Submission::STATUS_QUEUED,
            'verdict'          => null,
            'max_time_ms'      => null,
            'max_memory_kb'    => null,
            'score'            => null,
            'judged_at'        => null,
            'rejudge_count'    => $submission->rejudge_count + 1,
            'last_rejudged_at' => now(),
            'last_rejudged_by' => $request->user()->id,
            'last_judge_check_at' => null,
        ]);

        try {
            $response = $judge->submit($submission);
            $submission->update([
                'judge_job_id' => $response['job_id'],
                'status'       => Submission::STATUS_JUDGING,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $submission->update(['status' => Submission::STATUS_ERROR, 'verdict' => 'IE']);

            return back()->withErrors(['rejudge' => 'Judge unreachable — submission marked as Internal Error.']);
        }

        return back()->with('status', 'Submission queued for rejudge.');
    }
}
