<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestQuestion;
use Illuminate\Http\Request;

final class AdminQuestionController extends Controller
{
    public function index(Contest $contest)
    {
        $questions = $contest->questions()
            ->with(['participation.team.members', 'answeredBy'])
            ->orderByRaw('answered_at IS NOT NULL')   // unanswered first
            ->orderByDesc('created_at')
            ->get();

        return view('admin.contests.tabs.questions', compact('contest', 'questions'));
    }

    public function answer(Request $request, Contest $contest, ContestQuestion $question)
    {
        abort_unless($question->contest_id === $contest->id, 404);

        $data = $request->validate([
            'answer'     => ['required', 'string', 'max:5000'],
            'visibility' => ['required', 'in:public,private'],
        ]);

        $question->update([
            'answer'      => $data['answer'],
            'visibility'  => $data['visibility'],
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
        ]);

        return back()->with('status', 'Answer saved.');
    }
}
