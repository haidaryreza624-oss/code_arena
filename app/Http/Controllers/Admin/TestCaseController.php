<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use App\Models\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TestCaseController extends Controller
{
    public function store(Request $request, Problem $problem): RedirectResponse
    {
        $this->ensureProblemEditable($problem);

        $validated = $request->validate([
            'kind' => ['required', Rule::in([TestCase::KIND_SAMPLE, TestCase::KIND_HIDDEN])],
            'ordinal' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('test_cases', 'ordinal')->where('problem_id', $problem->id),
            ],
            'input' => ['required', 'string'],
            'output' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($problem, $validated) {
            $testCase = TestCase::create([
                'problem_id' => $problem->id,
                'ordinal' => $validated['ordinal'],
                'kind' => $validated['kind'],
                'input_path' => "problems/{$problem->id}/test-cases/pending.in",
                'output_path' => "problems/{$problem->id}/test-cases/pending.out",
                'input_sha256' => str_repeat('0', 64),
                'output_sha256' => str_repeat('0', 64),
                'input_size_bytes' => 0,
                'output_size_bytes' => 0,
            ]);

            $inputPath = "problems/{$problem->id}/test-cases/{$testCase->id}.in";
            $outputPath = "problems/{$problem->id}/test-cases/{$testCase->id}.out";

            Storage::disk(config('filesystems.test_cases_disk', 'local'))->put($inputPath, $validated['input']);
            Storage::disk(config('filesystems.test_cases_disk', 'local'))->put($outputPath, $validated['output']);

            $testCase->forceFill([
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'input_sha256' => hash('sha256', $validated['input']),
                'output_sha256' => hash('sha256', $validated['output']),
                'input_size_bytes' => strlen($validated['input']),
                'output_size_bytes' => strlen($validated['output']),
            ])->save();
        });

        return redirect()
            ->route('admin.problems.show', $problem)
            ->with('success', 'Test case added successfully.');
    }

    public function update(Request $request, Problem $problem, TestCase $testCase): RedirectResponse
    {
        $this->ensureProblemEditable($problem);
        abort_unless($testCase->problem_id === $problem->id, 404);

        $validated = $request->validate([
            'kind' => ['required', Rule::in([TestCase::KIND_SAMPLE, TestCase::KIND_HIDDEN])],
            'ordinal' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('test_cases', 'ordinal')
                    ->where('problem_id', $problem->id)
                    ->ignore($testCase->id),
            ],
            'input' => ['required', 'string'],
            'output' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($testCase, $validated) {
            $testCase->update([
                'kind' => $validated['kind'],
                'ordinal' => $validated['ordinal'],
            ]);

            $testCase->writeInput($validated['input']);
            $testCase->writeOutput($validated['output']);
        });

        return redirect()
            ->route('admin.problems.show', $problem)
            ->with('success', 'Test case updated successfully.');
    }

    public function destroy(Problem $problem, TestCase $testCase): RedirectResponse
    {
        $this->ensureProblemEditable($problem);
        abort_unless($testCase->problem_id === $problem->id, 404);

        $testCase->delete();

        return redirect()
            ->route('admin.problems.show', $problem)
            ->with('success', 'Test case deleted successfully.');
    }

    private function ensureProblemEditable(Problem $problem): void
    {
        abort_if($problem->isInUse(), 423, 'This problem is locked because it is used in a contest.');
    }
}
