<?php

namespace App\Http\Controllers\Admin;

use App\Actions\EndContest;
use App\Http\Controllers\Controller;
use App\Models\Contest;

final class ContestEndController extends Controller
{
    public function __invoke(Contest $contest, EndContest $action)
    {
        abort_unless($contest->isRunning(), 403, 'Only running contests can be ended.');

        $action->execute($contest);

        return redirect()
            ->route('admin.contests.show', $contest)
            ->with('status', 'Contest ended.');
    }
}
