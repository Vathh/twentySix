<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'twentySix')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    @auth
        <script>
            try {
                var storedRail = localStorage.getItem('friends-rail');
                var narrowRail = window.matchMedia('(max-width: 767px)').matches;
                if (storedRail === '1' || (storedRail === null && narrowRail)) {
                    document.documentElement.classList.add('friends-rail-collapsed');
                }
            } catch (e) {}
        </script>
    @endauth
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
</head>
<body class="app-shell flex flex-col min-h-screen text-text" @auth x-data="friendsPanel({ url: '{{ route('friends.panel') }}' })" @endauth>

    @include('components.notifications')

    @include('layouts.header')

    <div class="app-frame">
        <main class="container mx-auto flex-grow py-4 px-4 min-w-0">
            @yield('content')
        </main>

        @auth
            <aside id="friends-panel" class="friends-rail" :class="{ 'is-collapsed': collapsed }">
                <button
                    type="button"
                    class="friends-rail-toggle"
                    @click="toggle()"
                    :aria-expanded="(!collapsed).toString()"
                    :aria-label="collapsed ? 'Pokaż znajomych' : 'Schowaj znajomych'"
                >
                    <svg class="friends-rail-chevron" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M12.5 4.5 7 10l5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="friends-rail-body">
                    <div class="friends-panel-header">
                        <h2 class="text-lg font-bold text-text truncate">Znajomi</h2>
                        <span class="text-sm text-text-muted tabular-nums">{{ $friendsCount }}</span>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4 space-y-6">
                        <p x-show="loading" x-cloak class="text-sm text-text-muted">Ładowanie…</p>
                        <p x-show="error" x-cloak class="text-sm text-text-muted">Nie udało się załadować listy znajomych.</p>
                        <div x-show="!loading && !error" x-html="html"></div>
                    </div>
                </div>
            </aside>
        @endauth
    </div>

    @include('layouts.footer')

    <script>
        window.feedEngagement = {
            signedIn: @json(auth()->check()),
            loginUrl: @json(route('pages.loginPanel')),
        };
    </script>

    @yield('scripts')
</body>
</html>
