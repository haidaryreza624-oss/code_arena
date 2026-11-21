# CodeArena Laravel ↔ Judge Engine API Documentation

This document explains how the Laravel application communicates with the
Python FastAPI Judge Engine to submit source code, send test cases,
check submission status, and retrieve final verdicts.

## 1. Architecture

The application has two separate responsibilities:

-   **Laravel --- CodeArena:** users, problems, test cases, submissions,
    contests, leaderboards, and the application database.
-   **Python --- Judge Engine:** queue submissions, compile and execute
    code, compare outputs, and return verdicts.

The important rule is:

> **Laravel owns the data. The Judge Engine only executes submissions.**

For example, Laravel stores each problem and its hidden test cases. When
a participant submits code, Laravel sends the source code and test cases
to the Judge Engine. The Judge Engine executes the code and returns the
result. Laravel then stores that result in its own database.

## 2. Judge Engine API information

Assuming the Judge Engine is running locally:

  Setting                         Value
  ------------------------------- ------------------------------------
  Base URL                        `http://127.0.0.1:9000`
  API version                     `v1`
  Request format                  JSON
  Response format                 JSON
  Submission endpoint             `POST /api/v1/submissions/`
  Status endpoint                 `GET /api/v1/submissions/{job_id}`
  Languages endpoint              `GET /api/v1/languages/`
  Interactive API documentation   `/docs`

The Judge Engine currently has no API authentication. Keep it accessible
only to trusted local services until authentication and execution
isolation are implemented.

## 3. Submitting source code

Laravel sends an HTTP POST request to:

``` http
POST http://127.0.0.1:8000/api/v1/submissions/
Content-Type: application/json
```

### Request body

``` json
{
    "language": "python",
    "source_code": "a, b = map(int, input().split())\nprint(a + b)",
    "test_cases": [
        {
            "stdin": "2 3\n",
            "expected_output": "5\n"
        },
        {
            "stdin": "10 20\n",
            "expected_output": "30\n"
        }
    ],
    "time_limit": 2,
    "memory_limit": 128000
}
```

### Request fields

  Field               Description
  ------------------- ----------------------------------------------------
  `language`          Programming language. Currently `python` or `cpp`.
  `source_code`       Source code submitted by the participant.
  `test_cases`        Test cases Laravel sends for execution.
  `stdin`             Input provided to the submitted program.
  `expected_output`   Output expected from the submitted program.
  `time_limit`        Maximum execution time per test case, in seconds.
  `memory_limit`      Memory threshold, in KB.

**Important:** The time limit applies to each test case individually.
The memory limit is currently checked after execution using approximate
memory monitoring; it is not yet an operating-system-enforced limit.

### Successful response

The API returns HTTP `202 Accepted`:

``` json
{
    "job_id": "job_76d6f565df92",
    "job_status": "Queued"
}
```

This does not mean the submitted solution has been accepted. It means
the Judge Engine has accepted the job into its queue.

Laravel must save the returned `job_id` so it can retrieve the result
later.

## 4. Checking submission status

Laravel uses the `job_id` returned by the previous request:

``` http
GET http://127.0.0.1:8000/api/v1/submissions/job_76d6f565df92
```

### Job statuses

  Job status    Meaning
  ------------- ---------------------------------------------------
  `Queued`      Waiting for an available worker.
  `Running`     Code is being compiled or executed.
  `Completed`   Execution has finished and a result is available.
  `Failed`      The Judge Engine encountered an internal failure.

When the job is still queued or running, `result` will be `null`.

### Completed response

``` json
{
    "job_id": "job_76d6f565df92",
    "job_status": "Completed",
    "result": {
        "status": "Accepted",
        "passed_test_cases": 2,
        "total_test_cases": 2,
        "execution_time": 0.195,
        "test_results": [
            {
                "test_case": 1,
                "status": "Accepted",
                "stdout": "5\n",
                "stderr": "",
                "execution_time": 0.0882,
                "memory_usage": 12000
            },
            {
                "test_case": 2,
                "status": "Accepted",
                "stdout": "30\n",
                "stderr": "",
                "execution_time": 0.1068,
                "memory_usage": 12000
            }
        ]
    }
}
```

The memory values above are illustrative. Actual values depend on the
execution environment.

### Possible verdicts

  -----------------------------------------------------------------------
  Verdict                             Meaning
  ----------------------------------- -----------------------------------
  `Accepted`                          All test cases passed.

  `Wrong Answer`                      Program output did not match
                                      expected output.

  `Time Limit Exceeded`               Program exceeded the time limit.

  `Memory Limit Exceeded`             Measured memory exceeded the
                                      configured threshold.

  `Runtime Error`                     Program exited with an error.

  `Compilation Error`                 C++ compilation failed.

  `Internal Error`                    An execution or Judge Engine error
                                      occurred.
  -----------------------------------------------------------------------

The Judge Engine stops processing further test cases after the first
unsuccessful test case. Therefore, `passed_test_cases` represents the
number passed before the first failure.

## 5. Checking supported languages

Laravel can retrieve the languages currently configured on the Judge
Engine:

``` http
GET http://127.0.0.1:8000/api/v1/languages/
```

Example response:

``` json
{
    "languages": [
        {
            "id": 1,
            "name": "C++",
            "available": true,
            "needs_compilation": true
        },
        {
            "id": 2,
            "name": "Python",
            "available": true,
            "needs_compilation": false
        }
    ]
}
```

The `available` field indicates whether the required compiler or runtime
is detected on the server. Laravel can use this endpoint to display
available languages in its submission form.
