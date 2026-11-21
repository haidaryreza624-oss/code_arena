<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $contest->name }} credentials</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none !important; } .credential { break-inside: avoid; } }
    </style>
</head>
<body class="bg-light">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div><h1 class="h3 mb-1">{{ $contest->name }} credentials</h1><p class="text-muted mb-0">Passwords are only displayed during this generation.</p></div>
        <button class="btn btn-primary" onclick="window.print()">Print</button>
    </div>
    @if (count($credentials) === 0)
        <div class="alert alert-warning">No team participations are assigned to this contest.</div>
    @endif
    <div class="row g-3">
        @foreach ($credentials as $credential)
            <div class="col-md-6 credential">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5">{{ $credential['team'] }}</h2>
                        <dl class="row mb-0">
                            <dt class="col-4">Username</dt><dd class="col-8"><code>{{ $credential['username'] }}</code></dd>
                            <dt class="col-4">Password</dt><dd class="col-8"><code>{{ $credential['password'] }}</code></dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</main>
<script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
