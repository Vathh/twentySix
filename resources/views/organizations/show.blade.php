@extends('layouts.app')

@section('title', $organization ? $organization->name : 'Szczegóły')

@section('content')

    <div class="detail-layout">

        @organizationAdmin($organization)
            <aside class="admin-sidebar">
                <h2 class="admin-sidebar-title">⚙️ Zarządzanie organizacją</h2>

                <nav class="flex flex-col space-y-3">
                    <a href="{{ route('seasons.create') }}?organizationId={{ $organization->id }}" class="admin-sidebar-link">
                        ➕ Dodaj sezon
                    </a>
                    <a href="{{ route('leagues.create', $organization->id) }}" class="admin-sidebar-link">
                        ➕ Dodaj ligę
                    </a>
                    <a href="{{ route('organizations.admins', $organization->id) }}" class="admin-sidebar-link">
                        💼 Administratorzy
                    </a>
                    <a href="{{ route('organizations.edit', ['organization' => $organization->id]) }}" class="admin-sidebar-link">
                        ✏️ Edytuj organizację
                    </a>
                    <a href="{{ route('organizations.relatedUsers', $organization->id) }}" class="admin-sidebar-link">
                        👥 Powiązani użytkownicy
                    </a>
                    <a href="{{ route('organizations.guests', $organization->id) }}" class="admin-sidebar-link">
                        👤 Goście
                    </a>
                </nav>
            </aside>
        @endorganizationAdmin

        <div class="detail-main">
            <div class="detail-content">

                <header class="entity-header">
                    <p class="entity-eyebrow">Organizacja</p>
                    <h1 class="entity-title">{{ $organization->name }}</h1>
                    <span class="entity-rule" aria-hidden="true"></span>
                </header>

                <div class="entity-meta">
                    <dl class="entity-meta-grid cols-2">
                        <div class="entity-meta-item span-full">
                            <dt class="entity-meta-label">Opis</dt>
                            <dd class="entity-meta-value">{{ $organization->description ?: '—' }}</dd>
                        </div>
                        <div class="entity-meta-item">
                            <dt class="entity-meta-label">Data utworzenia</dt>
                            <dd class="entity-meta-value score-num">{{ $organization->createdAtDate() }}</dd>
                        </div>
                        <div class="entity-meta-item">
                            <dt class="entity-meta-label">Ilość sezonów</dt>
                            <dd class="entity-meta-value score-num">{{ count($organization->seasons) }}</dd>
                        </div>
                        <div class="entity-meta-item span-full">
                            <dt class="entity-meta-label">Ostatnia aktywność</dt>
                            <dd class="entity-meta-value score-num">{{ $organization->updatedAtDate() }}</dd>
                        </div>
                    </dl>
                </div>

                <h2 class="section-title mt-12">Ligi</h2>
                <p class="text-text-muted text-sm mb-3">Piramida szczebli z sezonami ligowymi — osobno od sezonów turniejowych.</p>
                <div class="space-y-3">
                    @forelse($leagues as $league)
                        <a href="{{ route('leagues.show', $league) }}">
                            <div class="list-item mb-2">
                                {{ $league->name }}
                                <span class="text-text-muted text-sm font-normal">
                                    · {{ $league->divisions->count() }} {{ $league->divisions->count() === 1 ? 'szczebel' : 'szczeble' }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <x-empty-state
                            class="!py-10"
                            title="Brak lig"
                            description="Dodaj ligę z panelu zarządzania organizacją."
                        />
                    @endforelse
                </div>

                <h2 class="section-title mt-12">Sezony</h2>
                <div class="space-y-3">
                    @forelse($seasons as $season)
                        <a href="{{ route('seasons.show', ['season' => $season->id]) }}">
                            <div class="list-item mb-2">{{ $season->name }}</div>
                        </a>
                    @empty
                        <x-empty-state
                            class="!py-10"
                            title="Brak sezonów"
                            description="Dodaj sezon z panelu zarządzania organizacją."
                        />
                    @endforelse
                </div>

            </div>
        </div>

    </div>

@endsection
