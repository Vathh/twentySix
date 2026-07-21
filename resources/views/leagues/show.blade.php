@extends('layouts.app')

@section('title', $league ? $league->name : 'Szczegóły')

@section('content')

    <div class="detail-layout">

        @leagueAdmin($league)
            <aside class="admin-sidebar">
                <h2 class="admin-sidebar-title">⚙️ Zarządzanie ligą</h2>

                <nav class="flex flex-col space-y-3">
                    <a href="{{ route('seasons.create') }}?leagueId={{ $league->id }}" class="admin-sidebar-link">
                        ➕ Dodaj sezon
                    </a>
                    <a href="{{ route('leagues.admins', $league->id) }}" class="admin-sidebar-link">
                        💼 Administratorzy
                    </a>
                    <a href="{{ route('leagues.edit', ['league' => $league->id]) }}" class="admin-sidebar-link">
                        ✏️ Edytuj ligę
                    </a>
                    <a href="{{ route('leagues.relatedUsers', $league->id) }}" class="admin-sidebar-link">
                        全家 Powiązani użytkownicy
                    </a>
                    <a href="{{ route('leagues.guests', $league->id) }}" class="admin-sidebar-link">
                        全家 Goście
                    </a>
                </nav>
            </aside>
        @endleagueAdmin

        <div class="detail-main">
            <div class="detail-content">

                <header class="entity-header">
                    <p class="entity-eyebrow">Liga</p>
                    <h1 class="entity-title">{{ $league->name }}</h1>
                    <span class="entity-rule" aria-hidden="true"></span>
                </header>

                <div class="entity-meta">
                    <dl class="entity-meta-grid cols-2">
                        <div class="entity-meta-item span-full">
                            <dt class="entity-meta-label">Opis</dt>
                            <dd class="entity-meta-value">{{ $league->description ?: '—' }}</dd>
                        </div>
                        <div class="entity-meta-item">
                            <dt class="entity-meta-label">Data utworzenia</dt>
                            <dd class="entity-meta-value score-num">{{ $league->createdAtDate() }}</dd>
                        </div>
                        <div class="entity-meta-item">
                            <dt class="entity-meta-label">Ilość sezonów</dt>
                            <dd class="entity-meta-value score-num">{{ count($league->seasons) }}</dd>
                        </div>
                        <div class="entity-meta-item span-full">
                            <dt class="entity-meta-label">Ostatnia aktywność</dt>
                            <dd class="entity-meta-value score-num">{{ $league->updatedAtDate() }}</dd>
                        </div>
                    </dl>
                </div>

                <h2 class="section-title mt-12">Tabela wyników ligi (top 40)</h2>
                <div class="table-wrap mb-10">
                    <table class="table-surface">
                        <thead>
                        <tr>
                            <th class="text-center">Miejsce</th>
                            <th class="text-left">Zawodnik</th>
                            <th class="text-center">Punkty</th>
                            <th class="text-center">180</th>
                            <th class="text-center">170+</th>
                            <th class="text-center">QF</th>
                            <th class="text-center">HF</th>
                            <th class="text-center">Najniższa lotka</th>
                            <th class="text-center">Najwyższy finish</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($standings as $row)
                            <tr>
                                <td class="text-center font-semibold text-accent">{{ $row->place }}</td>
                                <td class="font-medium text-text whitespace-nowrap">
                                    <a href="{{ route('players.show', $row->player_id) }}" class="hover:text-accent transition">{{ $row->player_name }}</a>
                                </td>
                                <td class="text-center">{{ $row->points }}</td>
                                <td class="text-center">{{ $row->count_max }}</td>
                                <td class="text-center">{{ $row->count_170_plus }}</td>
                                <td class="text-center">{{ $row->count_qf }}</td>
                                <td class="text-center">{{ $row->count_hf }}</td>
                                <td class="text-center">{{ $row->best_qf !== null ? $row->best_qf . ' lotek' : '–' }}</td>
                                <td class="text-center">{{ $row->best_hf ?? '–' }}</td>
                            </tr>
                        @endforeach
                        @if($standings->isEmpty())
                            <tr>
                                <td colspan="9" class="py-6 text-center text-text-muted">Brak danych. Rozegraj turnieje w sezonach tej ligi.</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>

                <h2 class="section-title">Sezony</h2>
                <div class="space-y-3">
                    @forelse($seasons as $season)
                        <a href="{{ route('seasons.show', ['season' => $season->id]) }}">
                            <div class="list-item mb-2">{{ $season->name }}</div>
                        </a>
                    @empty
                        <x-empty-state
                            class="!py-10"
                            title="Brak sezonów"
                            description="Dodaj sezon z panelu zarządzania ligą."
                        />
                    @endforelse
                </div>

            </div>
        </div>

    </div>

@endsection
