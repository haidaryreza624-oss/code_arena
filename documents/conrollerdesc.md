# Controller Descriptions

This document describes the 21 concrete controllers added from `controllers.md`. It lists each controller's methods and their responsibilities; it does not reproduce the method implementations.

## Admin Controllers

### `AdminAuthController`

- `showLogin` — Redirects an already authenticated administrator to the dashboard; otherwise displays the login page.
- `login` — Validates the submitted email and password, authenticates the administrator, regenerates the session, and redirects to the intended destination or dashboard. Invalid credentials produce a validation error.
- `logout` — Logs out the administrator, invalidates the session, regenerates the CSRF token, and redirects to the login page.

### `AdminDashboardController`

- `index` — Loads contests with their creators in status and start-time order, obtains the active contest, calculates overall problem, team, contest, and submission totals, and displays the dashboard.

### `ContestController`

- `index` — Lists contests with participation and problem counts, ordered by status and start time, with pagination.
- `create` — Displays the form for creating a draft contest.
- `store` — Validates contest details, generates a unique slug, records the creator, creates the contest in draft state, and redirects to its page.
- `show` — Loads contest relationships, calculates statistics appropriate to the contest's current state, and displays that state's admin page.
- `edit` — Allows editing only while the contest is a draft, then displays the edit form.
- `update` — Allows updates only for drafts, validates the submitted values, prevents changing a locked start time, updates the slug if the name changed, and saves the contest.
- `destroy` — Deletes a draft contest and returns to the contest list; contests outside draft state cannot be deleted.
- `validateContest` — Validates contest name, schedule, freeze duration, and visibility, applying a name-uniqueness rule appropriate to new or existing contests and defaulting optional values.
- `uniqueSlug` — Converts a contest name to a URL-friendly slug and appends an incrementing suffix until the slug is unused, optionally excluding the contest being edited.

### `ContestProblemController`

- `index` — Loads the contest's assigned problems and lists available problems that have not yet been attached.
- `store` — Allows assignments only for draft contests, validates the problem, label, and points, rejects duplicate problems or labels, and attaches the problem with the next sort order.
- `update` — Allows changes only for draft contests, verifies the assignment belongs to the contest, rejects label collisions, and updates its label and points.
- `destroy` — Allows removal only for draft contests, verifies the assignment belongs to the contest, and detaches it.
- `reorder` — Validates an ordered list of assignment IDs and updates the sort order of matching assignments in the contest.

### `ContestApprovalController`

- `__invoke` — Approves a draft contest through the approval action after checking for another active contest, at least one assigned problem and team, and a future end time. It warns the administrator if the start time has already passed.

### `ContestStartController`

- `__invoke` — Allows only an approved contest to be manually started, invokes the start action, and redirects to the contest page with a status message.

### `ContestEndController`

- `__invoke` — Allows only a running contest to be manually ended, invokes the end action, and redirects to the contest page with a status message.

### `TeamImportController`

- `show` — Displays the team CSV import form only while the contest is a draft.
- `store` — Accepts and validates a CSV or text upload for a draft contest, imports its teams through the import action, and reports created, skipped, and erroneous rows.

### `CredentialController`

- `index` — Makes the credential page available for approved contests and lists their teams' participations in username order.
- `generate` — Creates credentials only for teams that do not already have them and streams a PDF containing the new credentials. If none are missing, it asks the administrator to use regeneration instead.
- `regenerate` — Replaces all credentials for an approved contest and streams a PDF containing the replacement credentials.

### `ProblemController`

- `index` — Lists problems with test-case and contest-assignment counts, ordered by problem code and paginated.
- `create` — Displays the problem creation form.
- `store` — Validates problem details, records the administrator who created it, creates the problem, and redirects to its page.
- `show` — Loads a problem's test cases and contest assignments, then displays its details.
- `edit` — Displays the edit form unless the problem is in use by a locked contest.
- `update` — Prevents editing a problem in use by a locked contest; otherwise validates and saves the updated details.
- `destroy` — Prevents deleting a problem in use by a locked contest; otherwise deletes it and returns to the problem list.
- `validateProblem` — Validates the problem code, title, statement, time limit, and memory limit, enforcing a unique code while allowing the current problem's code during edits.

### `TestCaseController`

- `index` — Loads and displays the test cases belonging to a problem.
- `store` — Prevents changes to locked problems, validates the input and output uploads and case kind, stores both files, and creates a test-case record with paths, checksums, sizes, and the next ordinal.
- `update` — Verifies that the test case belongs to the problem, prevents changes to locked problems, and updates the case kind.
- `destroy` — Verifies ownership, prevents deletion when the problem is locked, and deletes the test-case record; the model handles associated file cleanup.
- `download` — Verifies ownership and the requested input/output side, then downloads the corresponding test-case file.

### `AdminSubmissionController`

- `index` — Lists a contest's submissions and supports filtering by pending or specific status, verdict, and problem label.
- `show` — Verifies the submission belongs to the contest, loads its team, problem, and result details, and displays it.
- `rejudge` — Verifies contest ownership and that the submission is finalized, clears prior results and judging data, records who requested the rejudge, and resubmits it to the judge. If the judge is unreachable, marks the submission as an internal error and reports the failure.

### `AdminScoreboardController`

- `show` — Builds the administrator's unfrozen scoreboard and returns it as JSON when requested, otherwise displaying the scoreboard page.

### `AdminQuestionController`

- `index` — Lists a contest's questions with team and responder details, placing unanswered questions first.
- `answer` — Verifies the question belongs to the contest, validates the answer and its visibility, records the responding administrator and answer time, and saves the response.

## Contestant Controllers

### `ContestantAuthController`

- `showLogin` — Redirects a contestant already logged into this contest to its problems page; otherwise displays the contest login form.
- `login` — Validates the username and password, finds the matching participation in this contest, verifies its password, logs it into the contestant guard, regenerates the session, and redirects to the intended destination or problems page.
- `logout` — Logs out the contestant, invalidates the session, regenerates the CSRF token, and redirects to this contest's login page.
- `alreadyLoggedIn` — Checks whether the currently authenticated contestant belongs to the specified contest.

### `ContestantLandingController`

- `show` — Displays the contest landing page and supplies the authenticated participation only when it belongs to that contest.

### `ContestantProblemController`

- `index` — Lists the contest's problems in configured order and, for the current participation, marks each problem as solved or attempted based on non-sample submissions.
- `show` — Finds a contest problem by label, loads its statement and sample cases, and displays up to 20 of the current participation's non-sample submissions for that problem.

### `SubmitController`

- `store` — Verifies the contestant belongs to the contest and that submissions are accepted, finds the selected problem, enforces the per-participation rate limit, validates source text or file and language, and checks that the judge supports that language. It stores the source, creates a queued submission, and sends it to the judge; an unavailable judge leaves the submission recorded as an internal error.

### `ContestantSubmissionController`

- `index` — Lists the authenticated participation's non-sample submissions, newest first, with pagination and problem details.
- `show` — Verifies submission ownership, loads problem and test-result details, and displays the submission.
- `status` — Verifies ownership and returns finalized submissions immediately. For unfinished submissions it avoids duplicate judge polling within a short interval, fetches and processes judge status when needed, refreshes the submission, and returns its current status as JSON.
- `authorizeOwnership` — Ensures the authenticated participation owns the submission and that it belongs to the requested contest; otherwise denies access.
- `statusResponse` — Formats a submission's finality, status, verdict, and detail-page URL as a JSON response.

### `ContestantScoreboardController`

- `show` — Requires contest-scoped authentication for a non-public contest, determines whether the running contest's scoreboard should be frozen, builds the scoreboard, and returns JSON or the HTML scoreboard page.
- `requireContestantAuth` — Confirms the authenticated participation belongs to the contest; otherwise denies access to its non-public scoreboard.

### `ContestantQuestionController`

- `index` — Lists questions visible to the authenticated participation, with their associated team details, newest first.
- `store` — Accepts questions only while the contest is running, validates the question text, enforces a per-participation rate limit, and creates a private question for the administrators.
