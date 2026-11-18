<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ImportTeamsCsv;
use App\Http\Controllers\Controller;
use App\Models\Contest;
use Illuminate\Http\Request;

final class TeamImportController extends Controller
{
    public function show(Contest $contest)
    {
        abort_unless($contest->isDraft(), 403, 'Teams can only be imported in draft state.');

        return view('admin.teams.import', compact('contest'));
    }

    public function store(Request $request, Contest $contest, ImportTeamsCsv $action)
    {
        abort_unless($contest->isDraft(), 403);

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $report = $action->execute($contest, $request->file('csv'));

        if (! empty($report['errors'])) {
            return back()->with([
                'status' => "Imported {$report['created']} team(s), {$report['skipped']} skipped.",
                'errors' => $report['errors'],
            ]);
        }

        return redirect()
            ->route('admin.contests.show', $contest)
            ->with('status', "Imported {$report['created']} team(s).");
    }
}
