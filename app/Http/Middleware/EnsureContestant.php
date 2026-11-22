<?php

namespace App\Http\Middleware;

use App\Models\ContestParticipation;
use App\Models\Contest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContestant
{
    public function handle(Request $request, Closure $next): Response
    {
        $participation = $request->user('contestant');
        if (! $participation instanceof ContestParticipation) {
            return redirect()->route('team.login');
        }

        $contest = $participation->contest()->first();
        abort_if($contest === null, 403, 'This team account is not associated with a contest.');
        $contest->syncState();
        if ($contest->isApproved() || $contest->isRunning()) {
            $activeContest = Contest::activeContest();
            abort_unless($activeContest?->is($contest), 403, 'This team account does not belong to the active contest.');
        }
        if ($contest->isApproved() && ! in_array($request->route()?->getName(), [
            'team.dashboard',
            'team.status',
            'team.logout',
        ], true)) {
            return redirect()->route('team.dashboard');
        }
        $request->attributes->set('contest', $contest);

        return $next($request);
    }
}
