<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Contest') | Code Arena</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('head')
</head>
<body class="min-vh-100 bg-light">
    <main class="container py-4">
        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @yield('content')
    </main>
    <script>
        document.querySelectorAll('time.local-datetime').forEach((element) => {
            const date = new Date(element.dateTime);
            if (!Number.isNaN(date.getTime())) {
                element.textContent = new Intl.DateTimeFormat(undefined, {
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    timeZoneName: 'short',
                }).format(date);
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
