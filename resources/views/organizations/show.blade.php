@extends('layouts.app')

@section('title', $organization ? $organization->name : 'Szczegóły')

@section('content')

    <div class="detail-layout">

        @organizationAdmin($organization)
            <aside class="admin-sidebar">
                <h2 class="admin-sidebar-title">⚙️ Zarządzanie organizacją</h2>

                <nav class="flex flex-col space-y-3">
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
                    <x-delete-application-entity
                        :action="route('organizations.destroy', $organization->id)"
                        :name="$organization->name"
                        label="organizację"
                        hint="Znikną też jej sezony, turnieje, ligi i sezony ligowe. Przez 90 dni przywrócić może je operator platformy."
                    />
                </nav>
            </aside>
        @endorganizationAdmin

        <div class="detail-main">
            <div class="detail-content">

                <x-place-bar
                    current="organization"
                    :organization-name="$organization->name"
                />

                @if(filled($organization->description))
                    <p class="place-lead">{{ $organization->description }}</p>
                @endif

                @php
                    $seasonCount = $seasons->count();
                    $leagueCount = $leagues->count();
                    $tournamentCount = $seasons->sum(fn ($season) => (int) ($season->tournamentCount ?? 0));
                    $relatedUsersUrl = null;
                    $guestsUrl = null;
                @endphp
                @organizationAdmin($organization)
                    @php
                        $relatedUsersUrl = route('organizations.relatedUsers', $organization->id);
                        $guestsUrl = route('organizations.guests', $organization->id);
                    @endphp
                @endorganizationAdmin
                <div class="place-facts">
                    <section class="place-fact-group">
                        <p class="place-fact-group-label">Daty</p>
                        <div class="place-fact-group-items">
                            <x-place-stat icon="calendar" label="Utworzono">{{ $organization->createdAt->locale(app()->getLocale())->translatedFormat('j F Y') }}</x-place-stat>
                            <x-place-stat icon="activity" label="Ostatnia aktywność">{{ $organization->getUpdatedAtFormatted() }}</x-place-stat>
                        </div>
                    </section>
                    <section class="place-fact-group">
                        <p class="place-fact-group-label">Rozgrywki</p>
                        <div class="place-fact-group-items">
                            <x-place-stat icon="seasons" label="Sezony"><span class="score-num">{{ $seasonCount }}</span></x-place-stat>
                            <x-place-stat icon="leagues" label="Ligi"><span class="score-num">{{ $leagueCount }}</span></x-place-stat>
                            <x-place-stat icon="tournaments" label="Turnieje"><span class="score-num">{{ $tournamentCount }}</span></x-place-stat>
                        </div>
                    </section>
                    <section class="place-fact-group">
                        <p class="place-fact-group-label">Społeczność</p>
                        <div class="place-fact-group-items">
                            <x-people-counts
                                :related-count="$organization->relatedUserCount"
                                :guest-count="$organization->guestCount"
                                :related-url="$relatedUsersUrl"
                                :guests-url="$guestsUrl"
                            />
                        </div>
                    </section>
                </div>

                <x-section-head title="Sezony turniejowe" class="mt-8">
                    <x-slot:action>
                        @organizationAdmin($organization)
                            @if($seasons->isNotEmpty())
                                <x-add-action :href="route('seasons.create').'?organizationId='.$organization->id">Dodaj sezon</x-add-action>
                            @endif
                        @endorganizationAdmin
                    </x-slot:action>
                </x-section-head>
                @if($seasons->isEmpty())
                    <x-empty-state
                        class="!py-10"
                        title="Brak sezonów"
                        description="Sezon zbiera turnieje rozgrywane w jednym okresie."
                    >
                        @organizationAdmin($organization)
                            <x-add-action :href="route('seasons.create').'?organizationId='.$organization->id">Dodaj sezon</x-add-action>
                        @endorganizationAdmin
                    </x-empty-state>
                @else
                    <div class="place-grid">
                        @foreach($seasons as $season)
                            @php
                                $seasonMeta = collect([$season->getPlayDatesFormatted(), $season->tournamentCountLabel()])
                                    ->filter()
                                    ->implode(' · ');
                            @endphp
                            <x-place-card
                                kind="season"
                                :href="route('seasons.show', ['season' => $season->id])"
                                :name="$season->name"
                                :meta="$seasonMeta !== '' ? $seasonMeta : null"
                            />
                        @endforeach
                    </div>
                @endif

                <x-section-head title="Ligi" class="mt-12 mb-1">
                    <x-slot:action>
                        @organizationAdmin($organization)
                            @if($leagues->isNotEmpty())
                                <x-add-action :href="route('leagues.create', $organization->id)">Dodaj ligę</x-add-action>
                            @endif
                        @endorganizationAdmin
                    </x-slot:action>
                </x-section-head>
                <p class="text-text-muted text-sm mb-3">Piramida szczebli z sezonami ligowymi — osobno od sezonów turniejowych.</p>
                @if($leagues->isEmpty())
                    <x-empty-state
                        class="!py-10"
                        title="Brak lig"
                        description="Liga to piramida szczebli z własnymi sezonami ligowymi."
                    >
                        @organizationAdmin($organization)
                            <x-add-action :href="route('leagues.create', $organization->id)">Dodaj ligę</x-add-action>
                        @endorganizationAdmin
                    </x-empty-state>
                @else
                    <div class="place-grid">
                        @foreach($leagues as $league)
                            <x-place-card
                                kind="league"
                                :href="route('leagues.show', $league)"
                                :name="$league->name"
                                :meta="$league->divisions->count().' '.($league->divisions->count() === 1 ? 'szczebel' : 'szczeble')"
                            />
                        @endforeach
                    </div>
                @endif

            </div>
        </div>

    </div>

@endsection
