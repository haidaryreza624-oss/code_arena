<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProblemController extends Controller
{
    public function index(Request $request): View
    {
        $problems = Problem::query()
            ->withCount('testCases')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.problems.index', compact('problems'));
    }

    public function create(): View
    {
        return view('admin.problems.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:problems,code'],
            'title' => ['required', 'string', 'max:255'],
            'statement_md' => ['required', 'string'],
            'time_limit_ms' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'memory_limit_mb' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ]);

        $problem = Problem::create([
            ...$validated,
            'created_by' => $request->user('web')->id,
        ]);

        return redirect()
            ->route('admin.problems.show', $problem)
            ->with('success', 'Problem created successfully.');
    }

    public function show(Problem $problem): View
    {
        $problem->load([
            'testCases',
            'contestProblems.contest',
            'creator',
        ]);

        foreach ($problem->testCases as $testCase) {
            try {
                $testCase->input_content = $testCase->readInput();
            } catch (\Throwable) {
                $testCase->input_content = '';
            }

            try {
                $testCase->output_content = $testCase->readOutput();
            } catch (\Throwable) {
                $testCase->output_content = '';
            }
        }

        return view('admin.problems.show', [
            'problem' => $problem,
            'inUse' => $problem->isInUse(),
        ]);
    }

    public function edit(Problem $problem): View
    {
        abort_if($problem->isInUse(), 423, 'This problem is locked because it is used in a contest.');

        return view('admin.problems.edit', compact('problem'));
    }

    public function update(Request $request, Problem $problem): RedirectResponse
    {
        abort_if($problem->isInUse(), 423, 'This problem is locked because it is used in a contest.');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:problems,code,'.$problem->id],
            'title' => ['required', 'string', 'max:255'],
            'statement_md' => ['required', 'string'],
            'time_limit_ms' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'memory_limit_mb' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ]);

        $problem->update($validated);

        return redirect()
            ->route('admin.problems.show', $problem)
            ->with('success', 'Problem updated successfully.');
    }

    public function destroy(Problem $problem): RedirectResponse
    {
        abort_if($problem->isInUse(), 423, 'This problem is locked because it is used in a contest.');

        DB::transaction(function () use ($problem) {
            foreach ($problem->testCases()->get() as $testCase) {
                $testCase->delete();
            }

            $problem->contestProblems()->delete();
            $problem->delete();
        });

        return redirect()
            ->route('admin.problems.index')
            ->with('success', 'Problem, test cases, contest assignments, and linked submissions deleted successfully.');
    }
}
