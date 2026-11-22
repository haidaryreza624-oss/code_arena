<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestParticipation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TeamAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('contestant')->check()) {
            return redirect()->route('team.dashboard');
        }

        return view('team.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        if (Auth::guard('contestant')->check()) {
            return redirect()->route('team.dashboard');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $contest = Contest::activeContest();
        $contest?->syncState();
        $contest = Contest::activeContest();

        $participation = $contest
            ? ContestParticipation::query()
                ->where('contest_id', $contest->id)
                ->where('username', $validated['username'])
                ->first()
            : null;

        if ($participation === null || ! Hash::check($validated['password'], $participation->password)) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'The credentials do not match a team in the active contest.']);
        }

        Auth::guard('contestant')->login($participation);
        $request->session()->regenerate();

        return redirect()->intended(route('team.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('contestant')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('team.login');
    }
}
