@extends('layouts.app')

@section('title', $season ? $season->name : 'Szczegóły')

@section('content')

    <div class="detail-layout">

        @seasonAdmin($season)
        <aside class="admin-sidebar">
            <h2 class="admin-sidebar-title">⚙️ Zarządzanie sezonem</h2>

            <nav class="flex flex-col space-y-3">
                <a href="{{ route('tournaments.create') }}?seasonId={{ $season->id }}" class="admin-sidebar-link">
                    ➕ Dodaj turniej
                </a>
                <a href="{{ route('seasons.admins', $season->id) }}" class="admin-sidebar-link">
                    💼 Administratorzy
                </a>
                <a href="{{ route('seasons.edit', ['season' => $season->id]) }}" class="admin-sidebar-link">
                    ✏️ Edytuj sezon
                </a>
                <a href="{{ route('seasons.relatedUsers', $season->id) }}" class="admin-sidebar-link">
                    全家 Powiązani użytkownicy
                </a>
                <a href="{{ route('seasons.guests', $season->id) }}" class="admin-sidebar-link">
                    全家 Goście
                </a>
            </nav>
        </aside>
        @endseasonAdmin

        <div class="detail-main">
            <div class="detail-content">

                <header class="entity-header">
                    <nav class="entity-breadcrumb" aria-label="Okruszki">
                        <a href="{{ route('leagues.show', $season->league->id) }}">{{ $season->league->name }}</a>
                        <span class="entity-breadcrumb-sep">/</span>
                        <span class="text-text-secondary">Sezon</span>
                    </nav>
                    <h1 class="entity-title">{{ $season->name }}</h1>
                    <span class="entity-rule" aria-hidden="true"></span>
                </header>

                <div class="entity-meta">
                    <dl class="entity-meta-grid cols-2">
                        <div class="entity-meta-item">
                            <dt class="entity-meta-label">Data rozpoczęcia</dt>
                            <dd class="entity-meta-value score-num">{{ $season->getStartDate() }}</dd>
                        </div>
                        <div class="entity-meta-item">
                            <dt class="entity-meta-label">Data zakończenia</dt>
                            <dd class="entity-meta-value score-num">{{ $season->getEndDate() }}</dd>
                        </div>
                        <div class="entity-meta-item span-full">
                            <dt class="entity-meta-label">Ostatnia aktywność</dt>
                            <dd class="entity-meta-value score-num">{{ $season->getUpdatedAtDate() }}</dd>
                        </div>
                    </dl>
                </div>

                <h2 class="section-title mt-12">Turnieje</h2>
                <div class="space-y-3">
                    @forelse($season->tournaments as $tournament)
                        <a href="{{ route('tournaments.show', ['tournament' => $tournament->id]) }}">
                            <div class="list-item">{{ $tournament->name }}</div>
                        </a>
                    @empty
                        <x-empty-state
                            class="!py-10"
                            title="Brak turniejów"
                            description="Dodaj turniej z panelu zarządzania sezonem."
                        />
                    @endforelse
                </div>

            </div>
        </div>

    </div>

@endsection
