<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Services\ScoreboardService;
use Illuminate\Http\Request;

final class AdminScoreboardController extends Controller
{
    public function show(Request $request, Contest $contest, ScoreboardService $scoreboard)
    {
        $rows = $scoreboard->build($contest, frozen: false);  // admin sees truth

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json(['rows' => $rows]);
        }

        return view('admin.contests.tabs.scoreboard', compact('contest', 'rows'));
    }
}
