<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'twentySix')</title>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
</head>
<body class="app-shell flex flex-col min-h-screen text-text" x-data="{ friendsOpen: false }">

    @include('components.notifications')

    @include('layouts.header')

    <div class="flex flex-grow relative">
        <main class="container mx-auto flex-grow py-4">
            @yield('content')
        </main>

        @auth
            <button type="button"
                    @click="friendsOpen = true"
                    class="friends-fab">
                Znajomi ({{ $friends->count() }})
            </button>

            <div x-show="friendsOpen"
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
                        <span class="brand-mark !w-8 !h-8 text-xs" aria-hidden="true">26</span>
                        <h2 class="text-lg font-bold text-text truncate">Znajomi</h2>
                    </div>
                    <button type="button" @click="friendsOpen = false" class="text-text-muted hover:text-accent transition p-1 text-lg leading-none" aria-label="Zamknij">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-6">
                    @if($receivedFriendInvitations->isNotEmpty())
                        <section>
                            <h3 class="text-sm font-semibold text-accent mb-2">Zaproszenia</h3>
                            <ul class="space-y-2">
                                @foreach($receivedFriendInvitations as $invitation)
                                    <li class="p-3 rounded-lg border border-border bg-bg-elevated/50">
                                        <p class="text-text-secondary text-sm mb-2">
                                            {{ $invitation->senderPlayer?->name ?? 'Gracz' }}
                                        </p>
                                        <div class="flex gap-2">
                                            <form action="{{ route('friends.invitations.accept', $invitation->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-mini text-xs py-1 px-2">Akceptuj</button>
                                            </form>
                                            <form action="{{ route('friends.invitations.reject', $invitation->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="text-xs py-1 px-2 rounded-md border border-accent text-accent hover:bg-accent/10 transition">Odrzuć</button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <section>
                        <h3 class="text-sm font-semibold text-accent mb-2">Twoi znajomi</h3>
                    @if($friends->isEmpty())
                        <x-empty-state
                            class="!py-8"
                            title="Brak znajomych"
                            description="Dodaj graczy z profilu lub wyszukiwarki."
                        />
                    @else
                        <ul class="space-y-2">
                            @foreach($friends as $friend)
                                <li>
                                    <a href="{{ route('players.show', $friend->friendPlayer->id) }}" class="friends-link">
                                        {{ $friend->friendPlayer->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    </section>

                    @if($sentFriendInvitations->isNotEmpty())
                        <section>
                            <h3 class="text-sm font-semibold text-accent mb-2">Oczekujący</h3>
                            <ul class="space-y-2">
                                @foreach($sentFriendInvitations as $invitation)
                                    <li>
                                        @if($invitation->receiverPlayer)
                                            <a href="{{ route('players.show', $invitation->receiverPlayer->id) }}" class="friends-link">
                                                {{ $invitation->receiverPlayer->name }}
                                            </a>
                                        @else
                                            <span class="friends-link text-text-muted pointer-events-none">Gracz</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            </div>

            <div x-show="friendsOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="friendsOpen = false"
                 class="overlay"
                 x-cloak
                 style="display: none;"></div>
        @endauth
    </div>

    @include('layouts.footer')

    @yield('scripts')
</body>
</html>
