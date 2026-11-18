<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveContest;
use App\Http\Controllers\Controller;
use App\Models\Contest;
use Illuminate\Http\Request;

final class ContestApprovalController extends Controller
{
    public function __invoke(Request $request, Contest $contest, ApproveContest $action)
    {
        abort_unless($contest->isDraft(), 403, 'Only draft contests can be approved.');

        // Pre-checks for friendly errors (DB enforces the real invariant).
        $active = Contest::activeContest();
        if ($active !== null) {
            return back()->withErrors([
                'approval' => "Cannot approve: \"{$active->name}\" is still {$active->status}. End it first.",
            ]);
        }

        if ($contest->contestProblems()->count() === 0) {
            return back()->withErrors(['approval' => 'Attach at least one problem before approving.']);
        }

        if ($contest->participations()->count() === 0) {
            return back()->withErrors(['approval' => 'Import teams before approving.']);
        }

        if ($contest->end_time->isPast()) {
            return back()->withErrors(['approval' => 'Cannot approve a contest whose end time has already passed.']);
        }

        if ($contest->start_time->isPast()) {
            // Allowed: contest will auto-start on next sync (started_at will be now()).
            // Just warn the admin via flash on redirect.
            $warn = 'Note: start time is already in the past — the contest will start immediately.';
        } else {
            $warn = null;
        }

        $action->execute($contest, $request->user());

        return redirect()
            ->route('admin.contests.show', $contest)
            ->with('status', 'Contest approved.')
            ->when($warn, fn ($r) => $r->with('warning', $warn));
    }
}
