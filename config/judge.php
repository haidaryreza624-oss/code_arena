<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Judge Engine Base URL
    |--------------------------------------------------------------------------
    | The FastAPI judge engine. NOTE: the judge docs cite both port 9000 and
    | 8000 — verify with the judge operator. The judge has NO authentication,
    | so it must be reachable only from trusted networks (bind to 127.0.0.1,
    | or an internal Docker/VM network — never expose publicly).
    */
    'base_url' => env('JUDGE_BASE_URL', 'http://127.0.0.1:9000'),

    /*
    |--------------------------------------------------------------------------
    | Endpoint Paths
    |--------------------------------------------------------------------------
    */
    'submit_path'    => '/api/v1/submissions/',
    'status_path'    => '/api/v1/submissions/{job_id}',
    'languages_path' => '/api/v1/languages/',

    /*
    |--------------------------------------------------------------------------
    | Timeouts (seconds)
    |--------------------------------------------------------------------------
    | Submit returns 202 immediately — fast. Status can be slower if the
    | judge queue is backed up. Both timeouts are bounded; JudgeClient raises
    | JudgeException on connection or API errors.
    */
    'submit_timeout' => (int) env('JUDGE_SUBMIT_TIMEOUT', 10),
    'status_timeout' => (int) env('JUDGE_STATUS_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Languages Cache TTL (seconds)
    |--------------------------------------------------------------------------
    | JudgeClient caches GET /languages/ for this long.
    */
    'languages_ttl' => (int) env('JUDGE_LANGUAGES_TTL', 300),
];
