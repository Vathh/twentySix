@extends('layouts.app')

@section('title', $season ? $season->name : 'Szczegóły')

@section('content')

    <div class="detail-layout">

        @seasonAdmin($season)
        <aside class="admin-sidebar">
            <h2 class="admin-sidebar-title">⚙️ Zarządzanie sezonem</h2>

            <nav class="flex flex-col space-y-3">
                <a href="{{ route('seasons.admins', $season->id) }}" class="admin-sidebar-link">
                    💼 Administratorzy
                </a>
                <a href="{{ route('seasons.edit', ['season' => $season->id]) }}" class="admin-sidebar-link">
                    ✏️ Edytuj sezon
                </a>
                <a href="{{ route('seasons.relatedUsers', $season->id) }}" class="admin-sidebar-link">
                    👥 Powiązani użytkownicy
                </a>
                <a href="{{ route('seasons.guests', $season->id) }}" class="admin-sidebar-link">
                    👤 Goście
                </a>
                <x-delete-application-entity
                    :action="route('seasons.destroy', $season->id)"
                    :name="$season->name"
                    label="sezon"
                    hint="Znikną też turnieje tego sezonu. Przez 90 dni przywrócić może je operator platformy."
                />
            </nav>
        </aside>
        @endseasonAdmin

        <div class="detail-main">
            <div class="detail-content">

                <x-place-bar
                    current="season"
                    :organization-name="$season->organization?->name"
                    :organization-url="$season->organization ? route('organizations.show', $season->organization->id) : null"
                    :season-name="$season->name"
                    :current-meta="$season->getPlayDatesFormatted()"
                />

                @php
                    $relatedUsersUrl = null;
                    $guestsUrl = null;
                @endphp
                @seasonAdmin($season)
                    @php
                        $relatedUsersUrl = route('seasons.relatedUsers', $season->id);
                        $guestsUrl = route('seasons.guests', $season->id);
                    @endphp
                @endseasonAdmin
                <div class="place-facts mt-4">
                    <section class="place-fact-group">
                        <p class="place-fact-group-label">Społeczność</p>
                        <div class="place-fact-group-items">
                            <x-people-counts
                                :related-count="$season->relatedUserCount"
                                :guest-count="$season->guestCount"
                                :related-url="$relatedUsersUrl"
                                :guests-url="$guestsUrl"
                            />
                        </div>
                    </section>
                </div>

                @include('seasons.partials.standings', ['standings' => $standings])

                @php
                    $tournaments = $season->tournaments->sortBy(function ($tournament) {
                        $rank = match ($tournament->status) {
                            \App\Enums\TournamentStatus::GROUP, \App\Enums\TournamentStatus::PLAYOFF => 0,
                            \App\Enums\TournamentStatus::CREATED => 1,
                            \App\Enums\TournamentStatus::FINISHED => 2,
                        };
                        $dateKey = PHP_INT_MAX - ($tournament->date?->getTimestamp() ?? 0);

                        return sprintf('%d-%010d', $rank, $dateKey);
                    })->values();
                @endphp
                <x-section-head title="Turnieje" class="mt-12">
                    <x-slot:action>
                        @seasonAdmin($season)
                            @if($tournaments->isNotEmpty())
                                <x-add-action :href="route('tournaments.create').'?seasonId='.$season->id">Dodaj turniej</x-add-action>
                            @endif
                        @endseasonAdmin
                    </x-slot:action>
                </x-section-head>
                @if($tournaments->isEmpty())
                    <x-empty-state
                        class="!py-10"
                        title="Brak turniejów"
                        description="W tym sezonie nie ma jeszcze turniejów."
                    >
                        @seasonAdmin($season)
                            <x-add-action :href="route('tournaments.create').'?seasonId='.$season->id">Dodaj turniej</x-add-action>
                        @endseasonAdmin
                    </x-empty-state>
                @else
                    <div class="place-grid">
                        @foreach($tournaments as $tournament)
                            <x-place-card
                                kind="tournament"
                                :href="route('tournaments.show', ['tournament' => $tournament->id])"
                                :name="$tournament->name"
                                :meta="$tournament->getPlayDateFormatted() ?? 'Data nieustalona'"
                                :status-label="$tournament->status->label()"
                                :status-variant="$tournament->status->badgeVariant()"
                            />
                        @endforeach
                    </div>
                @endif

            </div>
        </div>

    </div>

@endsection
