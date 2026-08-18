@extends('layouts.app')

@section('title', 'Panel platformy')

@section('content')
<div class="max-w-6xl mx-auto px-4 pt-10 pb-16">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <p class="text-text-secondary text-sm mb-1">twentySix — właściciel aplikacji</p>
            <h1 class="text-2xl sm:text-3xl font-semibold text-accent">Panel platformy</h1>
        </div>
        <a href="{{ route('admin.users') }}" class="btn btn-primary">Użytkownicy</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Użytkownicy</p>
            <p class="text-2xl font-semibold text-text">{{ $stats['usersTotal'] }}</p>
            <p class="text-text-muted text-xs mt-1">dziś +{{ $stats['usersRegisteredToday'] }} · 7 dni +{{ $stats['usersRegisteredLast7Days'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Zweryfikowani e-mail</p>
            <p class="text-2xl font-semibold text-text">{{ $stats['usersVerified'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Mogą tworzyć organizacje</p>
            <p class="text-2xl font-semibold text-text">{{ $stats['usersCanCreateOrganizations'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Organizacje / sezony</p>
            <p class="text-2xl font-semibold text-text">{{ $stats['organizationsTotal'] }} <span class="text-text-muted text-lg">/</span> {{ $stats['seasonsTotal'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="card p-5">
            <h2 class="text-accent font-semibold mb-4">Turnieje</h2>
            <p class="text-3xl font-semibold text-text mb-4">{{ $stats['tournamentsTotal'] }}</p>
            <ul class="space-y-2 text-sm text-text-secondary">
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Zaplanowane</span>
                    <span class="text-text font-medium">{{ $stats['tournamentsByStatus']['created'] ?? 0 }}</span>
                </li>
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Faza grupowa</span>
                    <span class="text-text font-medium">{{ $stats['tournamentsByStatus']['group'] ?? 0 }}</span>
                </li>
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Playoff</span>
                    <span class="text-text font-medium">{{ $stats['tournamentsByStatus']['playoff'] ?? 0 }}</span>
                </li>
                <li class="flex justify-between gap-3">
                    <span>Zakończone</span>
                    <span class="text-text font-medium">{{ $stats['tournamentsByStatus']['finished'] ?? 0 }}</span>
                </li>
            </ul>
        </div>

        <div class="card p-5">
            <h2 class="text-accent font-semibold mb-4">Szybka gra</h2>
            <ul class="space-y-2 text-sm text-text-secondary">
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Lobby łącznie</span>
                    <span class="text-text font-medium">{{ $stats['quickGameLobbiesTotal'] }}</span>
                </li>
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Lobby waiting</span>
                    <span class="text-text font-medium">{{ $stats['quickGameLobbiesWaiting'] }}</span>
                </li>
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Lobby in progress</span>
                    <span class="text-text font-medium">{{ $stats['quickGameLobbiesInProgress'] }}</span>
                </li>
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Quick games zakończone (H2H)</span>
                    <span class="text-text font-medium">{{ $stats['quickGamesFinished'] }}</span>
                </li>
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>FFA w trakcie</span>
                    <span class="text-text font-medium">{{ $stats['ffaSessionsInProgress'] }}</span>
                </li>
                <li class="flex justify-between gap-3">
                    <span>FFA zakończone</span>
                    <span class="text-text font-medium">{{ $stats['ffaSessionsFinished'] }}</span>
                </li>
            </ul>
        </div>
    </div>

    <p class="text-text-muted text-xs">
        Dostęp tylko dla kont z <code class="text-accent">users.role = admin</code>.
        To nie jest panel organizatora organizacji — to narzędzie właściciela aplikacji.
    </p>
</div>
@endsection
