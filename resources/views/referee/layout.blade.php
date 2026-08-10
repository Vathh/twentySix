<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sędziowanie') — twentySix</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell min-h-screen text-text">
    @include('components.notifications')

    <header class="border-b border-border bg-bg-deep/90 backdrop-blur-md sticky top-0 z-40">
        <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
            <a href="{{ route('referee.games') }}" class="flex items-center gap-2 no-underline hover:opacity-95 min-w-0">
                <img
                    class="h-8 w-auto"
                    src="{{ asset('images/logo.svg') }}"
                    alt="twentySix"
                    width="160"
                    height="34"
                >
                <span class="text-text-muted text-sm font-semibold truncate">Sędziowanie</span>
            </a>
            @hasSection('headerActions')
                <div class="flex items-center gap-2 shrink-0">
                    @yield('headerActions')
                </div>
            @endif
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-5 pb-10">
        @yield('content')
    </main>
</body>
</html>
