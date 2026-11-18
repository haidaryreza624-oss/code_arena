<?php

namespace App\Http\Controllers\Contest;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use Illuminate\Support\Facades\Auth;

final class ContestantLandingController extends Controller
{
    public function show(Contest $contest)
    {
        $participation = Auth::guard('contestant')->user();

        return view('contest.landing', [
            'contest'       => $contest,
            'participation' => $participation && $participation->contest_id === $contest->id
                ? $participation
                : null,
        ]);
    }
}
