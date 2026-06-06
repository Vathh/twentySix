<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'twentySix')</title>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
</head>
<body class="bg-dark-bg flex flex-col min-h-screen text-light-text" x-data="{ friendsOpen: false }">

    @include('components.notifications')

    @include('layouts.header')

    <div class="flex flex-grow relative">
        <main class="container mx-auto flex-grow py-4">
            @yield('content')
        </main>

        @auth
            {{-- Przycisk otwierający panel znajomych --}}
            <button type="button"
                    @click="friendsOpen = true"
                    class="fixed right-4 top-24 z-30 px-3 py-2 rounded border border-light-green text-light-green hover:bg-light-green/10 transition text-sm font-semibold">
                Znajomi ({{ $friends->count() }})
            </button>

            {{-- Panel boczny znajomych (wysuwany z prawej) --}}
            <div x-show="friendsOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-x-full"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-full"
                 class="fixed right-0 top-0 bottom-0 w-72 max-w-[85vw] bg-darker-bg border-l border-border z-40 flex flex-col shadow-xl"
                 x-cloak
                 style="display: none;">
                <div class="p-4 border-b border-border flex justify-between items-center">
                    <h2 class="text-lg font-bold text-light-green">Znajomi</h2>
                    <button type="button" @click="friendsOpen = false" class="text-light-white hover:text-light-orange transition p-1">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    @if($friends->isEmpty())
                        <p class="text-light-gray text-sm">Brak znajomych.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach($friends as $friend)
                                <li>
                                    <a href="{{ route('players.show', $friend->friendPlayer->id) }}" class="block py-2 px-3 rounded border border-border hover:border-light-green text-light-white hover:text-light-green transition text-sm">
                                        {{ $friend->friendPlayer->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Tło po kliknięciu poza panelem --}}
            <div x-show="friendsOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="friendsOpen = false"
                 class="fixed inset-0 bg-black/50 z-30"
                 x-cloak
                 style="display: none;"></div>
        @endauth
    </div>

    @include('layouts.footer')

    @yield('scripts')
</body>
</html>
