<?php

namespace App\Http\Controllers\Contest;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestParticipation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ContestantAuthController extends Controller
{
    public function showLogin(Contest $contest)
    {
        if ($this->alreadyLoggedIn($contest)) {
            return redirect()->route('contest.problems', $contest->slug);
        }

        return view('contest.auth.login', compact('contest'));
    }

    public function login(Request $request, Contest $contest)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $participation = ContestParticipation::query()
            ->where('contest_id', $contest->id)
            ->where('username', $credentials['username'])
            ->first();

        if (! $participation || ! Hash::check($credentials['password'], $participation->password)) {
            throw ValidationException::withMessages([
                'username' => 'Invalid credentials for this contest.',
            ]);
        }

        Auth::guard('contestant')->login($participation);
        $request->session()->regenerate();

        return redirect()->intended(route('contest.problems', $contest->slug));
    }

    public function logout(Request $request, Contest $contest)
    {
        Auth::guard('contestant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('contest.login', $contest->slug);
    }

    private function alreadyLoggedIn(Contest $contest): bool
    {
        $user = Auth::guard('contestant')->user();

        return $user !== null && $user->contest_id === $contest->id;
    }
}
