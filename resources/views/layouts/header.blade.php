<header class="site-header" x-data="{ navOpen: false }">
    <div class="container mx-auto flex flex-wrap items-center justify-between gap-3 px-4">
        <a href="{{ route('pages.home') }}" class="flex items-center gap-2 sm:gap-3 no-underline hover:opacity-95 transition min-w-0">
            <img
                class="brand-logo"
                src="{{ asset('images/logo.svg') }}"
                alt="twentySix"
                width="209"
                height="44"
            >
            @auth
                <span class="text-text-muted font-normal text-lg sm:text-2xl shrink-0" aria-hidden="true">/</span>
                <span class="text-accent text-base sm:text-lg font-semibold truncate max-w-[9rem] sm:max-w-[14rem]">
                    {{ Auth::user()->player?->name ?? 'Użytkownik' }}
                </span>
            @endauth
        </a>

        <button type="button"
                class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg border border-border text-text-secondary hover:text-accent hover:border-accent/40 transition"
                @click="navOpen = !navOpen"
                :aria-expanded="navOpen.toString()"
                aria-controls="site-nav"
                aria-label="Menu">
            <span class="text-xl leading-none" x-text="navOpen ? '✕' : '☰'"></span>
        </button>

        @php
            $currentPlayer = request()->routeIs('players.show') ? request()->route('player') : null;
            $isMyProfile = Auth::check()
                && Auth::user()->player
                && $currentPlayer
                && (int) $currentPlayer->id === (int) Auth::user()->player->id;
            $rozgrywkiActive = request()->routeIs('leagues.*', 'seasons.*', 'tournaments.*');
        @endphp
        <nav id="site-nav"
             class="w-full md:w-auto md:flex md:flex-wrap md:items-center md:gap-1"
             :class="navOpen ? 'flex flex-col gap-1 border-t border-border pt-3 mt-1' : 'hidden'">
            <a href="/" class="nav-btn {{ request()->routeIs('pages.home') ? 'active' : '' }}" @click="navOpen = false">Strona główna</a>

            <div class="relative w-full md:w-auto"
                 x-data="{ open: false }"
                 @mouseenter="if (window.matchMedia('(min-width: 768px)').matches) open = true"
                 @mouseleave="if (window.matchMedia('(min-width: 768px)').matches) open = false">
                <button type="button"
                        class="nav-btn inline-flex items-center gap-1 w-full md:w-auto {{ $rozgrywkiActive ? 'active' : '' }}"
                        @click="if (!window.matchMedia('(min-width: 768px)').matches) open = !open"
                        :aria-expanded="open.toString()"
                        aria-haspopup="true"
                        aria-controls="nav-rozgrywki-menu">
                    <span>Rozgrywki</span>
                    <span class="text-[0.65rem] opacity-70 leading-none" aria-hidden="true" x-text="open ? '▴' : '▾'"></span>
                </button>
                <div id="nav-rozgrywki-menu"
                     x-show="open"
                     x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="nav-dropdown"
                     role="menu">
                    <div class="nav-dropdown-panel">
                        <a href="{{ route('leagues.index') }}"
                           class="nav-dropdown-item {{ request()->routeIs('leagues.*') ? 'active' : '' }}"
                           role="menuitem"
                           @click="navOpen = false; open = false">Ligi</a>
                        <a href="{{ route('seasons.index') }}"
                           class="nav-dropdown-item {{ request()->routeIs('seasons.*') ? 'active' : '' }}"
                           role="menuitem"
                           @click="navOpen = false; open = false">Sezony</a>
                        <a href="{{ route('tournaments.index') }}"
                           class="nav-dropdown-item {{ request()->routeIs('tournaments.*') ? 'active' : '' }}"
                           role="menuitem"
                           @click="navOpen = false; open = false">Turnieje</a>
                    </div>
                </div>
            </div>

            <a href='{{ route('players.search') }}' class="nav-btn {{ request()->routeIs('players.*') && ! $isMyProfile ? 'active' : '' }}" @click="navOpen = false">Szukaj graczy</a>

            @auth
                <a href='{{ route('settings.index') }}' class="nav-btn {{ request()->routeIs('settings.*') ? 'active' : '' }}" @click="navOpen = false">Ustawienia</a>
            @endauth

            @platformAdmin
                <a href='{{ route('admin.dashboard') }}' class="nav-btn {{ request()->routeIs('admin.*') ? 'active' : '' }}" @click="navOpen = false">Panel</a>
            @endplatformAdmin

            @guest
                <a href='{{ route('pages.registerPanel') }}' class="nav-btn" @click="navOpen = false">Zarejestruj się</a>
                <a href='{{ route('pages.loginPanel') }}' class="nav-btn" @click="navOpen = false">Zaloguj się</a>
            @endguest

            @auth
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button class="nav-btn w-full md:w-auto text-left" type="submit">Wyloguj się</button>
                </form>
            @endauth
        </nav>
    </div>
</header>
