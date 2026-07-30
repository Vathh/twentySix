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
        @endphp
        <nav id="site-nav"
             class="w-full md:w-auto md:flex md:flex-wrap md:items-center md:gap-1"
             :class="navOpen ? 'flex flex-col gap-1 border-t border-border pt-3 mt-1' : 'hidden'">
            <a href="/" class="nav-btn {{ request()->routeIs('pages.home') ? 'active' : '' }}" @click="navOpen = false">Strona główna</a>

            <a href='{{ route('leagues.index') }}' class="nav-btn {{ request()->routeIs('leagues.*') ? 'active' : '' }}" @click="navOpen = false">Ligi</a>
            <a href='{{ route('seasons.index') }}' class="nav-btn {{ request()->routeIs('seasons.*') ? 'active' : '' }}" @click="navOpen = false">Sezony</a>
            <a href='{{ route('tournaments.index') }}' class="nav-btn {{ request()->routeIs('tournaments.*') ? 'active' : '' }}" @click="navOpen = false">Turnieje</a>
            <a href='{{ route('players.search') }}' class="nav-btn {{ request()->routeIs('players.*') && ! $isMyProfile ? 'active' : '' }}" @click="navOpen = false">Szukaj graczy</a>

            @auth
                <a href='{{ route('settings.index') }}' class="nav-btn {{ request()->routeIs('settings.*') ? 'active' : '' }}" @click="navOpen = false">Ustawienia</a>
            @endauth

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
