<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\ContestProblem;
use App\Models\ContestQuestion;
use App\Models\Submission;
use App\Models\TestCase;
use App\Services\ContestScoreboard;
use App\Services\Judge\JudgeClient;
use App\Services\Judge\JudgeException;
use App\Services\Judge\SubmissionJudgeStatusSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContestantController extends Controller
{
    public function dashboard(Request $request): View
    {
        /** @var ContestParticipation $participation */
        $participation = $request->user('contestant');
        /** @var Contest $contest */
        $contest = $request->attributes->get('contest');

        if ($contest->isApproved()) {
            return view('team.dashboard', [
                'contest' => $contest,
                'participation' => $participation,
                'problems' => collect(),
                'recentSubmissions' => collect(),
            ]);
        }

        $problems = $contest->contestProblems()->with('problem')->get();
        $recentSubmissions = $participation->submissions()
            ->with('contestProblem.problem')
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        return view('team.dashboard', compact('contest', 'participation', 'problems', 'recentSubmissions'));
    }

    public function status(Request $request): JsonResponse
    {
        /** @var Contest $contest */
        $contest = $request->attributes->get('contest');
        $contest->syncState();

        return response()->json([
            'status' => $contest->status,
            'start_time' => $contest->start_time->toIso8601String(),
            'end_time' => $contest->end_time->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'remaining_seconds' => match ($contest->status) {
                Contest::STATUS_APPROVED => max(0, (int) now()->diffInSeconds($contest->start_time, false)),
                Contest::STATUS_RUNNING => max(0, (int) now()->diffInSeconds($contest->end_time, false)),
                default => 0,
            },
        ]);
    }

    public function problem(Request $request, ContestProblem $contestProblem): View|RedirectResponse
    {
        /** @var ContestParticipation $participation */
        $participation = $request->user('contestant');
        /** @var Contest $contest */
        $contest = $request->attributes->get('contest');
        abort_unless($contestProblem->contest_id === $contest->id, 404);
        if ($contest->isApproved()) {
            return redirect()->route('team.dashboard');
        }

        $contestProblem->load(['problem.sampleTestCases']);
        $submissions = $participation->submissions()
            ->where('contest_problem_id', $contestProblem->id)
            ->latest('submitted_at')
            ->limit(20)
            ->get();

        return view('team.problems.show', compact('contest', 'contestProblem', 'participation', 'submissions'));
    }

    public function submit(
        Request $request,
        ContestProblem $contestProblem,
        JudgeClient $judge,
    ): RedirectResponse {
        /** @var ContestParticipation $participation */
        $participation = $request->user('contestant');
        /** @var Contest $contest */
        $contest = $request->attributes->get('contest');
        abort_unless($contest->isRunning(), 409, 'Submissions are accepted only while the contest is running.');
        abort_unless($contestProblem->contest_id === $contest->id, 404);

        $validated = $request->validate([
            'source_file' => ['required', 'file', 'max:5120', 'extensions:py,cpp,cc,cxx'],
        ]);
        $extension = strtolower($validated['source_file']->getClientOriginalExtension());
        $language = match ($extension) {
            'py' => 'python',
            'cpp', 'cc', 'cxx' => 'cpp',
            default => abort(422, 'The uploaded file extension is not supported.'),
        };
        $testCases = $contestProblem->problem->testCases()->get();
        if ($testCases->isEmpty()) {
            return back()->withErrors(['source_file' => 'This problem has no test cases and cannot be submitted yet.']);
        }

        $disk = config('filesystems.sources_disk', 'sources');
        $sourcePath = 'contestants/'.$contest->id.'/'.$participation->id.'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk($disk)->put($sourcePath, $validated['source_file']->get())) {
            abort(500, 'The uploaded source file could not be stored.');
        }

        $submission = Submission::create([
            'contest_id' => $contest->id,
            'participation_id' => $participation->id,
            'contest_problem_id' => $contestProblem->id,
            'language' => $language,
            'source_path' => $sourcePath,
            'status' => Submission::STATUS_QUEUED,
            'is_sample_run' => false,
            'submitted_at' => now(),
            'rejudge_count' => 0,
        ]);

        try {
            $job = $judge->submit(
                $language,
                $validated['source_file']->get(),
                $testCases->map(fn (TestCase $testCase) => [
                    'stdin' => $testCase->readInput(),
                    'expected_output' => $testCase->readOutput(),
                ])->all(),
                max(1, (int) ceil($contestProblem->problem->time_limit_ms / 1000)),
                $contestProblem->problem->memory_limit_mb * 1024,
            );
        } catch (JudgeException $exception) {
            $submission->forceFill([
                'status' => Submission::STATUS_ERROR,
                'verdict' => Submission::VERDICT_INTERNAL_ERROR,
                'judged_at' => now(),
            ])->save();

            return redirect()->route('team.submissions.show', $submission)
                ->with('judge_error', $exception->getMessage());
        }

        $submission->forceFill(['judge_job_id' => $job['job_id']])->save();

        return redirect()->route('team.submissions.show', $submission);
    }

    public function submission(
        Request $request,
        Submission $submission,
        JudgeClient $judge,
        SubmissionJudgeStatusSync $statusSync,
    ): View {
        /** @var ContestParticipation $participation */
        $participation = $request->user('contestant');
        /** @var Contest $contest */
        $contest = $request->attributes->get('contest');
        abort_unless(
            $submission->contest_id === $contest->id
                && $submission->participation_id === $participation->id,
            404
        );

        $judgeError = session('judge_error');
        if ($submission->judge_job_id !== null && $submission->isPending()) {
            try {
                $statusSync->refresh($submission, $judge->status($submission->judge_job_id));
            } catch (JudgeException $exception) {
                $judgeError = $exception->getMessage();
            }
        }

        $submission->load(['contestProblem.problem', 'results.testCase']);
        $sampleResults = $submission->results
            ->filter(fn ($result) => $result->testCase?->isSample())
            ->sortBy(fn ($result) => $result->testCase->ordinal)
            ->values();

        return view('team.submissions.show', compact('contest', 'submission', 'sampleResults', 'judgeError'));
    }

    public function scoreboard(Request $request, ContestScoreboard $scoreboard): View
    {
        /** @var Contest $contest */
        $contest = $request->attributes->get('contest');
        abort_unless($contest->isRunning() || $contest->isEnded(), 409);
        abort_unless($contest->is_public, 403, 'The contest scoreboard is private.');

        return view('team.scoreboard', [
            'contest' => $contest,
            'standings' => $scoreboard->standings($contest),
        ]);
    }

    public function questions(Request $request): View|RedirectResponse
    {
        /** @var Contest $contest */
        $contest = $request->attributes->get('contest');
        if ($contest->isApproved()) {
            return redirect()->route('team.dashboard');
        }

        /** @var ContestParticipation $participation */
        $participation = $request->user('contestant');
        $questions = ContestQuestion::query()
            ->where('contest_id', $contest->id)
            ->visibleToTeams($participation->id)
            ->with(['participation.team', 'contestProblem.problem'])
            ->latest()
            ->get();
        $problems = $contest->contestProblems()->with('problem')->get();

        return view('team.questions.index', compact('contest', 'participation', 'questions', 'problems'));
    }

    public function askQuestion(Request $request): RedirectResponse
    {
        /** @var Contest $contest */
        $contest = $request->attributes->get('contest');
        abort_unless($contest->isRunning(), 409, 'Questions can only be asked while the contest is running.');

        $validated = $request->validate([
            'contest_problem_id' => [
                'required',
                'integer',
                Rule::exists('contest_problems', 'id')->where('contest_id', $contest->id),
            ],
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => ['required', 'in:public,private'],
        ]);

        ContestQuestion::create([
            ...$validated,
            'contest_id' => $contest->id,
            'participation_id' => $request->user('contestant')->id,
        ]);

        return redirect()->route('team.questions')->with('success', 'Your question was sent to the admins.');
    }
}
