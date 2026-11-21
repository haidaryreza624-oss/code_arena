<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\ContestProblem;
use App\Models\Problem;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase as BaseTestCase;

class AdminProblemManagementTest extends BaseTestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.test_cases_disk', 'test_cases'));
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_create_list_show_update_and_delete_a_problem(): void
    {
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.problems.store'), [
                'code' => 'TWO-SUM',
                'title' => 'Two Sum',
                'statement_md' => '# Two Sum',
                'time_limit_ms' => 1000,
                'memory_limit_mb' => 256,
            ])
            ->assertRedirect();

        $problem = Problem::where('code', 'TWO-SUM')->firstOrFail();

        $this->get(route('admin.problems.index'))->assertOk()->assertSee('Two Sum');
        $this->get(route('admin.problems.show', $problem))->assertOk()->assertSee('Two Sum');

        $this->put(route('admin.problems.update', $problem), [
            'code' => 'TWO-SUM',
            'title' => 'Two Sum Updated',
            'statement_md' => '# Updated',
            'time_limit_ms' => 1500,
            'memory_limit_mb' => 512,
        ])
            ->assertRedirect(route('admin.problems.show', $problem));

        $this->assertDatabaseHas('problems', ['id' => $problem->id, 'title' => 'Two Sum Updated']);

        $this->delete(route('admin.problems.destroy', $problem))
            ->assertRedirect(route('admin.problems.index'));

        $this->assertDatabaseMissing('problems', ['id' => $problem->id]);
    }

    public function test_admin_can_add_edit_kind_and_delete_a_test_case(): void
    {
        $problem = Problem::factory()->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.problems.test-cases.store', $problem), [
                'ordinal' => 1,
                'kind' => TestCase::KIND_SAMPLE,
                'input' => '1 2',
                'output' => '3',
            ])
            ->assertRedirect(route('admin.problems.show', $problem));

        $testCase = TestCase::where('problem_id', $problem->id)->firstOrFail();
        $this->assertSame('1 2', $testCase->readInput());
        $this->assertSame('3', $testCase->readOutput());

        $this->put(route('admin.problems.test-cases.update', [$problem, $testCase]), [
            'ordinal' => 2,
            'kind' => TestCase::KIND_HIDDEN,
            'input' => '4 5',
            'output' => '9',
        ])->assertRedirect(route('admin.problems.show', $problem));

        $testCase->refresh();
        $this->assertSame(2, $testCase->ordinal);
        $this->assertSame(TestCase::KIND_HIDDEN, $testCase->kind);
        $this->assertSame('4 5', $testCase->readInput());

        $this->delete(route('admin.problems.test-cases.destroy', [$problem, $testCase]))
            ->assertRedirect(route('admin.problems.show', $problem));

        $this->assertDatabaseMissing('test_cases', ['id' => $testCase->id]);
    }

    public function test_locked_problem_and_test_cases_cannot_be_changed_or_deleted(): void
    {
        $problem = Problem::factory()->create(['created_by' => $this->admin->id]);
        $testCase = TestCase::factory()->create([
            'problem_id' => $problem->id,
            'ordinal' => 1,
        ]);
        $contest = Contest::factory()->running()->create([
            'created_by' => $this->admin->id,
        ]);
        ContestProblem::factory()->create([
            'contest_id' => $contest->id,
            'problem_id' => $problem->id,
        ]);

        $this->actingAs($this->admin, 'web');

        $this->put(route('admin.problems.update', $problem), [
            'code' => $problem->code,
            'title' => 'Changed',
            'statement_md' => 'Changed',
            'time_limit_ms' => 1000,
            'memory_limit_mb' => 256,
        ])->assertStatus(423);

        $this->delete(route('admin.problems.destroy', $problem))->assertStatus(423);

        $this->put(route('admin.problems.test-cases.update', [$problem, $testCase]), [
            'ordinal' => 1,
            'kind' => TestCase::KIND_HIDDEN,
            'input' => 'changed',
            'output' => 'changed',
        ])->assertStatus(423);

        $this->delete(route('admin.problems.test-cases.destroy', [$problem, $testCase]))
            ->assertStatus(423);
    }

    public function test_problem_assigned_to_an_ended_contest_can_be_updated_and_deleted(): void
    {
        $problem = Problem::factory()->create(['created_by' => $this->admin->id]);
        $contest = Contest::factory()->ended()->create(['created_by' => $this->admin->id]);
        $contestProblem = ContestProblem::factory()->create([
            'contest_id' => $contest->id,
            'problem_id' => $problem->id,
        ]);
        $testCase = TestCase::factory()->configure()->create([
            'problem_id' => $problem->id,
            'ordinal' => 1,
        ]);
        $inputPath = $testCase->input_path;
        $outputPath = $testCase->output_path;

        $this->actingAs($this->admin, 'web')
            ->put(route('admin.problems.update', $problem), [
                'code' => $problem->code,
                'title' => 'Updated after contest',
                'statement_md' => '# Updated',
                'time_limit_ms' => 1000,
                'memory_limit_mb' => 256,
            ])->assertRedirect(route('admin.problems.show', $problem));

        $this->delete(route('admin.problems.destroy', $problem))
            ->assertRedirect(route('admin.problems.index'));

        $this->assertDatabaseMissing('problems', ['id' => $problem->id]);
        $this->assertDatabaseMissing('contest_problems', ['id' => $contestProblem->id]);
        $this->assertDatabaseMissing('test_cases', ['id' => $testCase->id]);
        Storage::disk(config('filesystems.test_cases_disk', 'test_cases'))->assertMissing($inputPath);
        Storage::disk(config('filesystems.test_cases_disk', 'test_cases'))->assertMissing($outputPath);
    }
}
