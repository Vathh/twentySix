<header class="site-header">
    <div class="container mx-auto flex justify-between items-center gap-6 px-4">
        <a href="{{ route('pages.home') }}" class="flex items-center gap-3 no-underline hover:opacity-95 transition">
            <span class="brand-mark" aria-hidden="true">26</span>
            <h1 class="text-2xl font-bold text-text mb-0">
                twentySix
                @auth
                    <span class="text-text-muted pl-4 font-normal">/</span>
                    <span class="text-accent pl-4 text-lg font-semibold">{{ Auth::user()->player?->name ?? 'Użytkownik' }}</span>
                @endauth
            </h1>
        </a>
        <nav class="flex flex-wrap items-center gap-1">
            <a href="/" class="nav-btn {{ request()->routeIs('pages.home') ? 'active' : '' }}">Strona główna</a>

            <a href='{{ route('leagues.index') }}' class="nav-btn {{ request()->routeIs('leagues.*') ? 'active' : '' }}">Ligi</a>
            <a href='{{ route('seasons.index') }}' class="nav-btn {{ request()->routeIs('seasons.*') ? 'active' : '' }}">Sezony</a>
            <a href='{{ route('tournaments.index') }}' class="nav-btn {{ request()->routeIs('tournaments.*') ? 'active' : '' }}">Turnieje</a>
            <a href='{{ route('players.search') }}' class="nav-btn {{ request()->routeIs('players.*') ? 'active' : '' }}">Szukaj graczy</a>

            @auth
                @if(Auth::user()->player)
                    @php
                        $currentPlayer = request()->routeIs('players.show') ? request()->route('player') : null;
                        $isMyProfile = $currentPlayer && $currentPlayer->id === Auth::user()->player->id;
                    @endphp
                    <a href='{{ route('players.show', Auth::user()->player) }}' class="nav-btn {{ $isMyProfile ? 'active' : '' }}">Mój profil</a>
                @endif
            @endauth

            @guest
                <a href='{{ route('pages.registerPanel') }}' class="nav-btn">Zarejestruj się</a>
                <a href='{{ route('pages.loginPanel') }}' class="nav-btn">Zaloguj się</a>
            @endguest

            @auth
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button class="nav-btn">Wyloguj się</button>
                </form>
            @endauth
        </nav>
    </div>
</header>
