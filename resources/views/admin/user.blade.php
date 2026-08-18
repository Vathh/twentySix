@extends('layouts.app')

@section('title', ($user->player?->name ?? $user->email).' — panel')

@section('content')
<div class="max-w-6xl mx-auto px-4 pt-10 pb-16">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('admin.users') }}" class="text-text-secondary text-sm hover:text-accent">← Użytkownicy</a>
            <h1 class="text-2xl sm:text-3xl font-semibold text-accent mt-1">
                {{ $user->player?->name ?? 'Bez nicku' }}
            </h1>
            <p class="text-text-secondary text-sm mt-1 break-all">{{ $user->email }} · ID {{ $user->id }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @unless($user->isPlatformAdmin())
                <form method="POST" action="{{ route('admin.users.ban', $user->id) }}">
                    @csrf
                    @if($user->isBanned())
                        <input type="hidden" name="banned" value="0">
                        <button type="submit" class="btn btn-primary">Odblokuj</button>
                    @else
                        <input type="hidden" name="banned" value="1">
                        <button type="submit" class="btn btn-mini"
                                onclick="return confirm('Zablokować konto tego użytkownika?');">Zablokuj</button>
                    @endif
                </form>
            @endunless
            <form method="POST" action="{{ route('admin.users.can-create-organizations', $user->id) }}">
                @csrf
                <input type="hidden" name="can_create_organizations" value="{{ $user->can_create_organizations ? 0 : 1 }}">
                <button type="submit" class="btn btn-mini" @if($user->isBanned()) disabled @endif>
                    Tworzenie organizacji: {{ $user->can_create_organizations ? 'wyłącz' : 'włącz' }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Rola</p>
            <p class="text-lg font-semibold text-text">{{ $user->isPlatformAdmin() ? 'admin' : 'user' }}</p>
        </div>
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Status</p>
            <p class="text-lg font-semibold {{ $user->isBanned() ? 'text-danger' : 'text-success' }}">
                {{ $user->isBanned() ? 'zablokowany' : 'aktywny' }}
            </p>
            @if($user->banned_at)
                <p class="text-text-muted text-xs mt-1">od {{ $user->banned_at->format('Y-m-d H:i') }}</p>
            @endif
        </div>
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">E-mail</p>
            <p class="text-lg font-semibold {{ $user->email_verified_at ? 'text-success' : 'text-danger' }}">
                {{ $user->email_verified_at ? 'zweryfikowany' : 'niezweryfikowany' }}
            </p>
        </div>
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Rejestracja</p>
            <p class="text-lg font-semibold text-text">{{ $user->created_at?->format('Y-m-d') ?? '—' }}</p>
            <p class="text-text-muted text-xs mt-1">{{ $user->created_at?->format('H:i') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="card p-5">
            <h2 class="text-accent font-semibold mb-4">Aktywność API</h2>
            <ul class="space-y-2 text-sm text-text-secondary">
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Ostatnie użycie tokena</span>
                    <span class="text-text font-medium">{{ $activity['lastApiUsedAt'] ?? '—' }}</span>
                </li>
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Ostatni wydany token</span>
                    <span class="text-text font-medium">{{ $activity['lastTokenCreatedAt'] ?? '—' }}</span>
                </li>
                <li class="flex justify-between gap-3">
                    <span>Aktywne tokeny</span>
                    <span class="text-text font-medium">{{ $activity['activeTokensCount'] }}</span>
                </li>
            </ul>
            <p class="text-text-muted text-xs mt-3">Brak osobnego logu logowań — proxy przez Sanctum.</p>
        </div>

        <div class="card p-5">
            <h2 class="text-accent font-semibold mb-4">Szybka gra</h2>
            <ul class="space-y-2 text-sm text-text-secondary">
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Lobby jako host</span>
                    <span class="text-text font-medium">{{ $activity['lobbiesHostedCount'] }}</span>
                </li>
                <li class="flex justify-between gap-3 border-b border-border/60 pb-2">
                    <span>Udział w lobby</span>
                    <span class="text-text font-medium">{{ $activity['lobbiesAsPlayerCount'] }}</span>
                </li>
                <li class="flex justify-between gap-3">
                    <span>Wyniki quick game</span>
                    <span class="text-text font-medium">{{ $activity['quickGameResultsCount'] }}</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="card p-5">
            <h2 class="text-accent font-semibold mb-4">Organizacje</h2>
            <p class="text-text-secondary text-xs mb-2">Admin ({{ count($activity['organizationsAdmin']) }})</p>
            @forelse($activity['organizationsAdmin'] as $organization)
                <p class="text-sm text-text border-b border-border/40 py-1">{{ $organization['name'] }}</p>
            @empty
                <p class="text-text-muted text-sm mb-3">—</p>
            @endforelse
            <p class="text-text-secondary text-xs mt-4 mb-2">Członek ({{ count($activity['organizationsMember']) }})</p>
            @forelse($activity['organizationsMember'] as $organization)
                <p class="text-sm text-text border-b border-border/40 py-1">{{ $organization['name'] }}</p>
            @empty
                <p class="text-text-muted text-sm">—</p>
            @endforelse
        </div>

        <div class="card p-5">
            <h2 class="text-accent font-semibold mb-4">Znajomi ({{ $activity['friendsCount'] }})</h2>
            @forelse($activity['friends'] as $name)
                <p class="text-sm text-text border-b border-border/40 py-1">{{ $name }}</p>
            @empty
                <p class="text-text-muted text-sm">Brak znajomych.</p>
            @endforelse
            @if($activity['friendsCount'] > count($activity['friends']))
                <p class="text-text-muted text-xs mt-2">Pokazano {{ count($activity['friends']) }} z {{ $activity['friendsCount'] }}.</p>
            @endif
        </div>
    </div>

    <div class="card p-5 mb-8">
        <h2 class="text-accent font-semibold mb-4">
            Wyniki turniejowe
            <span class="text-text-muted font-normal text-sm">({{ $activity['tournamentResultsCount'] }})</span>
        </h2>
        @forelse($activity['tournamentResults'] as $row)
            <div class="flex flex-wrap justify-between gap-2 text-sm border-b border-border/40 py-2">
                <span class="text-text">{{ $row['tournament'] }}</span>
                <span class="text-text-secondary">
                    @if($row['place'] !== null) miejsce {{ $row['place'] }} @endif
                    @if($row['points'] !== null) · {{ $row['points'] }} pkt @endif
                    @if($row['date']) · {{ $row['date'] }} @endif
                </span>
            </div>
        @empty
            <p class="text-text-muted text-sm">Brak wyników turniejowych.</p>
        @endforelse
    </div>

    <div class="card p-5">
        <h2 class="text-accent font-semibold mb-4">Ostatnie mecze</h2>
        @forelse($recentGames['items'] as $game)
            <div class="flex flex-wrap justify-between gap-2 text-sm border-b border-border/40 py-2">
                <div>
                    <span class="text-text">{{ $game['opponents'] }}</span>
                    @if(!empty($game['tournament_name']))
                        <span class="text-text-muted"> · {{ $game['tournament_name'] }}</span>
                    @endif
                    <p class="text-text-muted text-xs">{{ $game['type'] }} · {{ $game['date_formatted'] ?? $game['date'] }}</p>
                </div>
                <span class="text-text font-medium">
                    {{ $game['result'] }}
                    @if(!empty($game['score'])) ({{ $game['score'] }}) @endif
                </span>
            </div>
        @empty
            <p class="text-text-muted text-sm">Brak historii meczów.</p>
        @endforelse
    </div>
</div>
@endsection
