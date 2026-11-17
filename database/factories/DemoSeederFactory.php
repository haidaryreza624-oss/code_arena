<?php

namespace Database\Seeders;

use App\Models\Contest;
use App\Models\ContestParticipation;
use App\Models\ContestProblem;
use App\Models\ContestQuestion;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a complete demo playthrough:
 *   - 3 problems with sample + hidden test cases
 *   - 1 running contest attached to those problems
 *   - 5 teams with members
 *   - Participations with known passwords
 *   - ~20 submissions with mixed verdicts
 *   - A couple of Q&A threads
 *
 * Idempotent on slug / email / team name / problem code — running twice
 * leaves the database untouched after the first pass.
 *
 * Contestant demo credentials printed at the end.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', env('ADMIN_SEED_EMAIL', 'admin@codearena.test'))->first()
            ?? User::factory()->create([
                'email'    => env('ADMIN_SEED_EMAIL', 'admin@codearena.test'),
                'password' => Hash::make('password'),
            ]);

        // ---------------------------------------------------------------
        // 1. Problem bank
        // ---------------------------------------------------------------
        $problems = $this->seedProblems($admin);

        // ---------------------------------------------------------------
        // 2. Contest (running) — firstOrCreate by slug, so idempotent
        // ---------------------------------------------------------------
        $contest = Contest::firstOrCreate(
            ['slug' => 'demo-contest-2024'],
            [
                'name'           => 'Demo Contest 2024',
                'status'         => Contest::STATUS_RUNNING,
                'start_time'     => now()->subHour(),
                'end_time'       => now()->addHours(2),
                'started_at'     => now()->subHour(),
                'ended_at'       => null,
                'freeze_minutes' => 0,
                'is_public'      => true,
                'approved_at'    => now()->subHours(2),
                'approved_by'    => $admin->id,
                'created_by'     => $admin->id,
            ]
        );

        // ---------------------------------------------------------------
        // 3. Attach problems to contest as A/B/C
        // ---------------------------------------------------------------
        $labels = ['A', 'B', 'C'];
        $contestProblems = [];
        foreach ($problems as $i => $problem) {
            $cp = ContestProblem::firstOrCreate(
                ['contest_id' => $contest->id, 'problem_id' => $problem->id],
                [
                    'label'      => $labels[$i],
                    'points'     => 0,
                    'sort_order' => $i,
                ]
            );
            $contestProblems[$problem->id] = $cp;
        }

        // ---------------------------------------------------------------
        // 4. Teams + members + participations
        // ---------------------------------------------------------------
        $teamSpecs = [
            ['name' => 'Algorithm Avengers',  'members' => ['Alice Smith', 'Bob Jones']],
            ['name' => 'Binary Bandits',      'members' => ['Carol Lee', 'Dave Kim', 'Eve Chen']],
            ['name' => 'Cache Me If You Can', 'members' => ['Frank Wu', 'Grace Patel']],
            ['name' => 'Divide and Conquer',  'members' => ['Henry Ito', 'Ivy Nair']],
            ['name' => 'Edge Case',           'members' => ['Jack Adams', 'Kara Brown']],
        ];

        $participations = [];
        $plainPasswords = [];

        foreach ($teamSpecs as $idx => $spec) {
            $team = Team::firstOrCreate(['name' => $spec['name']]);

            foreach ($spec['members'] as $ordinal => $memberName) {
                TeamMember::firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'name'    => $memberName,
                    ],
                    [
                        'email'   => strtolower(str_replace(' ', '.', $memberName)) . '@example.test',
                        'ordinal' => $ordinal,
                    ]
                );
            }

            $username = 'team-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT);
            $password = 'demo-pass-' . ($idx + 1);

            $participation = ContestParticipation::firstOrCreate(
                ['contest_id' => $contest->id, 'team_id' => $team->id],
                [
                    'username'                 => $username,
                    'password'                 => Hash::make($password),
                    'seat_number'              => $idx + 1,
                    'credentials_generated_at' => now()->subHours(2),
                ]
            );

            $participations[] = $participation;
            $plainPasswords[$username] = $password;
        }

        // ---------------------------------------------------------------
        // 5. Submissions with mixed verdicts
        //    Guard: only seed submissions if none exist yet for this contest.
        // ---------------------------------------------------------------
        if ($contest->submissions()->count() === 0) {
            $this->seedSubmissions($contest, $participations, $contestProblems, $problems);
        }

        // ---------------------------------------------------------------
        // 6. Q&A
        // ---------------------------------------------------------------
        if ($contest->questions()->count() === 0) {
            $this->seedQuestions($contest, $participations, $admin);
        }

        // ---------------------------------------------------------------
        // 7. Print a summary
        // ---------------------------------------------------------------
        $this->command->info('');
        $this->command->info('========== DEMO SEEDED ==========');
        $this->command->info("Contest URL: /c/{$contest->slug}");
        $this->command->info('Contestant login credentials:');
        foreach ($plainPasswords as $username => $password) {
            $this->command->info("  {$username} / {$password}");
        }
        $this->command->info('=================================');
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * @return array<int, Problem>
     */
    private function seedProblems(User $admin): array
    {
        $specs = [
            [
                'code'  => 'A-PLUS-B',
                'title' => 'A Plus B',
                'body'  => "# A + B\n\nRead two integers and print their sum.",
                'samples' => [
                    ["2 3\n",  "5\n"],
                    ["10 20\n","30\n"],
                ],
                'hidden' => [
                    ["-5 5\n", "0\n"],
                    ["1000000 999999\n", "1999999\n"],
                ],
            ],
            [
                'code'  => 'PALINDROME',
                'title' => 'Palindrome Check',
                'body'  => "# Palindrome\n\nRead a string and print `YES` if it is a palindrome, otherwise `NO`.",
                'samples' => [
                    ["racecar\n", "YES\n"],
                    ["hello\n",   "NO\n"],
                ],
                'hidden' => [
                    ["a\n",       "YES\n"],
                    ["abba\n",    "YES\n"],
                    ["abc\n",     "NO\n"],
                ],
            ],
            [
                'code'  => 'FIZZBUZZ',
                'title' => 'FizzBuzz',
                'body'  => "# FizzBuzz\n\nRead N. Print numbers 1..N, 'Fizz' for multiples of 3, 'Buzz' for 5, 'FizzBuzz' for 15.",
                'samples' => [
                    ["5\n", "1\n2\nFizz\n4\nBuzz\n"],
                ],
                'hidden' => [
                    ["15\n", "1\n2\nFizz\n4\nBuzz\nFizz\n7\n8\nFizz\nBuzz\n11\nFizz\n13\n14\nFizzBuzz\n"],
                ],
            ],
        ];

        $result = [];
        foreach ($specs as $spec) {
            $problem = Problem::firstOrCreate(
                ['code' => $spec['code']],
                [
                    'title'           => $spec['title'],
                    'statement_md'    => $spec['body'],
                    'time_limit_ms'   => 1000,
                    'memory_limit_mb' => 256,
                    'created_by'      => $admin->id,
                ]
            );

            // Only seed test cases if none exist.
            if ($problem->testCases()->count() === 0) {
                $ordinal = 1;

                foreach ($spec['samples'] as [$input, $output]) {
                    $this->createTestCase($problem, $ordinal++, TestCase::KIND_SAMPLE, $input, $output);
                }
                foreach ($spec['hidden'] as [$input, $output]) {
                    $this->createTestCase($problem, $ordinal++, TestCase::KIND_HIDDEN, $input, $output);
                }
            }

            $result[] = $problem;
        }

        return $result;
    }

    private function createTestCase(
        Problem $problem,
        int $ordinal,
        string $kind,
        string $input,
        string $output,
    ): TestCase {
        $disk = config('filesystems.test_cases_disk', 'local');
        $id   = (int) (TestCase::max('id') ?? 0) + 1;

        $inputPath  = "problems/{$problem->id}/test-cases/{$id}-{$ordinal}.in";
        $outputPath = "problems/{$problem->id}/test-cases/{$id}-{$ordinal}.out";

        \Illuminate\Support\Facades\Storage::disk($disk)->put($inputPath, $input);
        \Illuminate\Support\Facades\Storage::disk($disk)->put($outputPath, $output);

        return TestCase::create([
            'problem_id'        => $problem->id,
            'ordinal'           => $ordinal,
            'kind'              => $kind,
            'input_path'        => $inputPath,
            'output_path'       => $outputPath,
            'input_sha256'      => hash('sha256', $input),
            'output_sha256'     => hash('sha256', $output),
            'input_size_bytes'  => strlen($input),
            'output_size_bytes' => strlen($output),
        ]);
    }

    /**
     * @param  array<int, ContestParticipation> $participations
     * @param  array<int, ContestProblem>       $contestProblems   keyed by problem_id
     * @param  array<int, Problem>              $problems
     */
    private function seedSubmissions(
        Contest $contest,
        array $participations,
        array $contestProblems,
        array $problems,
    ): void {
        // Scenario:
        //  - Team 1 solves A and B, one WA on A first.
        //  - Team 2 solves A, one pending submission on B.
        //  - Team 3 solves all three.
        //  - Team 4 solves A only, one TLE on C.
        //  - Team 5 nothing solved, one CE.
        $scenarios = [
            [
                'team'   => 0,
                'items'  => [
                    ['problem' => 0, 'verdict' => Submission::VERDICT_WRONG_ANSWER, 'offset_min' => 55],
                    ['problem' => 0, 'verdict' => Submission::VERDICT_ACCEPTED,     'offset_min' => 50],
                    ['problem' => 1, 'verdict' => Submission::VERDICT_ACCEPTED,     'offset_min' => 30],
                ],
            ],
            [
                'team'  => 1,
                'items' => [
                    ['problem' => 0, 'verdict' => Submission::VERDICT_ACCEPTED, 'offset_min' => 45],
                    ['problem' => 1, 'pending' => true,                          'offset_min' => 5],
                ],
            ],
            [
                'team'  => 2,
                'items' => [
                    ['problem' => 0, 'verdict' => Submission::VERDICT_ACCEPTED, 'offset_min' => 58],
                    ['problem' => 1, 'verdict' => Submission::VERDICT_ACCEPTED, 'offset_min' => 40],
                    ['problem' => 2, 'verdict' => Submission::VERDICT_ACCEPTED, 'offset_min' => 20],
                ],
            ],
            [
                'team'  => 3,
                'items' => [
                    ['problem' => 0, 'verdict' => Submission::VERDICT_ACCEPTED, 'offset_min' => 52],
                    ['problem' => 2, 'verdict' => Submission::VERDICT_TIME_LIMIT, 'offset_min' => 25],
                ],
            ],
            [
                'team'  => 4,
                'items' => [
                    ['problem' => 0, 'verdict' => Submission::VERDICT_COMPILE_ERROR, 'offset_min' => 40],
                ],
            ],
        ];

        $startedAt = $contest->started_at ?? $contest->start_time;

        DB::transaction(function () use ($scenarios, $participations, $contestProblems, $problems, $startedAt, $contest) {
            foreach ($scenarios as $scenario) {
                $participation = $participations[$scenario['team']];

                foreach ($scenario['items'] as $item) {
                    $problem         = $problems[$item['problem']];
                    $contestProblem  = $contestProblems[$problem->id];
                    $isPending       = ! empty($item['pending']);
                    $verdict         = $isPending ? null : $item['verdict'];

                    $submittedAt = $startedAt->copy()->addMinutes($item['offset_min']);

                    $submission = Submission::create([
                        'contest_id'          => $contest->id,
                        'participation_id'    => $participation->id,
                        'contest_problem_id'  => $contestProblem->id,
                        'language'            => 'python',
                        'source_path'         => 'sources/pending',
                        'status'              => $isPending ? Submission::STATUS_JUDGING : Submission::STATUS_DONE,
                        'verdict'             => $verdict,
                        'max_time_ms'         => $isPending ? null : 120,
                        'max_memory_kb'       => $isPending ? null : 12000,
                        'judge_job_id'        => 'job_demo_' . $submission_id_placeholder = uniqid(),
                        'is_sample_run'       => false,
                        'submitted_at'        => $submittedAt,
                        'judged_at'           => $isPending ? null : $submittedAt->copy()->addSeconds(8),
                    ]);

                    $sourcePath = "sources/{$contest->id}/{$participation->id}/{$submission->id}.txt";
                    \Illuminate\Support\Facades\Storage::disk(config('filesystems.sources_disk', 'local'))
                        ->put($sourcePath, "print('demo source')\n");
                    $submission->forceFill(['source_path' => $sourcePath])->save();

                    // Create per-test-case results for non-pending submissions.
                    if (! $isPending) {
                        $this->seedSubmissionResults($submission, $problem, $verdict);
                    }
                }
            }
        });
    }

    private function seedSubmissionResults(Submission $submission, Problem $problem, string $verdict): void
    {
        $testCases = $problem->testCases()->orderBy('ordinal')->get();
        $firstFailureIdx = null;

        // Accepted → all pass. Otherwise → fail at a specific case and stop.
        if ($verdict !== Submission::VERDICT_ACCEPTED) {
            $firstFailureIdx = $testCases->count() > 1 ? 1 : 0;
        }

        foreach ($testCases as $idx => $testCase) {
            if ($firstFailureIdx !== null && $idx > $firstFailureIdx) {
                break; // early stop — judge stops at first failure
            }

            $isFailing = $firstFailureIdx !== null && $idx === $firstFailureIdx;
            $caseVerdict = $isFailing ? $verdict : Submission::VERDICT_ACCEPTED;

            SubmissionResult::create([
                'submission_id' => $submission->id,
                'test_case_id'  => $testCase->id,
                'verdict'       => $caseVerdict,
                'time_ms'       => $caseVerdict === Submission::VERDICT_TIME_LIMIT ? 2000 : random_int(20, 300),
                'memory_kb'     => random_int(8000, 30000),
                'message'       => $isFailing && $caseVerdict === Submission::VERDICT_WRONG_ANSWER
                    ? 'Output mismatch'
                    : null,
            ]);
        }
    }

    /**
     * @param array<int, ContestParticipation> $participations
     */
    private function seedQuestions(Contest $contest, array $participations, User $admin): void
    {
        $q1 = ContestQuestion::create([
            'contest_id'       => $contest->id,
            'participation_id' => $participations[0]->id,
            'body'             => 'For problem A, can the numbers be negative?',
            'visibility'       => ContestQuestion::VISIBILITY_PRIVATE,
        ]);

        $q2 = ContestQuestion::create([
            'contest_id'       => $contest->id,
            'participation_id' => $participations[1]->id,
            'body'             => 'Is FizzBuzz case-sensitive?',
            'answer'           => 'Yes — output must be exactly "Fizz", "Buzz", "FizzBuzz".',
            'answered_by'      => $admin->id,
            'answered_at'      => now()->subMinutes(15),
            'visibility'       => ContestQuestion::VISIBILITY_PUBLIC,
        ]);

        ContestQuestion::create([
            'contest_id'       => $contest->id,
            'participation_id' => $participations[3]->id,
            'body'             => 'What is the maximum N for FizzBuzz?',
            'visibility'       => ContestQuestion::VISIBILITY_PRIVATE,
        ]);
    }
}
