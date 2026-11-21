<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamMemberController extends Controller
{
    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->ensureTeamMembersEditable($team);

        $validated = $request->validateWithBag('addMember', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:team_members,email'],
            'ordinal' => ['nullable', 'integer', 'min:0'],
        ]);

        $team->members()->create([
            ...$validated,
            'ordinal' => $validated['ordinal'] ?? $team->members()->count(),
        ]);

        return redirect()
            ->route('admin.teams.show', $team)
            ->with('success', 'Team member added.');
    }

    public function update(Request $request, Team $team, TeamMember $teamMember): RedirectResponse
    {
        abort_unless($teamMember->team_id === $team->id, 404);
        $this->ensureTeamMembersEditable($team);

        $validated = $request->validateWithBag('editMember'.$teamMember->id, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('team_members', 'email')->ignore($teamMember->id),
            ],
            'ordinal' => ['required', 'integer', 'min:0'],
        ]);

        $teamMember->update($validated);

        return redirect()
            ->route('admin.teams.show', $team)
            ->with('success', 'Team member updated.');
    }

    public function destroy(Team $team, TeamMember $teamMember): RedirectResponse
    {
        abort_unless($teamMember->team_id === $team->id, 404);
        $this->ensureTeamMembersEditable($team);

        $teamMember->delete();

        return redirect()
            ->route('admin.teams.show', $team)
            ->with('success', 'Team member deleted.');
    }

    private function ensureTeamMembersEditable(Team $team): void
    {
        abort_if($team->isInActiveContest(), 423, 'Team members cannot be changed while the team is in an active contest.');
    }
}
