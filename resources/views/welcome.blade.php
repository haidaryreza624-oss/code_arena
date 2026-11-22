<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Code Arena</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="min-vh-100 bg-light d-flex align-items-center">
    <main class="container">
        <div class="row justify-content-center">
            <div class="col-md-9 col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center p-5">
                        <h1 class="display-5 fw-bold">Code Arena</h1>
                        <p class="lead text-muted mb-4">Contest programming platform</p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <a class="btn btn-primary" href="{{ route('team.login') }}">Team login</a>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.login') }}">Admin login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
