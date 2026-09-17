<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'twentySix')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
</head>
<body class="app-shell flex flex-col min-h-screen text-text" @auth x-data="friendsPanel({ url: '{{ route('friends.panel') }}' })" @endauth>

    @include('components.notifications')

    @include('layouts.header')

    <div class="flex flex-grow relative">
        <main class="container mx-auto flex-grow py-4 px-4 min-w-0">
            @yield('content')
        </main>

        @auth
            <button type="button"
                    @click="show()"
                    class="friends-fab">
                Znajomi ({{ $friendsCount }})
            </button>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-x-full"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-full"
                 class="friends-panel"
                 x-cloak
                 style="display: none;">
                <div class="friends-panel-header">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="brand-logotyp !h-9 !w-[3.6rem]" aria-hidden="true">
                            <img src="{{ asset('images/logotyp.svg') }}" alt="" width="58" height="36">
                        </span>
                        <h2 class="text-lg font-bold text-text truncate">Znajomi</h2>
                    </div>
                    <button type="button" @click="hide()" class="text-text-muted hover:text-accent transition p-1 text-lg leading-none" aria-label="Zamknij">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-6">
                    <p x-show="loading" x-cloak class="text-sm text-text-muted">Ładowanie…</p>
                    <p x-show="error" x-cloak class="text-sm text-text-muted">Nie udało się załadować listy znajomych.</p>
                    <div x-show="!loading && !error" x-html="html"></div>
                </div>
            </div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="hide()"
                 class="overlay"
                 x-cloak
                 style="display: none;"></div>
        @endauth
    </div>

    @include('layouts.footer')

    @yield('scripts')
</body>
</html>
