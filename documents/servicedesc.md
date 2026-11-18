# Service and Support Class Descriptions

This document describes the five service/support classes listed in `srvice.md`. It covers their methods and responsibilities without reproducing their implementations.

## `JudgeException`

Typed exception for failures involving the judge API. It can carry structured context and a previous exception.

- `__construct` — Initializes the exception message, optional context, and previous exception.
- `unreachable` — Creates an exception for a judge connection failure, preserving the underlying error.
- `rejected` — Creates an exception for an unsuccessful HTTP response and includes its status and a truncated response body in the context.
- `invalidResponse` — Creates an exception describing a malformed or unexpected response from the judge.
- `invalidPayload` — Creates an exception describing a payload that the application cannot construct for the judge.

## `JudgeClient`

The application's HTTP integration with the judge. It owns the judge endpoint details, request and response handling, wire-format conversions, and verdict mapping.

- `__construct` — Reads the judge base URL, endpoint paths, and request timeouts from configuration.
- `submit` — Builds and sends a judge job from a submission, including source, test cases, normalized language, and converted problem limits. Validates the response and returns the job ID and status.
- `status` — Requests the status of a judge job and validates that the response is JSON data.
- `languages` — Retrieves and validates the judge's supported-language list.
- `mapVerdict` — Converts a judge verdict string into the application's internal verdict code; unknown values become an internal-error verdict.
- `displayVerdict` — Converts an internal verdict code into a human-readable label for display, preserving unknown codes and representing a missing verdict.
- `jobIsPending` — Reports whether a judge job is queued or running.
- `jobIsCompleted` — Reports whether a judge job has completed.
- `jobIsFailed` — Reports whether a judge job has failed.
- `buildTestCasesPayload` — Loads test cases in ordinal order, optionally limits them to samples, reads their input and expected output, and formats them for the judge.
- `msToSeconds` — Converts the problem time limit from milliseconds to whole seconds, rounding up so the limit is not shortened.
- `mbToKb` — Converts the problem memory limit from megabytes to kilobytes.
- `secondsToMs` — Converts judge execution time in seconds to database milliseconds.
- `normalizeLanguage` — Trims and lowercases a language name to match the judge's expected format.

## `JudgeLanguages`

Cached access to the judge's language list for forms and submission validation.

- `__construct` — Receives the `JudgeClient` used to retrieve judge language data.
- `all` — Returns the full judge language list from cache or fetches and caches it; reports retrieval errors and returns an empty list if retrieval fails.
- `available` — Filters the full list to languages whose compiler or runtime is available on the judge host.
- `availableNames` — Returns the available language names in lowercase.
- `supports` — Checks whether a submitted language name matches one of the available names after trimming and lowercasing.
- `forget` — Removes the cached judge language list so it will be fetched again.

## `SubmissionResultProcessor`

Applies judge job responses to submissions, persists per-test-case results, finalizes submissions, and invalidates scoreboard caches. Its public operation is idempotent for submissions already finalized.

- `__construct` — Receives the `JudgeClient` used to interpret judge status and verdicts.
- `apply` — Leaves finalized submissions untouched; otherwise dispatches pending, completed, or failed judge jobs to the corresponding handler and rejects unknown job statuses.
- `handlePending` — Records when the pending job was last checked and reports that the submission is not finalized.
- `handleCompleted` — Validates the completed result, maps its verdict, associates returned test results with local test cases, writes results and final submission fields in a database transaction, and invalidates scoreboard caches.
- `handleFailed` — Marks a failed judge job as an internal error and reports that the submission was finalized.
- `markInternalError` — Sets the submission's error status and internal-error verdict, records judging and check timestamps, and invalidates both scoreboard caches for the contest.
- `buildMessage` — Extracts non-empty standard-error text from a test result and truncates it for storage; returns no message when there is no standard error.
- `invalidateScoreboardCache` — Removes the frozen and unfrozen scoreboard cache entries for the submission's contest.

## `ScoreboardService`

Builds and caches an ICPC-style contest scoreboard, including standings, per-problem status, shared ranks, and optional freeze behavior.

- `build` — Returns a cached scoreboard for the contest and freeze mode, computing it when the cache entry is missing or expired.
- `cacheKey` — Produces the cache key for a contest's frozen or unfrozen scoreboard.
- `forget` — Removes both frozen and unfrozen scoreboard cache entries for a contest.
- `compute` — Loads contest participations, problems, and completed non-sample submissions; builds each team's row, sorts the rows, assigns ranks, and returns the scoreboard.
- `buildRow` — Calculates one team's solved count, penalty, last accepted time, and status and attempt details for each problem. It excludes freeze-window submissions from scoring when freeze mode is active.
- `sortRows` — Orders teams by most problems solved, then lowest penalty, then team name for deterministic display order.
- `assignRanks` — Assigns competition ranks, sharing ranks for equal solved counts and penalties, and formats each team's last accepted timestamp for output.
- `freezeStart` — Calculates the freeze-window start from the contest end time and freeze duration, or returns no cutoff when freeze mode is inactive or not configured.
