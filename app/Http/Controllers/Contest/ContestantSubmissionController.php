<?php

namespace App\Http\Controllers\Contest;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Submission;
use App\Services\SubmissionResultProcessor;
use App\Services\JudgeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ContestantSubmissionController extends Controller
{
    public function index(Contest $contest)
    {
        $participation = Auth::guard('contestant')->user();

        $submissions = Submission::query()
            ->where('participation_id', $participation->id)
            ->where('is_sample_run', false)
            ->with('contestProblem.problem')
            ->orderByDesc('submitted_at')
            ->paginate(30);

        return view('contest.submissions.index', compact('contest', 'submissions'));
    }

    public function show(Contest $contest, Submission $submission)
    {
        $this->authorizeOwnership($contest, $submission);

        $submission->load([
            'contestProblem.problem',
            'results.testCase',
        ]);

        return view('contest.submissions.show', compact('contest', 'submission'));
    }

    /**
     * AJAX endpoint: polled by the submission detail page.
     * Dedups by last_judge_check_at to avoid hammering the judge when the
     * contestant has multiple tabs open.
     */
    public function status(
        Request $request,
        Contest $contest,
        Submission $submission,
        JudgeClient $judge,
        SubmissionResultProcessor $processor,
    ) {
        $this->authorizeOwnership($contest, $submission);

        // 1. Final → return immediately.
        if ($submission->isFinal()) {
            return $this->statusResponse($submission);
        }

        // 2. Dedup: if checked less than 2 seconds ago, don't re-hit judge.
        if ($submission->last_judge_check_at
            && $submission->last_judge_check_at->gt(now()->subSeconds(2))
        ) {
            return $this->statusResponse($submission);
        }

        // 3. Poll judge.
        try {
            $response = $judge->status($submission->judge_job_id);
        } catch (\Throwable $e) {
            report($e);

            return $this->statusResponse($submission);
        }

        $processor->apply($submission, $response);
        $submission->refresh();

        return $this->statusResponse($submission);
    }

    // ----------------------------------------------------------------

    private function authorizeOwnership(Contest $contest, Submission $submission): void
    {
        $participation = Auth::guard('contestant')->user();

        abort_unless(
            $participation
            && $submission->participation_id === $participation->id
            && $submission->contest_id === $contest->id,
            403
        );
    }

    private function statusResponse(Submission $submission)
    {
        return response()->json([
            'is_final' => $submission->isFinal(),
            'status'   => $submission->status,
            'verdict'  => $submission->verdict,
            'url'      => route('contest.submissions.show', [$submission->contest_id, $submission]),
        ]);
    }
}
