<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class ProblemController extends Controller
{
    public function index()
    {
        $problems = Problem::query()
            ->withCount('testCases', 'contestProblems')
            ->orderBy('code')
            ->paginate(30);

        return view('admin.problems.index', compact('problems'));
    }

    public function create()
    {
        return view('admin.problems.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateProblem($request);
        $data['created_by'] = Auth::id();

        $problem = Problem::create($data);

        return redirect()
            ->route('admin.problems.show', $problem)
            ->with('status', 'Problem created.');
    }

    public function show(Problem $problem)
    {
        $problem->load(['testCases', 'contestProblems.contest']);

        return view('admin.problems.show', compact('problem'));
    }

    public function edit(Problem $problem)
    {
        if ($problem->isInUse()) {
            return redirect()
                ->route('admin.problems.show', $problem)
                ->withErrors(['edit' => 'This problem is used by a locked contest. Editing is disabled.']);
        }

        return view('admin.problems.edit', compact('problem'));
    }

    public function update(Request $request, Problem $problem)
    {
        if ($problem->isInUse()) {
            return back()->withErrors(['edit' => 'This problem is used by a locked contest. Editing is disabled.']);
        }

        $data = $this->validateProblem($request, $problem);
        $problem->update($data);

        return redirect()
            ->route('admin.problems.show', $problem)
            ->with('status', 'Problem updated.');
    }

    public function destroy(Problem $problem)
    {
        if ($problem->isInUse()) {
            return back()->withErrors(['delete' => 'This problem is used by a locked contest. Delete blocked.']);
        }

        $problem->delete();

        return redirect()
            ->route('admin.problems.index')
            ->with('status', 'Problem deleted.');
    }

    private function validateProblem(Request $request, ?Problem $existing = null): array
    {
        $code = $request->input('code');
        $rules = [
            'code'             => [
                'required', 'string', 'max:20',
                $existing
                    ? Rule::unique('problems', 'code')->ignore($existing->id)
                    : 'unique:problems,code',
            ],
            'title'            => ['required', 'string', 'max:255'],
            'statement_md'     => ['required', 'string'],
            'time_limit_ms'    => ['required', 'integer', 'min:100', 'max:60000'],
            'memory_limit_mb'  => ['required', 'integer', 'min:16', 'max:2048'],
        ];

        return $request->validate($rules);
    }
}
