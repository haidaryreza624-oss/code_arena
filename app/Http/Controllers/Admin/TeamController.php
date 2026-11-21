<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $teams = Team::query()
            ->withCount('members')
            ->with(['participations.contest'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->filled('contest_id'), function ($query) use ($request) {
                $query->whereHas('participations', function ($query) use ($request) {
                    $query->where('contest_id', $request->integer('contest_id'));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $contests = Contest::query()->orderByDesc('start_time')->get();

        return view('admin.teams.index', compact('teams', 'contests'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
        ]);

        $team = Team::create($validated);

        return redirect()
            ->route('admin.teams.show', $team)
            ->with('success', 'Team created successfully.');
    }

    public function show(Team $team): View
    {
        $team->load([
            'members',
            'participations.contest',
            'participations.submissions',
        ]);

        $participationIds = $team->participations->pluck('id');
        $submissions = $team->participations
            ->flatMap(fn (ContestParticipation $participation) => $participation->submissions);

        $stats = [
            'members' => $team->members->count(),
            'contests' => $team->participations->count(),
            'active_contests' => $team->participations
                ->whereIn('contest.status', [Contest::STATUS_APPROVED, Contest::STATUS_RUNNING])
                ->count(),
            'submissions' => $submissions->count(),
            'accepted' => $submissions->where('verdict', 'AC')->count(),
            'questions' => $participationIds->isEmpty()
                ? 0
                : \App\Models\ContestQuestion::whereIn('participation_id', $participationIds)->count(),
        ];

        return view('admin.teams.show', [
            'team' => $team,
            'stats' => $stats,
            'inActiveContest' => $team->isInActiveContest(),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')->ignore($team->id),
            ],
        ]);

        $team->update($validated);

        return redirect()
            ->route('admin.teams.show', $team)
            ->with('success', 'Team information updated.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        abort_if($team->isInActiveContest(), 423, 'This team is participating in an active contest.');

        DB::transaction(function () use ($team) {
            $team->participations()->delete();
            $team->delete();
        });

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ]);

        $deleted = 0;
        $skipped = 0;

        foreach (Team::whereKey($validated['team_ids'])->get() as $team) {
            if ($team->isInActiveContest()) {
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($team) {
                $team->participations()->delete();
                $team->delete();
            });
            $deleted++;
        }

        $message = "{$deleted} team(s) deleted.";
        if ($skipped > 0) {
            $message .= " {$skipped} active-contest team(s) skipped.";
        }

        return redirect()
            ->route('admin.teams.index', $request->only('contest_id', 'search'))
            ->with('success', $message);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $validated['csv_file']->getRealPath();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return back()->withErrors(['csv_file' => 'The CSV file could not be read.']);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'The CSV file is empty.']);
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
        $headerAliases = array_flip($header);
        $teamIndex = $headerAliases['team_name'] ?? $headerAliases['team'] ?? $headerAliases['name'] ?? null;
        $memberNameIndex = $headerAliases['member_name'] ?? $headerAliases['member'] ?? null;
        $memberEmailIndex = $headerAliases['member_email'] ?? $headerAliases['email'] ?? null;

        if ($teamIndex === null) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV must contain a team_name column.']);
        }

        $createdTeams = 0;
        $createdMembers = 0;
        $skippedRows = 0;
        $skippedActiveTeamMembers = 0;
        $teams = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $teamName = trim((string) ($row[$teamIndex] ?? ''));
            if ($teamName === '') {
                $skippedRows++;
                continue;
            }

            $team = $teams[$teamName] ?? Team::firstOrCreate(['name' => $teamName]);
            if (! isset($teams[$teamName])) {
                $teams[$teamName] = $team;
                $createdTeams += $team->wasRecentlyCreated ? 1 : 0;
            }

            if ($memberNameIndex === null && $memberEmailIndex === null) {
                continue;
            }

            if ($team->isInActiveContest()) {
                $skippedActiveTeamMembers++;

                continue;
            }

            $memberName = trim((string) ($row[$memberNameIndex] ?? ''));
            $memberEmail = strtolower(trim((string) ($row[$memberEmailIndex] ?? '')));
            if ($memberName === '' || $memberEmail === '') {
                $skippedRows++;
                continue;
            }

            $validator = Validator::make([
                'name' => $memberName,
                'email' => $memberEmail,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:team_members,email'],
            ]);

            if ($validator->fails()) {
                $skippedRows++;
                continue;
            }

            $ordinal = $team->members()->count();
            $team->members()->create([
                'name' => $memberName,
                'email' => $memberEmail,
                'ordinal' => $ordinal,
            ]);
            $createdMembers++;
        }

        fclose($handle);

        $message = "{$createdTeams} team(s) and {$createdMembers} member(s) imported. {$skippedRows} row(s) skipped.";
        if ($skippedActiveTeamMembers > 0) {
            $message .= " {$skippedActiveTeamMembers} member row(s) skipped for teams in active contests.";
        }

        return redirect()
            ->route('admin.teams.index')
            ->with('success', $message);
    }
}
