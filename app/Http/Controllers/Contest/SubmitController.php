<?php

namespace App\Http\Controllers\Contest;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Submission;
use App\Services\JudgeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

final class SubmitController extends Controller
{
    public function store(Request $request, Contest $contest, string $label, JudgeClient $judge)
    {
        $participation = Auth::guard('contestant')->user();
        abort_unless($participation && $participation->contest_id === $contest->id, 403);
        abort_unless($contest->acceptsSubmissions(), 403, 'Contest is not running.');

        $contestProblem = $contest->contestProblems()
            ->where('label', strtoupper($label))
            ->firstOrFail();

        // Rate limit: 1 submission per 3 seconds per participation.
        $rateKey = "submit:{$participation->id}";
        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            return back()->withErrors([
                'rate' => 'Please wait ' . RateLimiter::availableIn($rateKey) . 's before submitting again.',
            ]);
        }
        RateLimiter::hit($rateKey, 3);

        // Validate: source OR source_file, exactly one required.
        $request->validate([
            'language'    => ['required', 'string', 'max:32'],
            'source'      => ['nullable', 'string', 'max:200000', 'required_without:source_file'],
            'source_file' => ['nullable', 'file', 'max:2048', 'required_without:source'],
        ]);

        $sourceCode = $request->filled('source')
            ? $request->input('source')
            : $request->file('source_file')->get();

        // Verify language is supported by the judge.
        $languages = collect($judge->languages())
            ->pluck('name')
            ->map(fn ($n) => strtolower($n))
            ->all();

        if (! in_array(strtolower($request->input('language')), $languages, true)) {
            return back()->withErrors(['language' => 'Selected language is not available.']);
        }

        $disk = config('filesystems.sources_disk', 'local');
        $path = "sources/{$contest->id}/{$participation->id}/" . uniqid('sub_', true) . '.txt';
        Storage::disk($disk)->put($path, $sourceCode);

        $submission = Submission::create([
            'contest_id'          => $contest->id,
            'participation_id'    => $participation->id,
            'contest_problem_id'  => $contestProblem->id,
            'language'            => strtolower($request->input('language')),
            'source_path'         => $path,
            'status'              => Submission::STATUS_QUEUED,
            'is_sample_run'       => false,
            'submitted_at'        => now(),
        ]);

        try {
            $response = $judge->submit($submission);
            $submission->update([
                'judge_job_id' => $response['job_id'],
                'status'       => Submission::STATUS_JUDGING,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $submission->update([
                'status'  => Submission::STATUS_ERROR,
                'verdict' => 'IE',
            ]);

            return redirect()
                ->route('contest.submissions.show', [$contest->slug, $submission])
                ->withErrors(['submit' => 'Judge is unreachable. Submission recorded but could not be judged.']);
        }

        return redirect()
            ->route('contest.submissions.show', [$contest->slug, $submission])
            ->with('status', 'Submission queued for judging.');
    }
}
