<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\Team;

final class AdminDashboardController extends Controller
{
    public function index()
    {
        $contests = Contest::query()
            ->with('creator')
            ->orderByRaw("FIELD(status, 'running', 'approved', 'draft', 'ended')")
            ->orderByDesc('start_time')
            ->get();

        $activeContest = Contest::activeContest();

        $stats = [
            'total_problems'     => Problem::count(),
            'total_teams'        => Team::count(),
            'total_contests'     => $contests->count(),
            'total_submissions'  => Submission::count(),
        ];

        return view('admin.dashboard', compact('contests', 'activeContest', 'stats'));
    }
}
