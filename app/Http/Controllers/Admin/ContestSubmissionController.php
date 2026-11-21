<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Submission;
use App\Models\TestCase;
use App\Services\Judge\JudgeClient;
use App\Services\Judge\JudgeException;
use App\Services\Judge\SubmissionJudgeStatusSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ContestSubmissionController extends Controller
{
    public function index(Request $request, Contest $contest): View
    {
        $contest->syncState();
        abort_unless($contest->isRunning() || $contest->isEnded(), 409);

        $submissions = $contest->submissions()
            ->with(['participation.team', 'contestProblem.problem'])
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('language'), fn (Builder $query) => $query->where('language', $request->input('language')))
            ->when($request->filled('problem_id'), function (Builder $query) use ($request) {
                $query->whereHas('contestProblem', fn (Builder $query) => $query->where('problem_id', $request->integer('problem_id')));
            })
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function (Builder $query) use ($search) {
                    $query->whereHas('participation.team', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('participation', fn (Builder $query) => $query->where('username', 'like', "%{$search}%"));
                });
            })
            ->latest('submitted_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.contests.submissions.index', [
            'contest' => $contest,
            'submissions' => $submissions,
            'problems' => $contest->contestProblems()->with('problem')->get(),
            'languages' => $contest->submissions()->distinct()->orderBy('language')->pluck('language'),
        ]);
    }

    public function show(
        Contest $contest,
        Submission $submission,
        JudgeClient $judge,
        SubmissionJudgeStatusSync $statusSync,
    ): View
    {
        $contest->syncState();
        abort_unless($submission->contest_id === $contest->id, 404);
        $judgeError = null;
        if ($submission->judge_job_id !== null && $submission->isPending()) {
            try {
                $statusSync->refresh($submission, $judge->status($submission->judge_job_id));
            } catch (JudgeException $exception) {
                $judgeError = $exception->getMessage();
            }
        }

        $submission->load([
            'participation.team',
            'contestProblem.problem.testCases',
            'results.testCase',
            'lastRejudgedBy',
        ]);

        return view('admin.contests.submissions.show', compact('contest', 'submission', 'judgeError'));
    }

    public function rejudge(Request $request, Contest $contest, Submission $submission, JudgeClient $judge): RedirectResponse
    {
        $contest->syncState();
        abort_unless($contest->isRunning(), 409, 'Submissions can only be rejudged during a running contest.');
        abort_unless($submission->contest_id === $contest->id, 404);
        abort_if($submission->rejudge_count >= 255, 422, 'The maximum rejudge count has been reached.');

        $submission->loadMissing('contestProblem.problem.testCases');
        $problem = $submission->contestProblem->problem;
        $testCases = $problem->testCases->map(fn (TestCase $testCase) => [
            'stdin' => $testCase->readInput(),
            'expected_output' => $testCase->readOutput(),
        ])->all();

        $job = $judge->submit(
            $submission->language,
            $submission->readSource(),
            $testCases,
            max(1, (int) ceil($problem->time_limit_ms / 1000)),
            $problem->memory_limit_mb * 1024,
        );

        $submission->results()->delete();
        $submission->forceFill([
            'status' => Submission::STATUS_QUEUED,
            'verdict' => null,
            'max_time_ms' => null,
            'max_memory_kb' => null,
            'judge_job_id' => $job['job_id'],
            'judged_at' => null,
            'rejudge_count' => $submission->rejudge_count + 1,
            'last_rejudged_at' => now(),
            'last_rejudged_by' => $request->user('web')->id,
        ])->save();

        return redirect()->route('admin.contests.submissions.show', [$contest, $submission])
            ->with('success', 'Submission sent to the judge again.');
    }

}
