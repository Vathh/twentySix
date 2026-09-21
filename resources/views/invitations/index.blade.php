@extends('layouts.app')

@section('title', 'Zaproszenia')

@section('content')
    <div class="max-w-3xl mx-auto px-4 pt-6 pb-12">
        <header class="entity-header mb-6">
            <p class="entity-eyebrow">Konto</p>
            <h1 class="entity-title">Zaproszenia</h1>
            <span class="entity-rule" aria-hidden="true"></span>
        </header>

        <div class="inline-flex rounded-lg border border-border p-1 mb-8 bg-bg-elevated/40" role="tablist">
            <a href="{{ route('invitations.index') }}"
               role="tab"
               aria-selected="{{ $tab === 'gra' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-semibold transition {{ $tab === 'gra' ? 'bg-accent text-on-accent' : 'text-text-secondary hover:text-text' }}">
                Gra
                @if($graCount > 0)
                    <span class="text-xs font-bold {{ $tab === 'gra' ? 'text-on-accent' : 'text-accent' }}">{{ $graCount }}</span>
                @endif
            </a>
            <a href="{{ route('invitations.index', ['tab' => 'friends']) }}"
               role="tab"
               aria-selected="{{ $tab === 'friends' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-semibold transition {{ $tab === 'friends' ? 'bg-accent text-on-accent' : 'text-text-secondary hover:text-text' }}">
                Znajomi
                @if($friendCount > 0)
                    <span class="text-xs font-bold {{ $tab === 'friends' ? 'text-on-accent' : 'text-accent' }}">{{ $friendCount }}</span>
                @endif
            </a>
        </div>

        @if($tab === 'friends')
            @if($friendInvitations->isEmpty())
                <x-empty-state
                    title="Brak zaproszeń"
                    description="Gdy ktoś wyśle Ci zaproszenie do znajomych, pojawi się tutaj."
                />
            @else
                <ul class="space-y-2">
                    @foreach($friendInvitations as $invitation)
                        <x-invitation-row
                            :title="$invitation->senderPlayer?->name ?? 'Gracz'"
                            subtitle="Chce dodać Cię do znajomych"
                            :url="$invitation->senderPlayer ? route('players.show', $invitation->senderPlayer->id) : null"
                        >
                            @include('invitations.partials.actions', [
                                'acceptUrl' => route('friends.invitations.accept', $invitation->id),
                                'rejectUrl' => route('friends.invitations.reject', $invitation->id),
                            ])
                        </x-invitation-row>
                    @endforeach
                </ul>
            @endif
        @elseif($graCount === 0)
            <x-empty-state
                title="Brak zaproszeń do gry"
                description="Tu pojawią się lobby, turnieje oraz zaproszenia do organizacji i sezonów."
            />
        @else
            @if(count($leagueGameInvitations) > 0 || $quickGameInvitations->isNotEmpty())
                <section class="mb-8">
                    <h2 class="section-title mt-0">Do gry</h2>
                    <ul class="space-y-2">
                        @foreach($leagueGameInvitations as $invitation)
                            <x-invitation-row
                                :title="$invitation['leagueName'] ?? 'Mecz ligowy'"
                                :subtitle="collect([$invitation['hostName'] ? 'Od '.$invitation['hostName'] : null, $invitation['formatLabel'] ?? null])->filter()->implode(' · ')"
                            >
                                @include('invitations.partials.actions', [
                                    'acceptUrl' => route('invitations.league-games.accept', $invitation['id']),
                                    'rejectUrl' => route('invitations.league-games.reject', $invitation['id']),
                                    'acceptLabel' => 'Akceptuj',
                                ])
                            </x-invitation-row>
                        @endforeach
                        @foreach($quickGameInvitations as $invitation)
                            <x-invitation-row
                                :title="($invitation['hostName'] ?? 'Gracz').' zaprasza'"
                                subtitle="Szybka gra"
                            >
                                @include('invitations.partials.actions', [
                                    'acceptUrl' => route('invitations.quick-game.join', $invitation['lobbyId']),
                                    'rejectUrl' => route('invitations.quick-game.reject', $invitation['id']),
                                    'acceptLabel' => 'Dołącz',
                                ])
                            </x-invitation-row>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($tournamentInvitations->isNotEmpty())
                <section class="mb-8">
                    <h2 class="section-title mt-0">Turnieje</h2>
                    <ul class="space-y-2">
                        @foreach($tournamentInvitations as $invitation)
                            <x-invitation-row
                                :title="$invitation->tournamentName"
                                :subtitle="$invitation->status->label()"
                            >
                                @if($invitation->status === \App\Enums\TournamentInvitationStatus::PENDING)
                                    @include('invitations.partials.actions', [
                                        'acceptUrl' => route('invitations.tournaments.accept', $invitation->id),
                                        'rejectUrl' => route('invitations.tournaments.reject', $invitation->id),
                                    ])
                                @elseif($invitation->status === \App\Enums\TournamentInvitationStatus::ACCEPTED)
                                    @include('invitations.partials.actions', [
                                        'acceptUrl' => route('invitations.tournaments.withdraw', $invitation->id),
                                        'acceptLabel' => 'Wycofaj',
                                        'acceptClass' => 'btn-mini-danger',
                                    ])
                                @endif
                            </x-invitation-row>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($organizationInvitations->isNotEmpty() || $seasonInvitations->isNotEmpty() || $leagueInvitations->isNotEmpty())
                <section>
                    <h2 class="section-title mt-0">Składy</h2>
                    <ul class="space-y-2">
                        @foreach($organizationInvitations as $invitation)
                            <x-invitation-row :title="$invitation->organizationName" subtitle="Organizacja · {{ $invitation->status->label() }}">
                                @include('invitations.partials.actions', [
                                    'acceptUrl' => route('invitations.organizations.accept', $invitation->id),
                                    'rejectUrl' => route('invitations.organizations.reject', $invitation->id),
                                ])
                            </x-invitation-row>
                        @endforeach
                        @foreach($seasonInvitations as $invitation)
                            <x-invitation-row :title="$invitation->seasonName" subtitle="Sezon · {{ $invitation->status->label() }}">
                                @include('invitations.partials.actions', [
                                    'acceptUrl' => route('invitations.seasons.accept', $invitation->id),
                                    'rejectUrl' => route('invitations.seasons.reject', $invitation->id),
                                ])
                            </x-invitation-row>
                        @endforeach
                        @foreach($leagueInvitations as $invitation)
                            <x-invitation-row :title="$invitation->leagueName" subtitle="Liga · {{ $invitation->status->label() }}">
                                @include('invitations.partials.actions', [
                                    'acceptUrl' => route('invitations.leagues.accept', $invitation->id),
                                    'rejectUrl' => route('invitations.leagues.reject', $invitation->id),
                                ])
                            </x-invitation-row>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif
    </div>
@endsection
