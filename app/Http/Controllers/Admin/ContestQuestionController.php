<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContestQuestionController extends Controller
{
    public function show(Contest $contest, ContestQuestion $question): View
    {
        $contest->syncState();
        abort_unless($question->contest_id === $contest->id, 404);
        $question->load(['participation.team', 'contestProblem.problem', 'answeredBy']);

        return view('admin.contests.questions.show', compact('contest', 'question'));
    }

    public function answer(Request $request, Contest $contest, ContestQuestion $question): RedirectResponse
    {
        $contest->syncState();
        abort_unless($contest->isRunning(), 409, 'Questions can only be answered during a running contest.');
        abort_unless($question->contest_id === $contest->id, 404);

        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:20000'],
            'visibility' => ['required', 'in:public,private'],
        ]);
        if ($question->contest_problem_id !== null) {
            abort_unless(
                $contest->contestProblems()->whereKey($question->contest_problem_id)->exists(),
                404
            );
        }
        $question->forceFill([
            ...$validated,
            'answered_by' => $request->user('web')->id,
            'answered_at' => now(),
        ])->save();

        return redirect()->route('admin.contests.questions.show', [$contest, $question])
            ->with('success', 'Answer saved.');
    }
}
