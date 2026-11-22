<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') | Code Arena</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('head')
</head>
<body class="min-vh-100 d-flex flex-column bg-light">
    @include('partials.admin.header')

    <main class="container py-4 flex-grow-1">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @yield('content')
    </main>

    @include('partials.admin.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('time.local-datetime').forEach((element) => {
            const date = new Date(element.dateTime);
            if (!Number.isNaN(date.getTime())) {
                element.textContent = new Intl.DateTimeFormat(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    timeZoneName: 'short',
                }).format(date);
            }
        });

        const pad = (value) => String(value).padStart(2, '0');
        const toLocalInputValue = (date) => (
            date.getFullYear()+'-'+pad(date.getMonth() + 1)+'-'+pad(date.getDate())
            +'T'+pad(date.getHours())+':'+pad(date.getMinutes())
        );
        document.querySelectorAll('[data-utc-target]').forEach((localInput) => {
            const utcInput = document.getElementById(localInput.dataset.utcTarget);
            if (utcInput.value) {
                const date = new Date(utcInput.value);
                if (!Number.isNaN(date.getTime())) {
                    localInput.value = toLocalInputValue(date);
                }
            }

            localInput.form.addEventListener('submit', () => {
                utcInput.value = localInput.value
                    ? new Date(localInput.value).toISOString()
                    : '';
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
