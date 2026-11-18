<?php

namespace App\Http\Controllers\Contest;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Services\ScoreboardService;
use Illuminate\Http\Request;

final class ContestantScoreboardController extends Controller
{
    public function show(Request $request, Contest $contest, ScoreboardService $scoreboard)
    {
        // Public access allowed if contest.is_public, otherwise require auth.
        if (! $contest->is_public) {
            $this->requireContestantAuth($contest);
        }

        $frozen = $contest->freeze_minutes > 0 && $contest->isRunning();
        $rows   = $scoreboard->build($contest, frozen: $frozen);

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json([
                'rows'    => $rows,
                'frozen'  => $frozen,
                'status'  => $contest->status,
                'ends_at' => $contest->end_time?->toIso8601String(),
            ]);
        }

        return view('contest.scoreboard', compact('contest', 'rows', 'frozen'));
    }

    private function requireContestantAuth(Contest $contest): void
    {
        $participation = auth('contestant')->user();

        abort_unless(
            $participation && $participation->contest_id === $contest->id,
            403,
            'This scoreboard is not public.'
        );
    }
}
