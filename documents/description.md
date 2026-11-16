# CodeArena – Eloquent Models Reference

This document provides a reference for all 11 Eloquent models in CodeArena. It describes each model's responsibilities, relationships, constants, helper methods, query scopes, and important configuration.

---

# 1. User Model

**File:** `app/Models/User.php`

Represents administrators and operators who manage the platform, create problems, and manage contests.

### Constants and configuration

- `ROLE_ADMIN`: Identifies an administrator.
- `ROLE_OPERATOR`: Identifies an operator.
- **Fillable attributes:** `name`, `email`, `password`, `role`.
- **Hidden attributes:** `password`, `remember_token`.
- **Casts:**
  - Password is automatically hashed.
  - `email_verified_at`, `created_at`, and `updated_at` are converted to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `createdContests()` | HasMany | Contests created by this user. |
| `approvedContests()` | HasMany | Contests approved by this user. |
| `createdProblems()` | HasMany | Problems created by this user. |
| `answeredQuestions()` | HasMany | Contest questions answered by this user. |

### Helper methods

| Method | Description |
|---|---|
| `isAdmin()` | Checks whether the user's role is administrator. |

### Other features

- Uses `HasFactory`.
- Extends Laravel's `Authenticatable`.
- Uses `Notifiable` for Laravel notifications.

---

# 2. Team Model

**File:** `app/Models/Team.php`

Represents a team participating in programming contests. A team can have multiple members and contest participations.

### Configuration

- **Fillable attributes:** `name`.
- **Casts:** `created_at` and `updated_at` to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `members()` | HasMany | Retrieves team members ordered by their ordinal position. |
| `participations()` | HasMany | Retrieves contest participations associated with the team. |

### Helper methods

| Method | Description |
|---|---|
| `fullLabel()` | Returns the team name followed by its members' names in parentheses. If there are no members, returns only the team name. |

### Other features

- Uses `HasFactory`.

---

# 3. TeamMember Model

**File:** `app/Models/TeamMember.php`

Represents an individual member belonging to a team.

### Configuration

- **Fillable attributes:** `team_id`, `name`, `email`, `ordinal`.
- **Casts:**
  - `ordinal` to integer.
  - `created_at` and `updated_at` to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `team()` | BelongsTo | Retrieves the team to which this member belongs. |

### Other features

- Uses `HasFactory`.

---

# 4. Contest Model

**File:** `app/Models/Contest.php`

Represents a programming contest. It manages contest information, scheduling, approval, lifecycle transitions, problems, participants, submissions, and questions.

### Status constants

| Constant | Value | Description |
|---|---|---|
| `STATUS_DRAFT` | `draft` | Contest is being prepared. |
| `STATUS_APPROVED` | `approved` | Contest has been approved. |
| `STATUS_RUNNING` | `running` | Contest is currently running. |
| `STATUS_ENDED` | `ended` | Contest has finished. |

### Configuration

- **Fillable attributes:** `slug`, `name`, `status`, scheduling fields, `started_at`, `ended_at`, `freeze_minutes`, `is_public`, approval metadata, and creator information.
- **Guarded attributes:** `id`, `active_slot`.
- **Casts:**
  - Contest date/time fields to datetime.
  - `is_public` to boolean.
  - `freeze_minutes` to integer.
  - `created_at` and `updated_at` to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `creator()` | BelongsTo | Retrieves the user who created the contest. |
| `approver()` | BelongsTo | Retrieves the user who approved the contest. |
| `participations()` | HasMany | Retrieves all team participations in the contest. |
| `contestProblems()` | HasMany | Retrieves contest-problem records ordered by `sort_order`. |
| `problems()` | BelongsToMany | Retrieves problems associated with the contest, including pivot fields `label`, `points`, and `sort_order`, with pivot timestamps. |
| `submissions()` | HasMany | Retrieves submissions belonging to the contest. |
| `questions()` | HasMany | Retrieves questions submitted during the contest. |

### State helper methods

| Method | Description |
|---|---|
| `isDraft()` | Checks whether the contest is in draft status. |
| `isApproved()` | Checks whether the contest is approved. |
| `isRunning()` | Checks whether the contest is running. |
| `isEnded()` | Checks whether the contest has ended. |
| `isLocked()` | Checks whether the contest is approved, running, or ended. |
| `acceptsSubmissions()` | Checks whether the contest is currently running. |
| `startTimeLocked()` | Checks whether the scheduled start time has been reached or passed. |
| `endTimeLocked()` | Checks whether the contest is no longer in draft status. |

### Query scopes

| Scope | Description |
|---|---|
| `scopeDraft()` | Filters contests with draft status. |
| `scopeApproved()` | Filters contests with approved status. |
| `scopeRunning()` | Filters contests with running status. |
| `scopeEnded()` | Filters contests with ended status. |
| `scopeOccupiesActiveSlot()` | Filters approved and running contests. |

### Static and lifecycle methods

| Method | Description |
|---|---|
| `activeContest()` | Returns the first contest that is approved or running, or `null` if none exists. |
| `syncState()` | Automatically transitions approved contests to running when their start time arrives and running contests to ended when their end time arrives. Saves only when a transition occurs. |
| `manualStart()` | Manually starts a contest and records its start timestamp. |
| `manualEnd()` | Manually ends a contest and records its end timestamp. |

### Other features

- Uses `HasFactory`.
- Supports automatic and manual contest lifecycle management.
- Uses an `active_slot` database attribute to support active-contest constraints.

---

# 5. ContestParticipation Model

**File:** `app/Models/ContestParticipation.php`

Represents a team account within a specific contest. It is also responsible for storing the team's contest-specific login credentials and seat information.

### Configuration

- **Fillable attributes:** `contest_id`, `team_id`, `username`, `password`, `seat_number`, `credentials_generated_at`.
- **Hidden attributes:** `password`.
- **Casts:**
  - Password is automatically hashed.
  - `seat_number` to integer.
  - `credentials_generated_at`, `created_at`, and `updated_at` to datetime.

### Authentication

- Extends Laravel's `Authenticatable`.
- Can be used with the dedicated `contestant` authentication guard.
- Represents contest-scoped team authentication rather than regular platform-user authentication.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `contest()` | BelongsTo | Retrieves the contest associated with this participation. |
| `team()` | BelongsTo | Retrieves the participating team. |
| `submissions()` | HasMany | Retrieves submissions made by this participation. |
| `questions()` | HasMany | Retrieves questions submitted by this participation. |

### Helper methods

| Method | Description |
|---|---|
| `teamLabel()` | Returns the associated team's name. If no team exists, returns the participation username. |
| `hasGeneratedCredentials()` | Checks whether contest credentials have been generated by checking `credentials_generated_at`. |

### Other features

- Uses `HasFactory`.
- Supports contest-specific team authentication.
- Keeps contest login credentials separate from regular user accounts.

---

# 6. Problem Model

**File:** `app/Models/Problem.php`

Represents a programming problem. It stores the problem statement, execution limits, creator information, and associated test cases and contests.

### Configuration

- **Fillable attributes:** `code`, `title`, `statement_md`, `time_limit_ms`, `memory_limit_mb`, `created_by`.
- **Casts:**
  - `time_limit_ms` and `memory_limit_mb` to integer.
  - `created_at` and `updated_at` to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `creator()` | BelongsTo | Retrieves the user who created the problem. |
| `testCases()` | HasMany | Retrieves all test cases ordered by `ordinal`. |
| `sampleTestCases()` | HasMany | Retrieves only sample test cases ordered by `ordinal`. |
| `hiddenTestCases()` | HasMany | Retrieves only hidden test cases ordered by `ordinal`. |
| `contestProblems()` | HasMany | Retrieves contest-problem records associated with this problem. |
| `contests()` | BelongsToMany | Retrieves contests using this problem, including pivot fields `label`, `points`, and `sort_order`, with pivot timestamps. |

### Helper methods

| Method | Description |
|---|---|
| `isInUse()` | Checks whether the problem is referenced by any approved, running, or ended contest. |
| `nextTestCaseOrdinal()` | Returns the next available test-case ordinal by finding the maximum existing ordinal and adding one. |

### Important behavior

- A problem is considered in use when it belongs to a contest that is approved, running, or ended.
- While a problem is in use, editing its statement or test cases must be blocked at the controller level.
- Test cases are retrieved in ordinal order.

### Other features

- Uses `HasFactory`.
- Uses `Builder` for relationship-based query filtering.

---

# 7. ContestProblem Model

**File:** `app/Models/ContestProblem.php`

Represents the association between a contest and a problem. It stores contest-specific information such as the problem label, points, and display order.

### Configuration

- **Fillable attributes:** `contest_id`, `problem_id`, `label`, `points`, `sort_order`.
- **Casts:**
  - `points` and `sort_order` to integer.
  - `created_at` and `updated_at` to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `contest()` | BelongsTo | Retrieves the contest associated with this record. |
| `problem()` | BelongsTo | Retrieves the actual programming problem. |
| `submissions()` | HasMany | Retrieves submissions associated with this contest-problem record. |

### Helper methods

| Method | Description |
|---|---|
| `displayLabel()` | Returns a human-readable label combining the contest problem label and the actual problem title, such as `A — Two Sum`. |

### Other features

- Uses `HasFactory`.
- Provides a separate record for each problem's participation in a contest.
- Allows the same problem to have different labels, points, and ordering in different contests.

---

# 8. TestCase Model

**File:** `app/Models/TestCase.php`

Represents an individual test case belonging to a programming problem. It stores metadata about input and expected output files, while the actual contents are stored using Laravel's filesystem.

### Constants

| Constant | Value | Description |
|---|---|---|
| `KIND_SAMPLE` | `sample` | Test case visible to contestants. |
| `KIND_HIDDEN` | `hidden` | Test case hidden from contestants. |

### Configuration

- **Fillable attributes:** `problem_id`, `ordinal`, `kind`, `input_path`, `output_path`, `input_sha256`, `output_sha256`, `input_size_bytes`, `output_size_bytes`.
- **Casts:**
  - `ordinal`, `input_size_bytes`, and `output_size_bytes` to integer.
  - `created_at` and `updated_at` to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `problem()` | BelongsTo | Retrieves the problem to which this test case belongs. |
| `submissionResults()` | HasMany | Retrieves submission results associated with this test case. |

### Storage helper methods

| Method | Description |
|---|---|
| `disk()` | Returns the configured filesystem disk from `filesystems.test_cases_disk`, defaulting to `local`. |
| `readInput()` | Reads the input file content from storage. |
| `readOutput()` | Reads the expected output file content from storage. |
| `writeInput()` | Stores input content, calculates its SHA-256 hash and byte size, and updates the database metadata. |
| `writeOutput()` | Stores expected output content, calculates its SHA-256 hash and byte size, and updates the database metadata. |
| `deleteFiles()` | Deletes the input and output files from storage. |

### State helper methods

| Method | Description |
|---|---|
| `isSample()` | Checks whether the test case is a sample test case. |
| `isHidden()` | Checks whether the test case is a hidden test case. |

### Lifecycle hooks

| Hook | Description |
|---|---|
| `booted()` | Registers a model deletion event. |
| `deleting` | Calls `deleteFiles()` before the test-case model is deleted. |

### Important behavior

- The database stores file paths and metadata, not the actual test-case contents.
- Input and output files are stored using Laravel's filesystem.
- SHA-256 hashes can be used to verify file integrity.
- File sizes are stored in bytes.
- Deleting an individual model instance automatically deletes its associated files.
- Mass deletion through a database query does not trigger individual model deletion events.

### Other features

- Uses `HasFactory`.
- Supports configurable filesystem storage.

---

# 9. Submission Model

**File:** `app/Models/Submission.php`

Represents a team's submitted source code for a problem during a contest. It tracks the submission's judging lifecycle, verdict, execution statistics, score, and rejudge information.

### Status constants

| Constant | Value | Description |
|---|---|---|
| `STATUS_QUEUED` | `queued` | Submission is waiting for judging. |
| `STATUS_JUDGING` | `judging` | Submission is currently being judged. |
| `STATUS_DONE` | `done` | Judging has completed. |
| `STATUS_ERROR` | `error` | An internal judging error occurred. |

### Verdict constants

| Constant | Value | Description |
|---|---|---|
| `VERDICT_ACCEPTED` | `AC` | All required tests passed. |
| `VERDICT_WRONG_ANSWER` | `WA` | Program produced an incorrect answer. |
| `VERDICT_TIME_LIMIT` | `TLE` | Program exceeded the time limit. |
| `VERDICT_MEMORY_LIMIT` | `MLE` | Program exceeded the memory limit. |
| `VERDICT_RUNTIME_ERROR` | `RE` | Program encountered a runtime error. |
| `VERDICT_COMPILE_ERROR` | `CE` | Program failed to compile. |
| `VERDICT_INTERNAL_ERROR` | `IE` | An internal judging error occurred. |

### Configuration

- **Fillable attributes:** `contest_id`, `participation_id`, `contest_problem_id`, `language`, `source_path`, `status`, `verdict`, `max_time_ms`, `max_memory_kb`, `score`, `judge_job_id`, `is_sample_run`, `submitted_at`, `judged_at`, `rejudge_count`, `last_rejudged_at`, `last_rejudged_by`.
- **Casts:**
  - `max_time_ms`, `max_memory_kb`, and `rejudge_count` to integer.
  - `score` to decimal with two decimal places.
  - `is_sample_run` to boolean.
  - Submission and rejudge timestamps to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `contest()` | BelongsTo | Retrieves the contest in which the submission was made. |
| `participation()` | BelongsTo | Retrieves the team participation that submitted the source code. |
| `contestProblem()` | BelongsTo | Retrieves the contest-problem record associated with the submission. |
| `results()` | HasMany | Retrieves individual test-case results for this submission. |
| `lastRejudgedBy()` | BelongsTo | Retrieves the user who last rejudged the submission. |

### Convenience accessor methods

| Method | Description |
|---|---|
| `problem()` | Returns the actual problem through the associated contest-problem record, or `null` if unavailable. |
| `label()` | Returns the contest-specific problem label, or `null` if unavailable. |
| `team()` | Returns the team associated with the participation, or `null` if unavailable. |

### State helper methods

| Method | Description |
|---|---|
| `isFinal()` | Checks whether the submission status is `done` or `error`. |
| `isAccepted()` | Checks whether the submission verdict is `AC`. |
| `isPending()` | Checks whether the submission is queued or currently being judged. |
| `minutesSinceContestStart()` | Calculates the whole number of minutes between the contest start time and submission time. Uses `started_at` when available, otherwise `start_time`. |

### Storage helper

| Method | Description |
|---|---|
| `readSource()` | Reads the submitted source code from the configured `filesystems.sources_disk`, defaulting to `local`. |

### Query scopes

| Scope | Description |
|---|---|
| `scopeAccepted()` | Retrieves submissions with an accepted verdict. |
| `scopeJudged()` | Retrieves submissions whose status is `done`. |
| `scopePending()` | Retrieves queued and currently judging submissions. |
| `scopeForParticipation()` | Filters submissions belonging to a specific participation ID. |
| `scopeForContestProblem()` | Filters submissions belonging to a specific contest-problem ID. |
| `scopeScoreboard()` | Retrieves completed, non-sample submissions for a specific contest. |

### Other features

- Uses `HasFactory`.
- Stores source code externally through Laravel's filesystem.
- Tracks judging status and verdict separately.
- Supports sample runs that can be excluded from scoreboard calculations.
- Tracks rejudge count, timestamp, and responsible user.

---

# 10. SubmissionResult Model

**File:** `app/Models/SubmissionResult.php`

Represents the result of executing a submission against an individual test case. It stores the verdict and execution statistics for that test case.

### Configuration

- **Fillable attributes:** `submission_id`, `test_case_id`, `verdict`, `time_ms`, `memory_kb`, `message`.
- **Casts:**
  - `time_ms` and `memory_kb` to integer.
  - `created_at` and `updated_at` to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `submission()` | BelongsTo | Retrieves the submission associated with this result. |
| `testCase()` | BelongsTo | Retrieves the test case against which the submission was executed. |

### Helper methods

| Method | Description |
|---|---|
| `isAccepted()` | Checks whether this test-case result has an accepted verdict. |
| `contentVisibleToContestant()` | Checks whether the test-case content can be shown to the contestant. Returns `true` for sample test cases and `false` for hidden test cases. |

### Important behavior

- Sample test-case results can expose their input and expected output content.
- Hidden test-case results should expose only the verdict and execution statistics, such as time and memory.
- The visibility check uses the associated test case's `isSample()` method.

### Other features

- Uses `HasFactory`.
- Stores individual execution results rather than only the final submission verdict.

---

# 11. ContestQuestion Model

**File:** `app/Models/ContestQuestion.php`

Represents a question submitted by a participating team during a contest. It supports public and private questions, answers from operators or administrators, and visibility rules for other teams.

### Visibility constants

| Constant | Value | Description |
|---|---|---|
| `VISIBILITY_PUBLIC` | `public` | Question and answer may become visible to other teams once answered. |
| `VISIBILITY_PRIVATE` | `private` | Question and answer remain private to the submitting team. |

### Configuration

- **Fillable attributes:** `contest_id`, `participation_id`, `body`, `answer`, `answered_by`, `answered_at`, `visibility`.
- **Casts:**
  - `answered_at`, `created_at`, and `updated_at` to datetime.

### Relationships

| Method | Relationship | Description |
|---|---|---|
| `contest()` | BelongsTo | Retrieves the contest in which the question was submitted. |
| `participation()` | BelongsTo | Retrieves the team participation that submitted the question. |
| `answeredBy()` | BelongsTo | Retrieves the user who answered the question. |

### State helper methods

| Method | Description |
|---|---|
| `isAnswered()` | Checks whether the question has been answered by checking `answered_at`. |
| `isPublic()` | Checks whether the question visibility is public. |
| `isPrivate()` | Checks whether the question visibility is private. |
| `isVisibleToOtherTeams()` | Checks whether the question is both public and answered. |

### Query scopes

| Scope | Description |
|---|---|
| `scopeUnanswered()` | Retrieves questions that have not been answered. |
| `scopeAnswered()` | Retrieves questions that have been answered. |
| `scopeVisibleToTeams()` | Retrieves questions visible to a particular participation: its own questions regardless of visibility, plus answered public questions from other teams. |

### Important behavior

- Private questions remain private regardless of whether they have been answered.
- Public questions become visible to other teams only after they are answered.
- Unanswered public questions are not exposed to other teams.
- The submitting team can retrieve its own questions regardless of their visibility setting.

### Other features

- Uses `HasFactory`.
- Uses `Builder` for query scopes.
- Supports contest communication between participating teams and contest administrators/operators.

---

# Model Relationship Overview

The following table summarizes the main relationships between the 11 models.

| Model | Main relationships |
|---|---|
| User | Created contests, approved contests, created problems, answered questions |
| Team | Team members, contest participations |
| TeamMember | Belongs to a team |
| Contest | Creator, approver, participations, problems, submissions, questions |
| ContestParticipation | Contest, team, submissions, questions |
| Problem | Creator, test cases, contest associations |
| ContestProblem | Contest, problem, submissions |
| TestCase | Problem, submission results |
| Submission | Contest, participation, contest problem, results, last rejudge user |
| SubmissionResult | Submission, test case |
| ContestQuestion | Contest, participation, answering user |

---

# Important Design Notes

1. **User and ContestParticipation are different authentication models.** User represents platform administrators/operators, while ContestParticipation represents a team's account within a specific contest.

2. **Team and ContestParticipation are different entities.** A Team represents the actual team, while ContestParticipation represents that team's participation in a particular contest.

3. **ContestProblem is the association between Contest and Problem.** It stores contest-specific problem information such as label, points, and ordering.

4. **Test-case contents are stored in the filesystem.** The database stores file paths, hashes, sizes, and other metadata.

5. **Submission and SubmissionResult have different responsibilities.** Submission stores the overall judging state and verdict, while SubmissionResult stores the result of each individual test case.

6. **Sample and hidden test cases have different visibility rules.** Sample content may be exposed to contestants, while hidden test-case content must remain protected.

7. **Contest lifecycle is managed by the Contest model.** It provides status checks, query scopes, automatic state synchronization, and manual start/end operations.

8. **Problem editing is restricted when a problem is in use.** A problem referenced by an approved, running, or ended contest must not have its statement or test cases modified.

9. **Contest questions have visibility rules.** A public question is visible to other teams only after it has been answered. Private questions remain private.

10. **Query scopes provide reusable database filters.** They allow controllers and other application components to retrieve records using consistent conditions.

---

**Total models documented: 11**

- User
- Team
- TeamMember
- Contest
- ContestParticipation
- Problem
- ContestProblem
- TestCase
- Submission
- SubmissionResult
- ContestQuestion
