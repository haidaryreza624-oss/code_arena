<?php

namespace App\Http\Controllers\Contest;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ContestantQuestionController extends Controller
{
    public function index(Contest $contest)
    {
        $participation = Auth::guard('contestant')->user();

        $questions = $contest->questions()
            ->visibleToTeams($participation->id)
            ->with('participation.team')
            ->orderByDesc('created_at')
            ->get();

        return view('contest.questions.index', compact('contest', 'questions'));
    }

    public function store(Request $request, Contest $contest)
    {
        abort_unless($contest->acceptsSubmissions(), 403, 'Contest is not running.');

        $participation = Auth::guard('contestant')->user();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        // Rate limit: 1 question per 30 seconds per participation.
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts("question:{$participation->id}", 1)) {
            return back()->withErrors(['body' => 'Please wait before sending another question.']);
        }
        \Illuminate\Support\Facades\RateLimiter::hit("question:{$participation->id}", 30);

        ContestQuestion::create([
            'contest_id'       => $contest->id,
            'participation_id' => $participation->id,
            'body'             => $data['body'],
            'visibility'       => ContestQuestion::VISIBILITY_PRIVATE,
        ]);

        return back()->with('status', 'Question submitted to the admin.');
    }
}
