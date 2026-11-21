<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\ContestProblem;
use App\Models\ContestQuestion;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase as BaseTestCase;

class AdminContestManagementTest extends BaseTestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin, 'web');
        Storage::fake(config('filesystems.test_cases_disk', 'test_cases'));
        Storage::fake(config('filesystems.sources_disk', 'sources'));
    }

    public function test_admin_can_create_update_list_and_delete_a_draft_contest(): void
    {
        $this->post(route('admin.contests.store'), [
            'name' => 'Winter Open',
            'slug' => 'winter-open',
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->addHours(3)->format('Y-m-d H:i:s'),
            'freeze-minutes' => 10,
            'is_public' => 1,
        ])->assertRedirect();

        $contest = Contest::where('slug', 'winter-open')->firstOrFail();
        $this->assertSame(Contest::STATUS_DRAFT, $contest->status);

        $this->get(route('admin.contests.index'))->assertOk()->assertSee('Winter Open');
        $this->get(route('admin.contests.show', $contest))->assertOk()->assertSee('Draft contest settings');

        $this->put(route('admin.contests.update', $contest), [
            'name' => 'Winter Open Updated',
            'slug' => 'winter-open',
            'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->addHours(3)->format('Y-m-d H:i:s'),
            'freeze-minutes' => 15,
        ])->assertRedirect(route('admin.contests.show', $contest));

        $this->assertDatabaseHas('contests', ['id' => $contest->id, 'name' => 'Winter Open Updated', 'is_public' => false]);

        $this->delete(route('admin.contests.destroy', $contest))
            ->assertRedirect(route('admin.contests.index'));
        $this->assertDatabaseMissing('contests', ['id' => $contest->id]);
    }

    public function test_admin_cannot_create_another_contest_while_one_is_active(): void
    {
        Contest::factory()->running()->create(['created_by' => $this->admin->id]);

        $this->get(route('admin.contests.create'))->assertStatus(409);
        $this->post(route('admin.contests.store'), [])->assertStatus(409);
    }

    public function test_draft_problem_and_team_assignments_can_be_approved_and_then_are_read_only(): void
    {
        $contest = Contest::factory()->draft()->create(['created_by' => $this->admin->id]);
        $problem = Problem::factory()->create(['created_by' => $this->admin->id]);
        $team = Team::create(['name' => 'Assigned Team']);

        $this->post(route('admin.contests.problems.assign', $contest), [
            'problem_id' => $problem->id,
            'points' => 250,
        ])->assertRedirect(route('admin.contests.show', $contest));
        $this->assertDatabaseHas('contest_problems', [
            'contest_id' => $contest->id,
            'problem_id' => $problem->id,
            'label' => 'A',
        ]);

        $this->post(route('admin.contests.teams.assign', $contest), [
            'team_ids' => [$team->id],
        ])->assertRedirect(route('admin.contests.show', $contest));
        $this->assertDatabaseHas('contest_participations', [
            'contest_id' => $contest->id,
            'team_id' => $team->id,
        ]);

        $this->post(route('admin.contests.approve', $contest))
            ->assertRedirect(route('admin.contests.show', $contest));
        $this->assertDatabaseHas('contests', ['id' => $contest->id, 'status' => Contest::STATUS_APPROVED]);

        $contestProblem = ContestProblem::where('contest_id', $contest->id)->firstOrFail();
        $participation = ContestParticipation::where('contest_id', $contest->id)->firstOrFail();

        $this->delete(route('admin.contests.problems.unassign', [$contest, $contestProblem]))->assertStatus(423);
        $this->delete(route('admin.contests.teams.unassign', [$contest, $participation]))->assertStatus(423);
        $this->put(route('admin.contests.update', $contest), [])->assertStatus(423);
    }

    public function test_credentials_are_generated_and_regenerated_without_storing_plaintext(): void
    {
        $contest = Contest::factory()->draft()->create(['created_by' => $this->admin->id]);
        $team = Team::create(['name' => 'Credentials Team']);
        $participation = ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => $team->id,
        ]);

        $response = $this->post(route('admin.contests.teams.credentials', [$contest, $participation]));
        $response->assertOk()->assertViewHas('credentials', function (array $credentials) use ($team) {
            return count($credentials) === 1 && $credentials[0]['team'] === $team->name;
        });

        $participation->refresh();
        $storedHash = $participation->password;
        $this->assertTrue(Hash::check(
            $response->viewData('credentials')[0]['password'],
            $storedHash
        ));
        $this->assertNotNull($participation->credentials_generated_at);

        $this->post(route('admin.contests.teams.credentials', [$contest, $participation]))
            ->assertOk();
        $participation->refresh();
        $this->assertNotSame($storedHash, $participation->password);
    }

    public function test_approved_contest_can_start_freeze_and_end_manually(): void
    {
        $contest = Contest::factory()->approved()->create(['created_by' => $this->admin->id]);

        $this->post(route('admin.contests.start', $contest))
            ->assertRedirect(route('admin.contests.show', $contest));
        $contest->refresh();
        $this->assertSame(Contest::STATUS_RUNNING, $contest->status);
        $this->assertNotNull($contest->start_at);

        $this->post(route('admin.contests.freeze-now', $contest))
            ->assertRedirect(route('admin.contests.show', $contest));
        $contest->refresh();
        $this->assertGreaterThan(0, $contest->{'freeze-minutes'});

        $this->put(route('admin.contests.freeze', $contest), ['freeze-minutes' => 0])
            ->assertRedirect(route('admin.contests.show', $contest));
        $this->assertSame(0, $contest->fresh()->{'freeze-minutes'});

        $this->post(route('admin.contests.end', $contest))
            ->assertRedirect(route('admin.contests.show', $contest));
        $this->assertSame(Contest::STATUS_ENDED, $contest->fresh()->status);
    }

    public function test_approved_contest_page_polls_and_starts_when_the_schedule_is_reached(): void
    {
        $contest = Contest::factory()->approved()->create([
            'created_by' => $this->admin->id,
            'start_time' => now()->addMinute(),
            'end_time' => now()->addHour(),
        ]);

        $this->get(route('admin.contests.show', $contest))
            ->assertOk()
            ->assertSee('content="10"', false)
            ->assertSee('Approved');
        $this->assertSame(Contest::STATUS_APPROVED, $contest->fresh()->status);

        $contest->update(['start_time' => now()->subMinute()]);
        $this->get(route('admin.contests.show', $contest))
            ->assertOk()
            ->assertSee('Running');

        $this->assertDatabaseHas('contests', [
            'id' => $contest->id,
            'status' => Contest::STATUS_RUNNING,
        ]);
    }

    public function test_running_contest_questions_can_be_answered(): void
    {
        $contest = Contest::factory()->running()->create(['created_by' => $this->admin->id]);
        $participation = ContestParticipation::factory()->create(['contest_id' => $contest->id]);
        $question = ContestQuestion::create([
            'contest_id' => $contest->id,
            'participation_id' => $participation->id,
            'body' => 'Can we use recursion?',
            'visibility' => ContestQuestion::VISIBILITY_PRIVATE,
        ]);

        $this->put(route('admin.contests.questions.answer', [$contest, $question]), [
            'answer' => 'Yes.',
            'visibility' => ContestQuestion::VISIBILITY_PUBLIC,
        ])->assertRedirect(route('admin.contests.questions.show', [$contest, $question]));

        $this->assertDatabaseHas('contest_questions', [
            'id' => $question->id,
            'answer' => 'Yes.',
            'visibility' => ContestQuestion::VISIBILITY_PUBLIC,
            'answered_by' => $this->admin->id,
        ]);

    }

    public function test_draft_and_approved_contests_print_the_problem_booklet(): void
    {
        $contest = Contest::factory()->draft()->create([
            'created_by' => $this->admin->id,
            'name' => 'Booklet Open',
            'slug' => 'booklet-open',
        ]);
        $problem = Problem::factory()->create([
            'created_by' => $this->admin->id,
            'code' => 'BOOKLET-1',
            'title' => 'Echo the input',
            'statement_md' => 'Read a line and print it.',
        ]);
        $contest->contestProblems()->create([
            'problem_id' => $problem->id,
            'label' => 'A',
            'points' => 100,
            'sort_order' => 0,
        ]);
        $team = Team::create(['name' => 'Booklet Team']);
        ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => $team->id,
        ]);
        foreach (['Second Booklet Team', 'Third Booklet Team'] as $teamName) {
            $additionalTeam = Team::create(['name' => $teamName]);
            ContestParticipation::factory()->create([
                'contest_id' => $contest->id,
                'team_id' => $additionalTeam->id,
            ]);
        }

        $sample = TestCase::create([
            'problem_id' => $problem->id,
            'ordinal' => 1,
            'kind' => TestCase::KIND_SAMPLE,
            'input_path' => 'problems/'.$problem->id.'/test-cases/1.in',
            'output_path' => 'problems/'.$problem->id.'/test-cases/1.out',
            'input_sha256' => str_repeat('0', 64),
            'output_sha256' => str_repeat('0', 64),
            'input_size_bytes' => 0,
            'output_size_bytes' => 0,
        ]);
        $sample->writeInput("hello\n");
        $sample->writeOutput("hello\n");

        $hidden = TestCase::create([
            'problem_id' => $problem->id,
            'ordinal' => 2,
            'kind' => TestCase::KIND_HIDDEN,
            'input_path' => 'problems/'.$problem->id.'/test-cases/2.in',
            'output_path' => 'problems/'.$problem->id.'/test-cases/2.out',
            'input_sha256' => str_repeat('0', 64),
            'output_sha256' => str_repeat('0', 64),
            'input_size_bytes' => 0,
            'output_size_bytes' => 0,
        ]);
        $hidden->writeInput('hidden-only-content');
        $hidden->writeOutput('hidden-only-content');

        $this->get(route('admin.contests.problems.print', $contest))
            ->assertOk()
            ->assertSee('Booklet Team')
            ->assertSee('Second Booklet Team')
            ->assertSee('Third Booklet Team')
            ->assertSee('Echo the input')
            ->assertSee('Read a line and print it.')
            ->assertSee('hello')
            ->assertSee('Table of contents')
            ->assertViewHas('teams', fn ($teams) => $teams->count() === 3
                && $teams->contains('Booklet Team')
                && $teams->contains('Second Booklet Team')
                && $teams->contains('Third Booklet Team'))
            ->assertDontSee('hidden-only-content');

        $this->post(route('admin.contests.approve', $contest))->assertRedirect();
        $this->get(route('admin.contests.problems.print', $contest))
            ->assertOk()
            ->assertSee('Booklet Team')
            ->assertSee('Echo the input');
    }

    public function test_problem_booklet_is_unavailable_for_running_or_ended_contests(): void
    {
        $running = Contest::factory()->running()->create(['created_by' => $this->admin->id]);
        $ended = Contest::factory()->ended()->create(['created_by' => $this->admin->id]);

        $this->get(route('admin.contests.problems.print', $running))->assertStatus(409);
        $this->get(route('admin.contests.problems.print', $ended))->assertStatus(409);
    }

    public function test_scoreboard_freeze_hides_submissions_after_the_freeze_cutoff(): void
    {
        $contest = Contest::factory()->running()->create([
            'created_by' => $this->admin->id,
            'end_time' => now()->addMinutes(20),
            'freeze-minutes' => 10,
        ]);
        $problem = Problem::factory()->create(['created_by' => $this->admin->id]);
        $contestProblem = ContestProblem::create([
            'contest_id' => $contest->id,
            'problem_id' => $problem->id,
            'label' => 'A',
            'points' => 100,
            'sort_order' => 0,
        ]);
        $beforeFreezeTeam = Team::create(['name' => 'Before Freeze']);
        $afterFreezeTeam = Team::create(['name' => 'After Freeze']);

        foreach ([
            [$beforeFreezeTeam, now()->addMinutes(5)],
            [$afterFreezeTeam, now()->addMinutes(15)],
        ] as [$team, $submittedAt]) {
            $participation = ContestParticipation::factory()->create([
                'contest_id' => $contest->id,
                'team_id' => $team->id,
            ]);
            Submission::create([
                'contest_id' => $contest->id,
                'participation_id' => $participation->id,
                'contest_problem_id' => $contestProblem->id,
                'language' => 'python',
                'source_path' => 'sources/scoreboard.py',
                'status' => Submission::STATUS_DONE,
                'verdict' => Submission::VERDICT_ACCEPTED,
                'is_sample_run' => false,
                'submitted_at' => $submittedAt,
                'judged_at' => now(),
                'rejudge_count' => 0,
            ]);
        }

        $this->get(route('admin.contests.show', $contest))
            ->assertOk()
            ->assertViewHas('scoreboard', function (array $scoreboard) {
                $rows = collect($scoreboard)->keyBy(fn ($row) => $row['team']->name);

                return $rows['Before Freeze']['points'] === 100
                    && $rows['After Freeze']['points'] === 0;
            });
    }

    public function test_submission_rejudge_submits_source_and_refresh_persists_judge_results(): void
    {
        $contest = Contest::factory()->running()->create(['created_by' => $this->admin->id]);
        $problem = Problem::factory()->create(['created_by' => $this->admin->id]);
        $contestProblem = ContestProblem::create([
            'contest_id' => $contest->id,
            'problem_id' => $problem->id,
            'label' => 'A',
            'points' => 100,
            'sort_order' => 0,
        ]);
        $testCase = TestCase::create([
            'problem_id' => $problem->id,
            'ordinal' => 1,
            'kind' => TestCase::KIND_SAMPLE,
            'input_path' => 'problems/'.$problem->id.'/test-cases/1.in',
            'output_path' => 'problems/'.$problem->id.'/test-cases/1.out',
            'input_sha256' => str_repeat('0', 64),
            'output_sha256' => str_repeat('0', 64),
            'input_size_bytes' => 0,
            'output_size_bytes' => 0,
        ]);
        $testCase->writeInput("hello\n");
        $testCase->writeOutput("hello\n");
        $participation = ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => Team::create(['name' => 'Judge Team'])->id,
        ]);
        $sourcePath = 'submissions/1.py';
        Storage::disk(config('filesystems.sources_disk', 'sources'))->put($sourcePath, "print(input())");
        $submission = Submission::create([
            'contest_id' => $contest->id,
            'participation_id' => $participation->id,
            'contest_problem_id' => $contestProblem->id,
            'language' => 'python',
            'source_path' => $sourcePath,
            'status' => Submission::STATUS_DONE,
            'verdict' => Submission::VERDICT_WRONG_ANSWER,
            'judge_job_id' => 'old-job',
            'is_sample_run' => false,
            'submitted_at' => now(),
            'rejudge_count' => 0,
        ]);

        Http::fake([
            '127.0.0.1:9000/api/v1/submissions/' => Http::response([
                'job_id' => 'new-job',
                'job_status' => 'Queued',
            ], 202),
            '127.0.0.1:9000/api/v1/submissions/new-job' => Http::response([
                'job_id' => 'new-job',
                'job_status' => 'Completed',
                'result' => [
                    'status' => 'Accepted',
                    'test_results' => [[
                        'test_case' => 1,
                        'status' => 'Accepted',
                        'execution_time' => 0.05,
                        'memory_usage' => 1234,
                        'stderr' => '',
                    ]],
                ],
            ]),
        ]);

        $this->post(route('admin.contests.submissions.rejudge', [$contest, $submission]))
            ->assertRedirect(route('admin.contests.submissions.show', [$contest, $submission]));

        $submission->refresh();
        $this->assertSame(1, $submission->rejudge_count);
        $this->assertSame('new-job', $submission->judge_job_id);
        $this->assertSame($this->admin->id, $submission->last_rejudged_by);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['source_code'] === 'print(input())'
            && $request['test_cases'][0]['stdin'] === "hello\n");

        $this->get(route('admin.contests.show', $contest))
            ->assertOk()
            ->assertViewHas('scoreboard', fn (array $scoreboard) => $scoreboard[0]['points'] === 100);
        $this->get(route('admin.contests.submissions.show', [$contest, $submission]))
            ->assertOk()
            ->assertSee('AC');

        $this->assertDatabaseHas('submissions', [
            'id' => $submission->id,
            'status' => Submission::STATUS_DONE,
            'verdict' => Submission::VERDICT_ACCEPTED,
            'max_time_ms' => 50,
            'max_memory_kb' => 1234,
        ]);
        $this->assertDatabaseHas('submission_results', [
            'submission_id' => $submission->id,
            'test_case_id' => $testCase->id,
            'verdict' => Submission::VERDICT_ACCEPTED,
        ]);
    }
}
