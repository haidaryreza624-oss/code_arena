<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use App\Models\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class TestCaseController extends Controller
{
    public function index(Problem $problem)
    {
        $problem->load('testCases');

        return view('admin.problems.test-cases', compact('problem'));
    }

    public function store(Request $request, Problem $problem)
    {
        if ($problem->isInUse()) {
            return back()->withErrors(['upload' => 'Cannot add test cases: problem is locked by a contest.']);
        }

        $data = $request->validate([
            'input'           => ['required', 'file', 'max:10240'],
            'output'          => ['required', 'file', 'max:10240'],
            'kind'            => ['required', 'in:sample,hidden'],
        ]);

        $ordinal = $problem->nextTestCaseOrdinal();
        $id      = (int) (TestCase::max('id') ?? 0) + 1;  // safe-ish pre-id for path
        $disk    = config('filesystems.test_cases_disk', 'local');

        $inputPath  = "problems/{$problem->id}/test-cases/{$id}-{$ordinal}.in";
        $outputPath = "problems/{$problem->id}/test-cases/{$id}-{$ordinal}.out";

        $inputBody  = $request->file('input')->get();
        $outputBody = $request->file('output')->get();

        Storage::disk($disk)->put($inputPath, $inputBody);
        Storage::disk($disk)->put($outputPath, $outputBody);

        $problem->testCases()->create([
            'ordinal'           => $ordinal,
            'kind'              => $data['kind'],
            'input_path'        => $inputPath,
            'output_path'       => $outputPath,
            'input_sha256'      => hash('sha256', $inputBody),
            'output_sha256'     => hash('sha256', $outputBody),
            'input_size_bytes'  => strlen($inputBody),
            'output_size_bytes' => strlen($outputBody),
        ]);

        return back()->with('status', 'Test case added.');
    }

    public function update(Request $request, Problem $problem, TestCase $testCase)
    {
        abort_unless($testCase->problem_id === $problem->id, 404);
        if ($problem->isInUse()) {
            return back()->withErrors(['update' => 'Cannot modify test cases: problem is locked.']);
        }

        $data = $request->validate([
            'kind' => ['required', 'in:sample,hidden'],
        ]);

        $testCase->update(['kind' => $data['kind']]);

        return back()->with('status', 'Test case updated.');
    }

    public function destroy(Problem $problem, TestCase $testCase)
    {
        abort_unless($testCase->problem_id === $problem->id, 404);
        if ($problem->isInUse()) {
            return back()->withErrors(['delete' => 'Cannot delete test cases: problem is locked.']);
        }

        $testCase->delete();  // File cleanup via model booted()

        return back()->with('status', 'Test case deleted.');
    }

    public function download(Problem $problem, TestCase $testCase, string $side)
    {
        abort_unless($testCase->problem_id === $problem->id, 404);
        abort_unless(in_array($side, ['input', 'output'], true), 404);

        $disk = config('filesystems.test_cases_disk', 'local');
        $path = $side === 'input' ? $testCase->input_path : $testCase->output_path;

        return Storage::disk($disk)->download($path, "test-{$testCase->ordinal}.{$side}.txt");
    }
}
