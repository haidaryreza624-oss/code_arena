<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\ContestProblem;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase as BaseTestCase;

class AdminSubmissionManagementTest extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create(), 'web');
    }

    public function test_admin_can_filter_all_submissions_and_open_contest_submission_details(): void
    {
        $contest = Contest::factory()->draft()->create([
            'name' => 'Winter Open',
            'created_by' => User::factory()->admin()->create()->id,
        ]);
        $problem = Problem::factory()->create(['title' => 'Echo the input']);
        $contestProblem = ContestProblem::create([
            'contest_id' => $contest->id,
            'problem_id' => $problem->id,
            'label' => 'A',
            'points' => 100,
            'sort_order' => 0,
        ]);
        $team = Team::create(['name' => 'North Team']);
        $participation = ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => $team->id,
        ]);
        $submission = $this->createSubmission($contest, $participation, $contestProblem, [
            'language' => 'python',
            'status' => Submission::STATUS_DONE,
            'verdict' => Submission::VERDICT_ACCEPTED,
            'is_sample_run' => false,
            'submitted_at' => now(),
        ]);

        $otherContest = Contest::factory()->draft()->create([
            'name' => 'Spring Open',
            'created_by' => User::factory()->admin()->create()->id,
        ]);
        $otherProblem = Problem::factory()->create();
        $otherContestProblem = ContestProblem::create([
            'contest_id' => $otherContest->id,
            'problem_id' => $otherProblem->id,
            'label' => 'B',
            'points' => 100,
            'sort_order' => 0,
        ]);
        $otherTeam = Team::create(['name' => 'South Team']);
        $otherParticipation = ContestParticipation::factory()->create([
            'contest_id' => $otherContest->id,
            'team_id' => $otherTeam->id,
        ]);
        $this->createSubmission($otherContest, $otherParticipation, $otherContestProblem, [
            'language' => 'cpp',
            'status' => Submission::STATUS_QUEUED,
            'verdict' => null,
            'is_sample_run' => true,
            'submitted_at' => now()->subDay(),
        ]);

        $this->get(route('admin.submissions.index', [
            'contest_id' => $contest->id,
            'team_id' => $team->id,
            'problem_id' => $problem->id,
            'status' => Submission::STATUS_DONE,
            'verdict' => Submission::VERDICT_ACCEPTED,
            'language' => 'python',
            'from' => now()->startOfDay()->toIso8601String(),
            'to' => now()->endOfDay()->toIso8601String(),
            'sample_run' => '0',
        ]))
            ->assertOk()
            ->assertSee('Winter Open')
            ->assertSee('North Team')
            ->assertSee('Echo the input')
            ->assertSee(route('admin.contests.submissions.show', [$contest, $submission]), false)
            ->assertViewHas('submissions', fn ($submissions) => $submissions->total() === 1
                && $submissions->first()->id === $submission->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSubmission(
        Contest $contest,
        ContestParticipation $participation,
        ContestProblem $contestProblem,
        array $attributes,
    ): Submission {
        return Submission::create([
            'contest_id' => $contest->id,
            'participation_id' => $participation->id,
            'contest_problem_id' => $contestProblem->id,
            'language' => 'python',
            'source_path' => 'sources/test.py',
            'status' => Submission::STATUS_DONE,
            'verdict' => Submission::VERDICT_ACCEPTED,
            'is_sample_run' => false,
            'submitted_at' => now(),
            'rejudge_count' => 0,
            ...$attributes,
        ]);
    }
}
