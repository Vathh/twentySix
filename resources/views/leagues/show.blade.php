@extends('layouts.app')

@section('title', $league->name)

@section('content')
    @php
        $isAdmin = auth()->check() && $organization->admins->contains('id', auth()->id());
        $newSeasonBlockedReason = $activeSeason
            ? 'Najpierw dokończ aktualny sezon ligowy.'
            : 'Ta liga ma już szkic sezonu.';
    @endphp

    <div class="detail-layout">
        @if($isAdmin)
            <aside class="admin-sidebar">
                <h2 class="admin-sidebar-title">⚙️ Zarządzanie ligą</h2>
                <nav class="flex flex-col space-y-3">
                    <a href="{{ route('leagues.roster', $league) }}" class="admin-sidebar-link">👥 Skład szczebli</a>
                    @if($hasOpenSeason)
                        <span
                            class="admin-sidebar-link admin-sidebar-link-disabled"
                            aria-disabled="true"
                            title="{{ $newSeasonBlockedReason }}"
                        >➕ Nowy sezon ligowy</span>
                    @else
                        <a href="{{ route('league-seasons.create', $league) }}" class="admin-sidebar-link">➕ Nowy sezon ligowy</a>
                    @endif
                    <a href="{{ route('leagues.edit', $league) }}" class="admin-sidebar-link">✏️ Edytuj ligę / szczeble</a>
                </nav>
            </aside>
        @endif

        <div class="detail-main">
            <div class="detail-content">
                <a href="{{ route('organizations.show', $organization) }}" class="link-back mb-4 inline-block">← {{ $organization->name }}</a>

                <header class="entity-header">
                    <p class="entity-eyebrow">Liga</p>
                    <h1 class="entity-title {{ $activeSeason ? '!mb-2' : '' }}">{{ $league->name }}</h1>
                    @if($activeSeason)
                        <p class="mb-4">
                            <a href="{{ route('league-seasons.show', $activeSeason) }}" class="badge-status-live inline-flex items-center">
                                Sezon w trakcie rozgrywek
                                @if($activeSeason->status === \App\Enums\LeagueSeasonStatus::PLAYOFFS)
                                    · baraże
                                @endif
                            </a>
                        </p>
                    @endif
                    <span class="entity-rule" aria-hidden="true"></span>
                </header>

                <div class="entity-meta">
                    <dl class="entity-meta-grid cols-2">
                        <div class="entity-meta-item span-full">
                            <dt class="entity-meta-label">Opis</dt>
                            <dd class="entity-meta-value">{{ $league->description ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <h2 class="section-title mt-10">Piramida</h2>
                <div class="space-y-3">
                    @foreach($divisions as $division)
                        <div class="list-item">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <span class="font-semibold">{{ $division->position + 1 }}. {{ $division->name }}</span>
                                <span class="text-text-muted text-sm">
                                    {{ $division->members->count() }}/{{ $division->capacity }}
                                    · {{ $division->starting_score }} · do {{ $division->legs_to_win_set }} {{ $division->sets_to_win_match > 1 ? 'legów / '.$division->sets_to_win_match.' setów' : 'legów' }}
                                </span>
                            </div>
                            @if($division->position > 0)
                                <p class="text-text-muted text-sm mt-1">
                                    Awans: {{ $division->promote_direct }} bezpośredni
                                    @if($division->promote_playoff > 0)
                                        + {{ $division->promote_playoff }} baraż
                                    @endif
                                </p>
                            @endif
                            @if($division->members->isNotEmpty())
                                <p class="text-sm mt-2">
                                    {{ $division->members->map(fn ($m) => $m->player?->name)->filter()->join(', ') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <h2 class="section-title mt-10">Sezony ligowe</h2>
                <div class="space-y-3">
                    @forelse($seasons as $season)
                        <a href="{{ route('league-seasons.show', $season) }}">
                            <div class="list-item mb-2">
                                {{ $season->name }}
                                <span class="text-text-muted text-sm font-normal">· {{ $season->status->label() }}</span>
                            </div>
                        </a>
                    @empty
                        <x-empty-state
                            class="!py-10"
                            title="Brak sezonów ligowych"
                            description="Ustaw skład, potem wystartuj sezon — zdjęcie piramidy i mecze każdy z każdym."
                        />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
