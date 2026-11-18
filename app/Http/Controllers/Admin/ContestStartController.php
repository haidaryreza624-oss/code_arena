<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StartContest;
use App\Http\Controllers\Controller;
use App\Models\Contest;

final class ContestStartController extends Controller
{
    public function __invoke(Contest $contest, StartContest $action)
    {
        abort_unless($contest->isApproved(), 403, 'Only approved contests can be started.');

        $action->execute($contest);

        return redirect()
            ->route('admin.contests.show', $contest)
            ->with('status', 'Contest started.');
    }
}
