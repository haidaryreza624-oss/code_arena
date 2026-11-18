<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestProblem;
use App\Models\Problem;
use Illuminate\Http\Request;

final class ContestProblemController extends Controller
{
    public function index(Contest $contest)
    {
        $contest->load('contestProblems.problem');

        $attachedIds  = $contest->contestProblems->pluck('problem_id')->all();
        $available    = Problem::whereNotIn('id', $attachedIds)
            ->orderBy('code')
            ->get();

        return view('admin.contests.tabs.problems', compact('contest', 'available'));
    }

    public function store(Request $request, Contest $contest)
    {
        abort_unless($contest->isDraft(), 403, 'Cannot modify problems after approval.');

        $data = $request->validate([
            'problem_id' => ['required', 'integer', 'exists:problems,id'],
            'label'      => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'points'     => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        // Reject duplicates
        abort_if(
            $contest->contestProblems()->where('problem_id', $data['problem_id'])->exists(),
            422,
            'Problem is already attached to this contest.'
        );
        abort_if(
            $contest->contestProblems()->where('label', $data['label'])->exists(),
            422,
            'Label is already used in this contest.'
        );

        $sortOrder = (int) $contest->contestProblems()->max('sort_order') + 1;

        $contest->contestProblems()->create([
            'problem_id' => $data['problem_id'],
            'label'      => strtoupper($data['label']),
            'points'     => $data['points'] ?? 0,
            'sort_order' => $sortOrder,
        ]);

        return back()->with('status', 'Problem attached.');
    }

    public function update(Request $request, Contest $contest, ContestProblem $contestProblem)
    {
        abort_unless($contest->isDraft(), 403);
        abort_unless($contestProblem->contest_id === $contest->id, 404);

        $data = $request->validate([
            'label'  => ['required', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/'],
            'points' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        // Reject label collisions
        abort_if(
            $contest->contestProblems()
                ->where('label', $data['label'])
                ->where('id', '!=', $contestProblem->id)
                ->exists(),
            422,
            'Label is already used in this contest.'
        );

        $contestProblem->update([
            'label'  => strtoupper($data['label']),
            'points' => $data['points'] ?? 0,
        ]);

        return back()->with('status', 'Problem updated.');
    }

    public function destroy(Contest $contest, ContestProblem $contestProblem)
    {
        abort_unless($contest->isDraft(), 403);
        abort_unless($contestProblem->contest_id === $contest->id, 404);

        $contestProblem->delete();

        return back()->with('status', 'Problem detached.');
    }

    /**
     * Bulk reorder via JSON array of contest_problem ids in new order.
     */
    public function reorder(Request $request, Contest $contest)
    {
        abort_unless($contest->isDraft(), 403);

        $data = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['required', 'integer'],
        ]);

        foreach ($data['order'] as $index => $id) {
            $contest->contestProblems()
                ->where('id', $id)
                ->update(['sort_order' => $index]);
        }

        return response()->noContent();
    }
}
