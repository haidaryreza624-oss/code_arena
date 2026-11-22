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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase as BaseTestCase;

class TeamContestManagementTest extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.sources_disk', 'sources'));
        Storage::fake(config('filesystems.test_cases_disk', 'test_cases'));
    }

    public function test_team_login_is_scoped_to_the_active_contest_and_waiting_page_only_shows_countdown(): void
    {
        $contest = $this->approvedContest();
        $problem = $this->assignProblem($contest, 'Secret before start');
        $participation = $this->participation($contest, 'shared-login');
        $participation->forceFill(['password' => Hash::make('team-password')])->save();

        $otherContest = Contest::factory()->draft()->create();
        $otherParticipation = $this->participation($otherContest, 'draft-only');
        $otherParticipation->forceFill(['password' => Hash::make('team-password')])->save();

        $this->post(route('team.login.store'), [
            'username' => 'draft-only',
            'password' => 'team-password',
        ])->assertSessionHasErrors('username');

        $this->post(route('team.login.store'), [
            'username' => 'shared-login',
            'password' => 'team-password',
        ])->assertRedirect(route('team.dashboard'));

        $this->get(route('team.dashboard'))
            ->assertOk()
            ->assertSee('Contest starts in')
            ->assertDontSee('Secret before start')
            ->assertDontSee('Problems')
            ->assertDontSee(route('team.questions'));
        $this->get(route('team.questions'))->assertRedirect(route('team.dashboard'));
        $this->get(route('team.scoreboard'))->assertRedirect(route('team.dashboard'));

        $contest->manualStart();
        $this->get(route('team.status'))
            ->assertOk()
            ->assertJsonPath('status', Contest::STATUS_RUNNING);
        $this->get(route('team.dashboard'))
            ->assertOk()
            ->assertSee('Secret before start')
            ->assertSee('Time remaining');
        $this->assertSame($problem->id, $contest->contestProblems()->first()->problem_id);
    }

    public function test_team_countdown_state_sync_starts_and_ends_the_contest_without_admin_page_visits(): void
    {
        $contest = $this->approvedContest();
        $this->actingAs($this->participation($contest), 'contestant');
        $contest->forceFill([
            'start_time' => now()->subMinute(),
            'end_time' => now()->addHour(),
        ])->save();

        $this->get(route('team.dashboard'))->assertOk()->assertSee('Time remaining');
        $this->assertSame(Contest::STATUS_RUNNING, $contest->fresh()->status);

        $contest->forceFill(['end_time' => now()->subSecond()])->save();
        $this->get(route('team.status'))->assertJsonPath('status', Contest::STATUS_ENDED);
        $this->get(route('team.dashboard'))->assertOk()->assertSee('The contest has ended');
    }

    public function test_teams_can_ask_questions_and_only_see_their_own_or_answered_public_questions(): void
    {
        $contest = Contest::factory()->running()->create();
        $contestProblem = $this->assignProblem($contest);
        $mine = $this->participation($contest, 'team-own');
        $other = $this->participation($contest, 'team-other');

        ContestQuestion::create([
            'contest_id' => $contest->id,
            'participation_id' => $mine->id,
            'contest_problem_id' => $contestProblem->id,
            'body' => 'My private question',
            'visibility' => ContestQuestion::VISIBILITY_PRIVATE,
        ]);
        ContestQuestion::create([
            'contest_id' => $contest->id,
            'participation_id' => $other->id,
            'contest_problem_id' => $contestProblem->id,
            'body' => 'Other public question',
            'answer' => 'Public admin answer',
            'answered_at' => now(),
            'visibility' => ContestQuestion::VISIBILITY_PUBLIC,
        ]);
        ContestQuestion::create([
            'contest_id' => $contest->id,
            'participation_id' => $other->id,
            'contest_problem_id' => $contestProblem->id,
            'body' => 'Unanswered public question',
            'visibility' => ContestQuestion::VISIBILITY_PUBLIC,
        ]);
        ContestQuestion::create([
            'contest_id' => $contest->id,
            'participation_id' => $other->id,
            'contest_problem_id' => $contestProblem->id,
            'body' => 'Other private question',
            'answer' => 'Private secret answer',
            'answered_at' => now(),
            'visibility' => ContestQuestion::VISIBILITY_PRIVATE,
        ]);

        $this->actingAs($mine, 'contestant');
        $this->get(route('team.questions'))
            ->assertOk()
            ->assertSee('My private question')
            ->assertSee('Other public question')
            ->assertSee('Public admin answer')
            ->assertDontSee('Unanswered public question')
            ->assertDontSee('Other private question')
            ->assertDontSee('Private secret answer');

        $this->post(route('team.questions.store'), [
            'contest_problem_id' => $contestProblem->id,
            'body' => 'Can I use a standard library?',
            'visibility' => ContestQuestion::VISIBILITY_PUBLIC,
        ])->assertRedirect(route('team.questions'));

        $this->assertDatabaseHas('contest_questions', [
            'contest_id' => $contest->id,
            'participation_id' => $mine->id,
            'contest_problem_id' => $contestProblem->id,
            'body' => 'Can I use a standard library?',
            'visibility' => ContestQuestion::VISIBILITY_PUBLIC,
        ]);
    }

    public function test_submission_detects_language_queues_source_and_only_shows_sample_case_results(): void
    {
        $contest = Contest::factory()->running()->create(['is_public' => true]);
        $contestProblem = $this->assignProblem($contest);
        $participation = $this->participation($contest);
        $sample = $this->testCase($contestProblem->problem_id, 1, TestCase::KIND_SAMPLE, "hello\n", "hello\n");
        $hidden = $this->testCase($contestProblem->problem_id, 2, TestCase::KIND_HIDDEN, "secret\n", "secret\n");
        $this->actingAs($participation, 'contestant');

        Http::fake([
            '127.0.0.1:9000/api/v1/submissions/' => Http::response([
                'job_id' => 'team-job-1',
                'job_status' => 'Queued',
            ], 202),
            '127.0.0.1:9000/api/v1/submissions/team-job-1' => Http::response([
                'job_id' => 'team-job-1',
                'job_status' => 'Completed',
                'result' => [
                    'status' => 'Wrong Answer',
                    'test_results' => [
                        ['test_case' => 1, 'status' => 'Accepted', 'execution_time' => 0.02, 'memory_usage' => 1024],
                        ['test_case' => 2, 'status' => 'Time Limit Exceeded', 'execution_time' => 2.0, 'memory_usage' => 2048],
                    ],
                ],
            ]),
        ]);

        $response = $this->post(route('team.submissions.store', $contestProblem), [
            'source_file' => UploadedFile::fake()->createWithContent('answer.py', "print(input())\n"),
        ]);
        $submission = Submission::firstOrFail();
        $response->assertRedirect(route('team.submissions.show', $submission));
        $this->assertSame('python', $submission->language);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['language'] === 'python'
            && count($request['test_cases']) === 2);

        $this->get(route('team.submissions.show', $submission))
            ->assertOk()
            ->assertSee('WA')
            ->assertSee('Sample test-case results')
            ->assertSee('AC')
            ->assertDontSee('Time Limit Exceeded');

        $this->assertDatabaseHas('submission_results', [
            'submission_id' => $submission->id,
            'test_case_id' => $sample->id,
            'verdict' => 'AC',
        ]);
        $this->assertDatabaseHas('submission_results', [
            'submission_id' => $submission->id,
            'test_case_id' => $hidden->id,
            'verdict' => 'TLE',
        ]);

        $anotherParticipation = $this->participation($contest, 'another-team');
        $this->actingAs($anotherParticipation, 'contestant')
            ->get(route('team.submissions.show', $submission))
            ->assertNotFound();
    }

    public function test_scoreboard_requires_public_visibility_and_shows_per_problem_points(): void
    {
        $contest = Contest::factory()->running()->create(['is_public' => false]);
        $contestProblem = $this->assignProblem($contest, points: 200);
        $participation = $this->participation($contest);
        $submission = Submission::create([
            'contest_id' => $contest->id,
            'participation_id' => $participation->id,
            'contest_problem_id' => $contestProblem->id,
            'language' => 'python',
            'source_path' => 'sources/accepted.py',
            'status' => Submission::STATUS_DONE,
            'verdict' => Submission::VERDICT_ACCEPTED,
            'is_sample_run' => false,
            'submitted_at' => now(),
            'judged_at' => now(),
            'rejudge_count' => 0,
        ]);
        $this->actingAs($participation, 'contestant');
        $this->get(route('team.scoreboard'))->assertForbidden();

        $contest->update(['is_public' => true]);
        $this->get(route('team.scoreboard'))
            ->assertOk()
            ->assertSee('200')
            ->assertSee($participation->team->name);
        $this->assertSame(Submission::VERDICT_ACCEPTED, $submission->fresh()->verdict);
    }

    private function approvedContest(): Contest
    {
        return Contest::factory()->approved()->create([
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(4),
        ]);
    }

    private function assignProblem(Contest $contest, string $title = 'Contest problem', int $points = 100): ContestProblem
    {
        $problem = Problem::factory()->create(['title' => $title]);

        return ContestProblem::create([
            'contest_id' => $contest->id,
            'problem_id' => $problem->id,
            'label' => 'A',
            'points' => $points,
            'sort_order' => 0,
        ]);
    }

    private function participation(Contest $contest, string $username = 'team-account'): ContestParticipation
    {
        return ContestParticipation::factory()->create([
            'contest_id' => $contest->id,
            'team_id' => Team::factory()->create()->id,
            'username' => $username,
        ]);
    }

    private function testCase(int $problemId, int $ordinal, string $kind, string $input, string $output): TestCase
    {
        $testCase = TestCase::create([
            'problem_id' => $problemId,
            'ordinal' => $ordinal,
            'kind' => $kind,
            'input_path' => "problems/{$problemId}/{$ordinal}.in",
            'output_path' => "problems/{$problemId}/{$ordinal}.out",
            'input_sha256' => str_repeat('0', 64),
            'output_sha256' => str_repeat('0', 64),
            'input_size_bytes' => 0,
            'output_size_bytes' => 0,
        ]);
        $testCase->writeInput($input);
        $testCase->writeOutput($output);

        return $testCase;
    }
}
