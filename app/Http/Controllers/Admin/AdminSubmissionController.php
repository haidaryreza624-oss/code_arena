<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'contest_id' => ['nullable', 'integer', 'exists:contests,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'problem_id' => ['nullable', 'integer', 'exists:problems,id'],
            'status' => ['nullable', 'in:queued,judging,done,error'],
            'verdict' => ['nullable', 'in:AC,WA,TLE,MLE,RE,CE,IE'],
            'language' => ['nullable', 'string', 'max:32'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sample_run' => ['nullable', 'in:0,1'],
        ]);

        $submissions = Submission::query()
            ->with(['contest', 'participation.team', 'contestProblem.problem'])
            ->when($filters['contest_id'] ?? null, fn (Builder $query, $contestId) => $query->where('contest_id', $contestId))
            ->when($filters['team_id'] ?? null, fn (Builder $query, $teamId) => $query
                ->whereHas('participation', fn (Builder $query) => $query->where('team_id', $teamId)))
            ->when($filters['problem_id'] ?? null, fn (Builder $query, $problemId) => $query
                ->whereHas('contestProblem', fn (Builder $query) => $query->where('problem_id', $problemId)))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['verdict'] ?? null, fn (Builder $query, $verdict) => $query->where('verdict', $verdict))
            ->when($filters['language'] ?? null, fn (Builder $query, $language) => $query->where('language', $language))
            ->when($filters['from'] ?? null, fn (Builder $query, $date) => $query
                ->where('submitted_at', '>=', Carbon::parse($date)->utc()->toDateTimeString()))
            ->when($filters['to'] ?? null, fn (Builder $query, $date) => $query
                ->where('submitted_at', '<=', Carbon::parse($date)->utc()->toDateTimeString()))
            ->when(isset($filters['sample_run']), fn (Builder $query) => $query->where('is_sample_run', $filters['sample_run'] === '1'))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('judge_job_id', 'like', "%{$search}%")
                        ->orWhereHas('contest', fn (Builder $query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%"))
                        ->orWhereHas('participation', fn (Builder $query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhereHas('team', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))
                        ->orWhereHas('contestProblem', fn (Builder $query) => $query
                            ->where('label', 'like', "%{$search}%")
                            ->orWhereHas('problem', fn (Builder $query) => $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%")));

                    if (ctype_digit($search)) {
                        $query->orWhereKey((int) $search);
                    }
                });
            })
            ->latest('submitted_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.submissions.index', [
            'submissions' => $submissions,
            'contests' => Contest::query()
                ->whereHas('submissions')
                ->orderByDesc('start_time')
                ->get(['id', 'name', 'slug']),
            'teams' => Team::query()
                ->whereHas('participations.submissions')
                ->orderBy('name')
                ->get(['id', 'name']),
            'problems' => Problem::query()
                ->whereHas('contestProblems.submissions')
                ->orderBy('code')
                ->get(['id', 'code', 'title']),
            'languages' => Submission::query()->distinct()->orderBy('language')->pluck('language'),
        ]);
    }
}
