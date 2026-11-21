<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\ContestProblem;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TestCase;
use App\Services\Judge\JudgeClient;
use App\Services\Judge\JudgeException;
use App\Services\Judge\SubmissionJudgeStatusSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContestController extends Controller
{
    public function index(Request $request): View
    {
        $this->syncActiveContest();

        $contests = Contest::query()
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->withCount(['participations', 'contestProblems'])
            ->orderByRaw("CASE status WHEN 'running' THEN 0 WHEN 'approved' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderByDesc('start_time')
            ->paginate(20)
            ->withQueryString();

        return view('admin.contests.index', compact('contests'));
    }

    public function create(): View
    {
        $this->syncActiveContest();
        abort_if(Contest::activeContest() !== null, 409, 'A contest is already approved or running.');

        return view('admin.contests.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->syncActiveContest();
        abort_if(Contest::activeContest() !== null, 409, 'A contest is already approved or running.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:contests,slug'],
            'start_time' => ['required', 'date', 'after:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'freeze-minutes' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $validated['status'] = Contest::STATUS_DRAFT;
        $validated['is_public'] = $request->boolean('is_public');
        $validated['created_by'] = $request->user('web')->id;

        $contest = Contest::create($validated);

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', 'Draft contest created.');
    }

    public function show(
        Request $request,
        Contest $contest,
        JudgeClient $judge,
        SubmissionJudgeStatusSync $statusSync,
    ): View
    {
        $contest->syncState();
        $judgeErrors = [];
        if ($contest->isRunning()) {
            $pendingSubmissions = $contest->submissions()
                ->whereIn('status', [Submission::STATUS_QUEUED, Submission::STATUS_JUDGING])
                ->whereNotNull('judge_job_id')
                ->limit(50)
                ->get();

            foreach ($pendingSubmissions as $submission) {
                try {
                    $statusSync->refresh($submission, $judge->status($submission->judge_job_id));
                } catch (JudgeException $exception) {
                    $judgeErrors[] = $exception->getMessage();
                }
            }
        }

        $contest->load([
            'creator',
            'contestProblems.problem',
            'participations.team.members',
            'questions.participation.team',
            'questions.answeredBy',
        ]);

        $problemSearch = $request->string('problem_search')->toString();
        $availableProblems = Problem::query()
            ->whereDoesntHave('contestProblems', fn (Builder $query) => $query->where('contest_id', $contest->id))
            ->when($problemSearch !== '', function (Builder $query) use ($problemSearch) {
                $query->where(fn (Builder $query) => $query
                    ->where('code', 'like', "%{$problemSearch}%")
                    ->orWhere('title', 'like', "%{$problemSearch}%"));
            })
            ->orderBy('code')
            ->limit(50)
            ->get();

        $teamSearch = $request->string('team_search')->toString();
        $availableTeams = Team::query()
            ->whereDoesntHave('participations', fn (Builder $query) => $query->where('contest_id', $contest->id))
            ->when($teamSearch !== '', function (Builder $query) use ($teamSearch) {
                $query->where(function (Builder $query) use ($teamSearch) {
                    $query->where('name', 'like', "%{$teamSearch}%")
                        ->orWhereHas('members', fn (Builder $query) => $query
                            ->where('name', 'like', "%{$teamSearch}%")
                            ->orWhere('email', 'like', "%{$teamSearch}%"));
                });
            })
            ->withCount('members')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $submissions = collect();
        if ($contest->isRunning() || $contest->isEnded()) {
            $submissions = $contest->submissions()
                ->with(['participation.team', 'contestProblem.problem'])
                ->latest('submitted_at')
                ->limit(10)
                ->get();
        }

        return view('admin.contests.show', [
            'contest' => $contest,
            'availableProblems' => $availableProblems,
            'availableTeams' => $availableTeams,
            'submissions' => $submissions,
            'scoreboard' => $this->scoreboard($contest),
            'judgeErrors' => array_unique($judgeErrors),
        ]);
    }

    public function update(Request $request, Contest $contest): RedirectResponse
    {
        $this->ensureDraft($contest);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:contests,slug,'.$contest->id],
            'start_time' => ['required', 'date', 'after:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'freeze-minutes' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $validated['is_public'] = $request->boolean('is_public');
        $contest->update($validated);

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', 'Contest information updated.');
    }

    public function destroy(Contest $contest): RedirectResponse
    {
        $this->ensureDraft($contest);
        $contest->delete();

        return redirect()->route('admin.contests.index')
            ->with('success', 'Draft contest deleted.');
    }

    public function assignProblem(Request $request, Contest $contest): RedirectResponse
    {
        $this->ensureDraft($contest);
        $validated = $request->validate([
            'problem_id' => ['required', 'integer', 'exists:problems,id'],
            'points' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        abort_if(
            $contest->contestProblems()->where('problem_id', $validated['problem_id'])->exists(),
            409,
            'That problem is already assigned.'
        );

        $label = $this->nextProblemLabel($contest);
        $sortOrder = (int) $contest->contestProblems()->max('sort_order') + 1;
        $contest->contestProblems()->create([
            ...$validated,
            'label' => $label,
            'sort_order' => $sortOrder,
        ]);

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', "Problem assigned as {$label}.");
    }

    public function unassignProblem(Contest $contest, ContestProblem $contestProblem): RedirectResponse
    {
        $this->ensureDraft($contest);
        abort_unless($contestProblem->contest_id === $contest->id, 404);
        $contestProblem->delete();

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', 'Problem unassigned.');
    }

    public function assignTeams(Request $request, Contest $contest): RedirectResponse
    {
        $this->ensureDraft($contest);
        $validated = $request->validate([
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'distinct', 'exists:teams,id'],
        ]);

        $assigned = 0;
        foreach (Team::whereKey($validated['team_ids'])->get() as $team) {
            if ($contest->participations()->where('team_id', $team->id)->exists()) {
                continue;
            }

            $this->createParticipation($contest, $team);
            $assigned++;
        }

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', "{$assigned} team(s) assigned. Generate credentials to print logins.");
    }

    public function unassignTeam(Contest $contest, ContestParticipation $participation): RedirectResponse
    {
        $this->ensureDraft($contest);
        abort_unless($participation->contest_id === $contest->id, 404);
        $participation->delete();

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', 'Team unassigned.');
    }

    public function generateCredentials(Request $request, Contest $contest, ?ContestParticipation $participation = null): View
    {
        abort_unless($contest->isDraft() || $contest->isApproved(), 423);
        if ($participation !== null) {
            abort_unless($participation->contest_id === $contest->id, 404);
            $participations = collect([$participation]);
        } else {
            $participations = $contest->participations()->with('team')->get();
        }

        $credentials = [];
        DB::transaction(function () use ($contest, $participations, &$credentials) {
            foreach ($participations as $item) {
                $username = $item->username;
                if ($username === '' || $username === null) {
                    $username = 'team-'.$item->team_id;
                    while ($contest->participations()
                        ->where('username', $username)
                        ->whereKeyNot($item->id)
                        ->exists()) {
                        $username = 'team-'.$item->team_id.'-'.Str::lower(Str::random(4));
                    }
                }

                $password = Str::password(14);
                $item->forceFill([
                    'username' => $username,
                    'password' => $password,
                    'credentials_generated_at' => now(),
                ])->save();
                $credentials[] = [
                    'team' => $item->team?->name ?? 'Team '.$item->team_id,
                    'username' => $username,
                    'password' => $password,
                ];
            }
        });

        return view('admin.contests.credentials', compact('contest', 'credentials'));
    }

    public function approve(Request $request, Contest $contest): RedirectResponse
    {
        $this->ensureDraft($contest);
        $this->syncActiveContest();
        abort_if(Contest::activeContest() !== null, 409, 'Another contest is already approved or running.');
        abort_if($contest->contestProblems()->doesntExist(), 422, 'Assign at least one problem before approving.');
        abort_if($contest->participations()->doesntExist(), 422, 'Assign at least one team before approving.');

        $contest->forceFill([
            'status' => Contest::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $request->user('web')->id,
        ])->save();

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', 'Registration closed. Contest approved.');
    }

    public function start(Contest $contest): RedirectResponse
    {
        $contest->syncState();
        abort_unless($contest->isApproved(), 409, 'Only an approved contest can be started.');
        abort_if(Contest::query()->occupiesActiveSlot()->whereKeyNot($contest->id)->exists(), 409, 'Another contest is already active.');
        $contest->manualStart();

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', 'Contest started.');
    }

    public function end(Contest $contest): RedirectResponse
    {
        $contest->syncState();
        abort_unless($contest->isRunning(), 409, 'Only a running contest can be ended.');
        $contest->manualEnd();

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', 'Contest ended.');
    }

    public function updateFreeze(Request $request, Contest $contest): RedirectResponse
    {
        $contest->syncState();
        abort_unless($contest->isRunning(), 409, 'The scoreboard can only be frozen during a running contest.');
        $validated = $request->validate([
            'freeze-minutes' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
        $contest->forceFill(['freeze-minutes' => $validated['freeze-minutes']])->save();

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', 'Scoreboard freeze setting updated.');
    }

    public function freezeNow(Contest $contest): RedirectResponse
    {
        $contest->syncState();
        abort_unless($contest->isRunning(), 409, 'The scoreboard can only be frozen during a running contest.');
        $minutesRemaining = max(0, (int) ceil(now()->diffInSeconds($contest->end_time, false) / 60));
        $contest->forceFill(['freeze-minutes' => min($minutesRemaining, 65535)])->save();

        return redirect()->route('admin.contests.show', $contest)
            ->with('success', 'Scoreboard frozen at the current time.');
    }

    public function printProblems(Contest $contest): View
    {
        $contest->syncState();
        abort_unless($contest->isDraft() || $contest->isApproved(), 409);

        $contest->load(['contestProblems.problem.sampleTestCases']);
        $problems = $contest->contestProblems->map(function (ContestProblem $contestProblem) {
            $problem = $contestProblem->problem;
            $problem->sample_cases = $problem->sampleTestCases->map(fn (TestCase $testCase) => [
                'ordinal' => $testCase->ordinal,
                'input' => $testCase->readInput(),
                'output' => $testCase->readOutput(),
            ])->values();

            return [
                'label' => $contestProblem->label,
                'points' => $contestProblem->points,
                'code' => $problem->code,
                'title' => $problem->title,
                'statement' => $problem->statement_md,
                'sample_cases' => $problem->sample_cases,
            ];
        })->values();
        $teams = $contest->participations()
            ->with('team')
            ->get()
            ->pluck('team.name')
            ->filter()
            ->values();

        return view('admin.contests.print-problems', compact('contest', 'problems', 'teams'));
    }

    private function ensureDraft(Contest $contest): void
    {
        abort_unless($contest->isDraft(), 423, 'Only draft contests can be changed.');
    }

    private function syncActiveContest(): void
    {
        Contest::activeContest()?->syncState();
    }

    private function createParticipation(Contest $contest, Team $team): ContestParticipation
    {
        $username = 'team-'.$team->id;
        while ($contest->participations()->where('username', $username)->exists()) {
            $username = 'team-'.$team->id.'-'.Str::lower(Str::random(4));
        }

        return $contest->participations()->create([
            'team_id' => $team->id,
            'username' => $username,
            'password' => Str::password(14),
        ]);
    }

    private function nextProblemLabel(Contest $contest): string
    {
        $used = $contest->contestProblems()->pluck('label')->all();
        for ($index = 0; $index < 702; $index++) {
            $label = '';
            $number = $index;
            do {
                $label = chr(65 + ($number % 26)).$label;
                $number = intdiv($number, 26) - 1;
            } while ($number >= 0);

            if (! in_array($label, $used, true)) {
                return $label;
            }
        }

        abort(422, 'No contest problem labels are available.');
    }

    /**
     * @return list<array{team: Team, points: int, solved: int, penalty: int}>
     */
    private function scoreboard(Contest $contest): array
    {
        $contestProblems = $contest->contestProblems()->with('problem')->get();
        $rows = [];

        foreach ($contest->participations()->with('team')->get() as $participation) {
            $cutoff = (int) $contest->{'freeze-minutes'} > 0
                ? $contest->end_time->copy()->subMinutes((int) $contest->{'freeze-minutes'})
                : null;

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

            foreach ($contestProblems as $contestProblem) {
                $attempts = $submissions->get($contestProblem->id, collect());
                $wrongAttempts = 0;
                foreach ($attempts as $submission) {
                    if ($submission->verdict === Submission::VERDICT_ACCEPTED) {
                        $points += $contestProblem->points;
                        $solved++;
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
            ];
        }

        usort($rows, fn (array $a, array $b) => [$b['points'], $b['solved'], $a['penalty']] <=> [$a['points'], $a['solved'], $b['penalty']]);

        return $rows;
    }
}
