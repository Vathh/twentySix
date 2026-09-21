@extends('layouts.app')

@section('title', 'Start turnieju')

@section('content')
    <div class="container mx-auto py-4 sm:py-5 max-w-5xl min-w-0">

        <h1 class="page-title mb-4 break-words">
            Start turnieju: {{ $tournament->name }}
        </h1>

        <x-errors/>

        <div
            x-show="$store.tournamentStartLive.flash"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-2"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed top-5 right-5 z-50 w-96 max-w-[calc(100vw-2rem)] p-3 rounded-lg shadow-xl flex items-start justify-between gap-3 bg-bg-elevated"
            :class="$store.tournamentStartLive.flash?.type === 'error'
                ? 'border border-danger text-danger-text'
                : 'border border-success text-success-bright'"
        >
            <div x-text="$store.tournamentStartLive.flash?.message"></div>
            <button
                type="button"
                class="shrink-0 font-bold opacity-70 hover:opacity-100"
                @click="$store.tournamentStartLive.flash = null"
            >&times;</button>
        </div>

        @if(!$canManageParticipants)
            <div class="mb-6 p-4 rounded-lg bg-bg-elevated border border-accent text-text-secondary">
                Turniej już wystartował — zaproszenia i zmiany uczestników są zablokowane.
            </div>
        @endif

        {{-- Strefa 1: Uczestnicy turnieju --}}
        <div
            class="mb-8 card border-2 border-success/40"
            @if($canManageParticipants)
                x-data
                x-init="$store.tournamentStartLive.init(@js([
                    'participants' => $participantsLive ?? [],
                    'participantCount' => $participantCount,
                    'minPlayers' => $minPlayers,
                    'canManage' => true,
                    'csrfToken' => csrf_token(),
                    'inviteUrl' => route('tournaments.invitations.send', $tournament->id),
                    'joinSelfUrl' => route('tournaments.invitations.join-self', $tournament->id),
                    'currentUserId' => auth()->id(),
                    'invitationPipeline' => $invitationPipelineLive ?? [],
                ]))"
            @endif
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                <h2 class="section-title">Uczestnicy turnieju</h2>
                @if($canManageParticipants)
                    <span
                        class="text-sm font-semibold px-3 py-1 rounded-full shrink-0"
                        :class="$store.tournamentStartLive.participantCount >= $store.tournamentStartLive.minPlayers
                            ? 'bg-success-muted text-success-bright'
                            : 'bg-accent/20 text-accent'"
                        x-text="$store.tournamentStartLive.participantCount + ' / min. ' + $store.tournamentStartLive.minPlayers"
                    ></span>
                @else
                    <span @class([
                        'text-sm font-semibold px-3 py-1 rounded-full shrink-0',
                        'bg-success-muted text-success-bright' => $participantCount >= $minPlayers,
                        'bg-accent/20 text-accent' => $participantCount < $minPlayers,
                    ])>
                        {{ $participantCount }} / min. {{ $minPlayers }}
                    </span>
                @endif
            </div>
            <p class="text-text-secondary text-sm mb-3">
                W turnieju grają wszyscy z tej listy — zaakceptowane zaproszenia oraz goście dodani do turnieju.
            </p>

            @if($canManageParticipants)
                <p
                    class="text-text-secondary/80 italic text-sm"
                    x-show="$store.tournamentStartLive.participants.length === 0"
                >
                    Brak uczestników. Użyj sekcji poniżej, aby wysłać zaproszenia lub dodać gości.
                </p>
                <div x-show="$store.tournamentStartLive.participants.length > 0" x-cloak>
                    <p
                        class="text-accent/70 text-xs mb-2"
                        x-show="$store.tournamentStartLive.participants.length > 12"
                    >Duża lista — przewiń, aby zobaczyć wszystkich.</p>
                    <div class="max-h-36 sm:max-h-44 overflow-y-auto overflow-x-hidden pr-1 -mr-1">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="p in $store.tournamentStartLive.participants" :key="(p.kind || '') + '-' + (p.invitationId || p.playerId)">
                                <div class="flex items-center gap-1 bg-bg text-text-secondary pl-3 pr-1 py-1.5 rounded-lg text-sm">
                                    <span>
                                        <span x-text="p.name"></span>
                                        <span
                                            class="text-xs text-accent ml-1"
                                            x-show="p.kind === 'guest'"
                                        >gość</span>
                                    </span>
                                    <button
                                        type="button"
                                        class="btn-mini-danger w-7 h-7 p-0 flex items-center justify-center font-bold"
                                        title="Usuń z turnieju"
                                        x-show="p.removeUrl"
                                        :disabled="$store.tournamentStartLive.busyKey === ((p.kind || '') + '-' + (p.invitationId || p.playerId))"
                                        @click="$store.tournamentStartLive.removeParticipant(p)"
                                    >×</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            @elseif($participants->isEmpty())
                <p class="text-text-secondary/80 italic text-sm">
                    Brak uczestników. Użyj sekcji poniżej, aby wysłać zaproszenia lub dodać gości.
                </p>
            @else
                @if($participantCount > 12)
                    <p class="text-accent/70 text-xs mb-2">Duża lista — przewiń, aby zobaczyć wszystkich.</p>
                @endif
                <div class="max-h-36 sm:max-h-44 overflow-y-auto overflow-x-hidden pr-1 -mr-1">
                    <div class="flex flex-wrap gap-2">
                        @foreach($participants as $participant)
                            <div class="flex items-center gap-1 bg-bg text-text-secondary pl-3 pr-1 py-1.5 rounded-lg  text-sm">
                                <span>
                                    {{ $participant['name'] }}
                                    @if($participant['kind'] === 'guest')
                                        <span class="text-xs text-accent ml-1">gość</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if($canManageParticipants)
            {{-- Strefa 2: Dodaj uczestników — live WS zawsze (nie tylko przy QR) --}}
            <div
                class="mb-8 card overflow-hidden"
                x-data="tournamentJoinRequestsLive(@js([
                    'channel' => 'tournament.'.$tournament->id,
                    'snapshotUrl' => route('tournaments.join-requests-live', $tournament->id),
                    'csrfToken' => csrf_token(),
                    'inviteUrl' => route('tournaments.invitations.send', $tournament->id),
                    'joinSelfUrl' => route('tournaments.invitations.join-self', $tournament->id),
                    'currentUserId' => auth()->id(),
                    'createGuestUrl' => route('tournaments.participants.guests.create', $tournament->id),
                    'addGuestUrl' => route('tournaments.participants.guests.add', $tournament->id),
                    'invitationPipeline' => $invitationPipelineLive ?? [],
                    'relatedGuests' => ($relatedGuests ?? collect())->values()->all(),
                    'initialRequests' => $pendingJoinRequestsLive ?? [],
                    'initialParticipants' => $participantsLive ?? [],
                    'participantCount' => $participantCount,
                    'minPlayers' => $minPlayers,
                    'canManage' => true,
                    'reverb' => \App\Support\Broadcasting\ReverbClientConfig::forWeb(),
                ]))"
                x-init="init()"
            >
                <div class="border-b border-border px-6 pt-6 pb-4">
                    <h2 class="text-xl font-semibold text-accent mb-4">Dodaj uczestników</h2>

                    {{-- QR zgłoszenia --}}
                    @if(!empty($joinCode) && !empty($joinUrl))
                        <div class="mb-6 p-4 rounded-lg border border-border bg-bg/40">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <h3 class="text-accent font-semibold mb-0">Dołącz przez QR</h3>
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-semibold bg-accent/25 text-accent"
                                    x-text="connectionLabel()"
                                >Łączenie…</span>
                            </div>
                            <p class="text-text-secondary text-sm mb-4">
                                Zawodnik skanuje kod → zgłasza się w aplikacji. Zgłoszenia pojawiają się poniżej na żywo — zatwierdzasz Dołącz / Odrzuć.
                            </p>
                            <div class="flex flex-wrap items-start gap-6">
                                <div class="bg-white p-3 rounded-lg inline-block">
                                    <img
                                        src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data={{ urlencode($joinUrl) }}"
                                        width="180"
                                        height="180"
                                        alt="QR dołączania do turnieju"
                                    >
                                </div>
                                <div class="flex-1 min-w-[180px]">
                                    <p class="text-text-secondary text-sm mb-1">Kod</p>
                                    <p class="font-mono text-2xl tracking-widest text-accent mb-3">{{ $joinCode }}</p>
                                    <p class="text-text-secondary text-xs break-all mb-4">{{ $joinUrl }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        <form action="{{ route('tournaments.join-code.regenerate', $tournament->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn-mini">Nowy kod</button>
                                        </form>
                                        <form action="{{ route('tournaments.join-code.toggle', $tournament->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="enabled" value="{{ !empty($joinCodeEnabled) ? 0 : 1 }}">
                                            <button type="submit" class="btn-mini">
                                                {{ !empty($joinCodeEnabled) ? 'Wyłącz zgłoszenia' : 'Włącz zgłoszenia' }}
                                            </button>
                                        </form>
                                    </div>
                                    @unless(!empty($joinCodeEnabled))
                                        <p class="text-danger text-sm mt-2">Przyjmowanie zgłoszeń jest wyłączone.</p>
                                    @endunless
                                </div>
                            </div>

                            <div class="mt-5">
                                <h4 class="text-accent font-semibold mb-2">
                                    Zgłoszenia
                                    <span
                                        class="text-sm font-normal text-text-secondary"
                                        x-show="requests.length > 0"
                                        x-text="'(' + requests.length + ')'"
                                    ></span>
                                </h4>
                                <p class="text-text-secondary text-sm" x-show="requests.length === 0">
                                    Brak oczekujących zgłoszeń.
                                </p>
                                <div
                                    class="overflow-x-auto rounded-lg border border-border"
                                    x-show="requests.length > 0"
                                    x-cloak
                                >
                                    <table class="w-full text-text-secondary text-sm">
                                        <thead class="bg-bg">
                                            <tr class="text-accent">
                                                <th class="text-left py-2 px-3">Zawodnik</th>
                                                <th class="text-left py-2 px-3">Zgłoszono</th>
                                                <th class="text-left py-2 px-3">Akcja</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="req in requests" :key="req.id">
                                                <tr class="border-t border-border/50">
                                                    <td class="py-2 px-3" x-text="req.playerName"></td>
                                                    <td class="py-2 px-3" x-text="req.createdAt"></td>
                                                    <td class="py-2 px-3">
                                                        <div class="flex flex-wrap gap-2">
                                                            <button
                                                                type="button"
                                                                class="btn-mini"
                                                                :disabled="busyId === req.id"
                                                                @click="resolveRequest(req, 'approve')"
                                                            >Dołącz</button>
                                                            <button
                                                                type="button"
                                                                class="btn-mini"
                                                                :disabled="busyId === req.id"
                                                                @click="resolveRequest(req, 'reject')"
                                                            >Odrzuć</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Wyszukiwarka — zawsze widoczna (niezależnie od zakładek) --}}
                    <div
                        class="mb-2"
                        x-data="tournamentUserSearch(@js([
                            'searchUrl' => route('tournaments.invitations.search', $tournament->id),
                            'csrfToken' => csrf_token(),
                        ]))"
                    >
                        <h3 class="text-accent font-semibold mb-2">Wyszukaj użytkownika</h3>
                        <div
                            class="mb-4"
                            x-show="!$store.tournamentStartLive.isCurrentUserParticipant()"
                            x-cloak
                        >
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="$store.tournamentStartLive.inviteBusyKey === 'join-self'"
                                @click="$store.tournamentStartLive.joinSelf()"
                            >Dodaj mnie</button>
                            <p class="text-text-secondary text-sm mt-2">
                                Wejdziesz od razu na listę uczestników, bez akceptacji w aplikacji.
                            </p>
                        </div>
                        <p class="text-text-secondary text-sm mb-3">Wpisz imię lub fragment nazwy gracza i wyślij zaproszenie.</p>
                        <form @submit.prevent="search()" class="flex flex-wrap items-center gap-4">
                            <input
                                type="text"
                                x-model="query"
                                placeholder="Min. 2 znaki..."
                                class="input-field flex-1 min-w-[200px]"
                                autocomplete="off"
                            >
                            <button type="submit" class="btn btn-primary" :disabled="loading">
                                <span x-text="loading ? 'Szukam…' : 'Szukaj'"></span>
                            </button>
                        </form>

                        <p class="text-text-secondary mt-3 text-sm" x-show="searched && results.length === 0" x-cloak>
                            Brak wyników.
                        </p>
                        <div class="flex flex-wrap gap-2 mt-3" x-show="results.length > 0" x-cloak>
                            <template x-for="user in results" :key="user.id">
                                <div class="flex items-center gap-2 bg-bg rounded-lg px-3 py-2">
                                    <span class="text-text-secondary text-sm" x-text="user.name"></span>
                                    <button
                                        type="button"
                                        class="btn-mini"
                                        :disabled="$store.tournamentStartLive.inviteBusyKey === ('search-' + user.id)"
                                        @click="invite(user)"
                                    >Zaproś</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Goście bez konta — zawsze widoczne --}}
                    <div class="mt-6 pt-6 border-t border-border">
                        <h3 class="text-accent font-semibold mb-2">Dodaj gościa (bez konta)</h3>
                        <p class="text-text-secondary text-sm mb-3">
                            Gracz niezarejestrowany w aplikacji — trafi od razu na listę uczestników turnieju.
                        </p>
                        <form
                            @submit.prevent="$store.tournamentStartLive.createGuest()"
                            class="flex flex-wrap items-center gap-4"
                        >
                            <input type="text"
                                   x-model="$store.tournamentStartLive.guestName"
                                   placeholder="Imię gościa..."
                                   maxlength="20"
                                   class="input-field flex-1 min-w-[200px]"
                                   required>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="$store.tournamentStartLive.guestBusy"
                            >
                                <span x-text="$store.tournamentStartLive.guestBusy ? 'Dodaję…' : 'Dodaj gościa'"></span>
                            </button>
                        </form>
                    </div>

                    {{-- Oczekujące zaproszenia --}}
                    <div class="mt-6" x-show="$store.tournamentStartLive.invitationPipeline.length > 0" x-cloak>
                        <h3 class="text-accent font-semibold mb-2">Zaproszenia w toku</h3>
                        <div class="overflow-x-auto rounded-lg border border-border">
                            <table class="w-full text-text-secondary text-sm">
                                <thead class="bg-bg">
                                    <tr class="text-accent">
                                        <th class="text-left py-2 px-3">Zawodnik</th>
                                        <th class="text-left py-2 px-3">Status</th>
                                        <th class="text-left py-2 px-3">Akcja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        x-for="inv in $store.tournamentStartLive.invitationPipeline"
                                        :key="inv.id"
                                    >
                                        <tr class="border-t border-border/50">
                                            <td class="py-2 px-3" x-text="inv.name"></td>
                                            <td class="py-2 px-3" x-text="inv.statusLabel"></td>
                                            <td class="py-2 px-3">
                                                <button
                                                    type="button"
                                                    class="btn-mini"
                                                    x-show="inv.isPending"
                                                    :disabled="$store.tournamentStartLive.inviteBusyKey === ('cancel-' + inv.id)"
                                                    @click="$store.tournamentStartLive.cancelInvitation(inv)"
                                                >Anuluj</button>
                                                <button
                                                    type="button"
                                                    class="btn-mini"
                                                    x-show="inv.canReinvite"
                                                    :disabled="$store.tournamentStartLive.inviteBusyKey === ('reinvite-' + inv.id)"
                                                    @click="$store.tournamentStartLive.reinvite(inv)"
                                                >Zaproś ponownie</button>
                                                <span
                                                    class="text-accent/60"
                                                    x-show="!inv.isPending && !inv.canReinvite"
                                                >—</span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if($tournament->season)
                <div
                    x-data="{ activeTab: '{{ $addTab }}' }"
                >
                    <div class="border-b border-border px-6">
                        <div class="flex gap-1">
                            <button
                                type="button"
                                x-on:click="activeTab = 'registered'"
                                x-bind:class="activeTab === 'registered'
                                    ? 'bg-accent text-on-accent'
                                    : 'bg-bg text-text-secondary hover:bg-bg/80'"
                                class="px-5 py-2 rounded-t-lg font-semibold text-sm transition"
                            >
                                Stały skład
                            </button>
                            <button
                                type="button"
                                x-on:click="activeTab = 'guests'"
                                x-bind:class="activeTab === 'guests'
                                    ? 'bg-accent text-on-accent'
                                    : 'bg-bg text-text-secondary hover:bg-bg/80'"
                                class="px-5 py-2 rounded-t-lg font-semibold text-sm transition"
                            >
                                Goście organizacji / sezonu
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        {{-- Stały skład --}}
                        <div x-show="activeTab === 'registered'">
                            <div x-data="{ selectedRegulars: [] }">
                                <h3 class="text-accent font-semibold mb-2">Stały skład organizacji / sezonu</h3>
                                <p class="text-text-secondary text-sm mb-3">Zaznacz bywalców i wyślij masowe zaproszenia.</p>

                                @if($regulars->isEmpty())
                                    <p class="text-text-secondary text-sm">
                                        Brak powiązanych użytkowników.
                                        <a href="{{ route('seasons.relatedUsers', $tournament->season->id) }}" class="text-accent underline">Sezon</a>
                                        ·
                                        <a href="{{ route('organizations.relatedUsers', $tournament->season->organization->id) }}" class="text-accent underline">Organizacja</a>
                                    </p>
                                @else
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        @foreach($regulars as $regular)
                                            @if($regular['canInvite'])
                                                <div
                                                    x-on:click="selectedRegulars.includes({{ $regular['userId'] }})
                                                        ? selectedRegulars = selectedRegulars.filter(id => id !== {{ $regular['userId'] }})
                                                        : selectedRegulars.push({{ $regular['userId'] }})"
                                                    x-bind:class="selectedRegulars.includes({{ $regular['userId'] }})
                                                        ? 'bg-success text-on-success'
                                                        : 'bg-bg text-text-secondary'"
                                                    class="cursor-pointer px-3 py-2 rounded-lg text-sm transition  select-none"
                                                >
                                                    {{ $regular['name'] }}
                                                </div>
                                            @else
                                                <div class="px-3 py-2 rounded-lg text-sm bg-bg/50 text-text-secondary/60 border border-accent/20">
                                                    {{ $regular['name'] }}
                                                    <span class="text-xs text-accent block">{{ $regular['invitationStatus']?->label() }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>

                                    <form action="{{ route('tournaments.invitations.bulk', $tournament->id) }}" method="POST">
                                        @csrf
                                        <template x-for="userId in selectedRegulars" x-bind:key="userId">
                                            <input type="hidden" name="user_ids[]" x-bind:value="userId">
                                        </template>
                                        <button type="submit" class="btn btn-primary" x-bind:disabled="selectedRegulars.length === 0">
                                            Wyślij zaproszenie (<span x-text="selectedRegulars.length"></span>)
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        {{-- Goście --}}
                        <div x-show="activeTab === 'guests'" style="display: none;">
                            <h3 class="text-accent font-semibold mb-2">Powiązani goście</h3>
                            <p class="text-text-secondary text-sm mb-4">Dodaj gości z puli organizacji/sezonu do tego turnieju.</p>

                            <p
                                class="text-text-secondary text-sm"
                                x-show="$store.tournamentStartLive.relatedGuests.length === 0"
                            >
                                Brak powiązanych gości.
                                <a href="{{ route('seasons.guests', $tournament->season->id) }}" class="text-accent underline">Sezon</a>
                                ·
                                <a href="{{ route('organizations.guests', $tournament->season->organization->id) }}" class="text-accent underline">Organizacja</a>
                            </p>
                            <div
                                class="flex flex-wrap gap-3"
                                x-show="$store.tournamentStartLive.relatedGuests.length > 0"
                                x-cloak
                            >
                                <template
                                    x-for="guest in $store.tournamentStartLive.relatedGuests"
                                    :key="guest.playerId"
                                >
                                    <div class="flex flex-col items-center bg-bg rounded-lg p-4 min-w-[110px]">
                                        <span class="text-text-secondary text-sm text-center mb-2" x-text="guest.name"></span>
                                        <span
                                            class="text-xs text-accent font-semibold"
                                            x-show="guest.inTournament"
                                        >W turnieju</span>
                                        <button
                                            type="button"
                                            class="btn-mini"
                                            x-show="!guest.inTournament"
                                            :disabled="$store.tournamentStartLive.busyKey === ('related-' + guest.playerId)"
                                            @click="$store.tournamentStartLive.addRelatedGuest(guest)"
                                        >Dodaj</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        @endif

        {{-- Strefa 3: Start turnieju --}}
        @if($canManageParticipants)
            {{-- JSON poza atrybutem HTML — @json w x-data="..." psuje parser (cudzysłowy). --}}
            @php
                $tournamentStartConfig = [
                    'groupsCount' => $defaultGroupsCount,
                    'playoffBracketSize' => $defaultPlayoffBracketSize,
                    'tournamentFormat' => $defaultTournamentFormat,
                    'grandFinalMode' => (string) old('grandFinalMode', 'reset'),
                    'seBracketSize' => $seBracketSize,
                    'seByeCount' => $seByeCount,
                    'groupCountOptions' => $groupCountOptions,
                    'bracketOptionsByGroupCount' => $bracketOptionsByGroupCount,
                    'startConfigPreview' => $startConfigPreview,
                    'matchFormatStagesByBracket' => $matchFormatStagesByBracket,
                    'matchFormatStagesByBracketSe' => $matchFormatStagesByBracketSe,
                    'matchFormatStagesByBracketDe' => $matchFormatStagesByBracketDe,
                    'startingScoreOptions' => $startingScoreOptions,
                    'defaultMatchFormat' => $defaultMatchFormat,
                    'defaultMatchFormatsByStage' => $defaultMatchFormatsByStage,
                    'hasOrganizationFormatPresets' => $hasOrganizationFormatPresets,
                    'oldMatchFormats' => $oldMatchFormats,
                    'oldConsolationMatchFormats' => old('consolationMatchFormats', []),
                    'hasConsolationBracket' => (bool) old('hasConsolationBracket', false),
                    'minPlayers' => $minPlayers,
                    'minPlayersPerGroup' => $minPlayersPerGroup,
                    'participantCount' => $participantCount,
                ];
            @endphp
            <script type="application/json" id="tournament-start-config">
                @json($tournamentStartConfig)
            </script>
            <div
                x-data="tournamentStartForm()"
                x-init="init(); syncGroupsCount(); syncBracketSelect(); syncMatchFormats()"
                class="mb-8 card"
            >
                <h2 class="section-title text-accent">Start turnieju</h2>

                <p
                    class="text-text-secondary text-sm"
                    x-show="participantCount === 0"
                    x-cloak
                >Dodaj uczestników powyżej, aby wystartować turniej.</p>
                <p
                    class="text-text-secondary text-sm"
                    x-show="participantCount > 0 && participantCount < minPlayers"
                    x-cloak
                >
                    Potrzeba co najmniej <span x-text="minPlayers"></span> uczestników
                    (obecnie <span x-text="participantCount"></span>).
                </p>
                <form
                    action="{{ route('tournaments.run', $tournament->id) }}"
                    method="POST"
                    class="flex flex-col items-center gap-4"
                    x-show="participantCount >= minPlayers"
                    x-cloak
                >
                        @csrf

                        <div class="w-full max-w-2xl flex flex-col gap-3">
                            <p class="text-accent font-semibold">Rodzaj turnieju</p>
                            <label
                                class="flex items-start gap-3"
                                :class="canUseGroupsPlayoff ? 'cursor-pointer' : 'cursor-help'"
                                :title="groupsPlayoffDisabledReason"
                            >
                                <input type="radio" name="tournamentFormat" value="groups_playoff"
                                       class="mt-1"
                                       x-model="tournamentFormat"
                                       @change="onFormatChange()"
                                       x-bind:disabled="!canUseGroupsPlayoff">
                                <span>
                                    <span class="font-medium text-text-primary">Grupy + drabinka</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5" x-show="canUseGroupsPlayoff">
                                        Faza grupowa, potem playoff
                                    </span>
                                    <span
                                        class="block text-accent/90 text-xs mt-0.5"
                                        x-show="!canUseGroupsPlayoff"
                                        x-cloak
                                        x-text="groupsPlayoffDisabledReason"
                                    ></span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="radio" name="tournamentFormat" value="single_elimination"
                                       class="mt-1"
                                       x-model="tournamentFormat"
                                       @change="onFormatChange()">
                                <span>
                                    <span class="font-medium text-text-primary">Single elimination</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5">
                                        Tylko drabinka (bez grup); wolne losy dopełniają do potęgi 2
                                    </span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="radio" name="tournamentFormat" value="double_elimination"
                                       class="mt-1"
                                       x-model="tournamentFormat"
                                       @change="onFormatChange()">
                                <span>
                                    <span class="font-medium text-text-primary">Double elimination</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5">
                                        Drabinka wygranych + przegranych; dwie porażki = odpadnięcie
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 w-full max-w-2xl"
                             x-show="tournamentFormat === 'groups_playoff'"
                             x-cloak>
                            <div class="flex flex-col">
                                <label for="groupsCount" class="text-accent font-semibold mb-2">Liczba grup</label>
                                <select id="groupsCount" name="groupsCount" class="select-field"
                                        x-ref="groupsSelect"
                                        x-model.number="groupsCount" x-on:change="onGroupsChange()"
                                        x-bind:disabled="tournamentFormat !== 'groups_playoff'">
                                    @foreach ($groupCountOptions as $option)
                                        <option value="{{ $option }}" @selected($defaultGroupsCount === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                <p class="text-text-secondary/70 text-xs mt-2">
                                    Min. {{ $minPlayersPerGroup }} zawodników w grupie
                                </p>
                            </div>
                            <div class="flex flex-col">
                                <label for="playoffBracketSizeSelect" class="text-accent font-semibold mb-2">Etap drabinki</label>
                                <select id="playoffBracketSizeSelect"
                                        x-ref="bracketSelect"
                                        name="playoffBracketSize"
                                        class="select-field"
                                        x-model.number="playoffBracketSize"
                                        x-on:change="onBracketChange()"
                                        x-bind:disabled="tournamentFormat !== 'groups_playoff'">
                                    @foreach ($defaultBracketOptions as $option)
                                        <option value="{{ $option['value'] }}" @selected($defaultPlayoffBracketSize === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                <p class="text-text-secondary/70 text-xs mt-2">
                                    Od tego etapu zaczyna się faza pucharowa
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 w-full max-w-2xl"
                             x-show="tournamentFormat === 'single_elimination' || tournamentFormat === 'double_elimination'"
                             x-cloak>
                            <div class="flex flex-col justify-center rounded-lg border border-border bg-bg/40 p-4">
                                <p class="text-sm text-text-secondary">
                                    <span x-text="participantCount"></span> graczy → drabinka
                                    <span class="text-accent font-semibold" x-text="seBracketSize"></span>
                                    <template x-if="seByeCount > 0">
                                        <span> (<span x-text="seByeCount"></span> wolnych losów)</span>
                                    </template>
                                </p>
                            </div>
                            <div class="flex flex-col gap-3" x-show="tournamentFormat === 'double_elimination'" x-cloak>
                                <div class="flex flex-col">
                                    <label for="grandFinalMode" class="text-accent font-semibold mb-2">Grand Final</label>
                                    <select id="grandFinalMode" name="grandFinalMode" class="select-field"
                                            x-model="grandFinalMode"
                                            x-bind:disabled="tournamentFormat !== 'double_elimination'">
                                        <option value="reset">Reset (do 2 meczów, gdy wygra LB)</option>
                                        <option value="single">Jeden mecz o tytuł</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="w-full max-w-2xl rounded-lg border border-border bg-bg/40 p-4"
                             x-show="tournamentFormat === 'groups_playoff' && preview"
                             x-cloak>
                            <p class="text-accent font-semibold text-sm mb-3">Podgląd podziału</p>
                            <template x-for="(advanceCount, index) in (preview?.advances ?? [])" x-bind:key="index">
                                <div class="flex flex-wrap items-baseline justify-between gap-2 py-1 text-sm text-text-secondary border-b border-border/60 last:border-0">
                                    <span>
                                        Grupa <span x-text="index + 1"></span>:
                                        <span x-text="preview.groupSizes[index]"></span> graczy
                                    </span>
                                    <span class="text-accent">
                                        → <span x-text="advanceCount"></span> awansujących
                                    </span>
                                </div>
                            </template>
                        </div>

                        <p class="text-accent text-sm" x-show="tournamentFormat === 'groups_playoff'" x-cloak>
                            Drabinka playoff: <span x-text="$data.playoffBracketSize"></span> graczy awansujących
                        </p>

                        <div class="w-full max-w-2xl rounded-lg border border-border bg-bg/40 p-4"
                             x-show="tournamentFormat === 'groups_playoff' && canEnableConsolation"
                             x-cloak>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox"
                                       class="mt-1"
                                       x-model="hasConsolationBracket"
                                       @change="onConsolationToggle()">
                                <span>
                                    <span class="font-medium text-text-primary">Drabinka pocieszenia</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5">
                                        Osobna drabinka dla zawodników, którzy nie awansowali z grup
                                    </span>
                                </span>
                            </label>
                            <input type="hidden" name="hasConsolationBracket"
                                   x-bind:value="hasConsolationBracket ? 1 : 0">
                            <p class="text-sm text-text-secondary mt-3"
                               x-show="hasConsolationBracket"
                               x-cloak>
                                <span x-text="remainingAfterAdvance"></span> nieawansujących → drabinka
                                <span class="text-accent font-semibold" x-text="consolationBracketSize"></span>
                                <template x-if="consolationByeCount > 0">
                                    <span> (<span x-text="consolationByeCount"></span> wolnych losów)</span>
                                </template>
                            </p>
                        </div>

                        <div class="w-full max-w-3xl rounded-lg border border-border bg-bg/40 p-4"
                             x-show="activeFormatStages.length"
                             x-cloak>
                            <p class="text-accent font-semibold text-sm mb-1">Format gry per etap</p>
                            <p class="text-text-secondary/70 text-xs mb-4"
                               x-text="hasOrganizationFormatPresets
                                   ? 'Domyślne z presetów organizacji — możesz nadpisać przed startem. Tablet odczyta format z meczu.'
                                   : 'Domyślnie 501 · 1 set · 2 legi. Tablet odczyta format z meczu — bez konfiguracji przy starcie gry.'">
                            </p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-text-secondary">
                                    <thead class="text-accent">
                                        <tr class="border-b border-border">
                                            <th class="text-left py-2 pr-3 font-semibold">Etap</th>
                                            <th class="text-left py-2 px-2 font-semibold">Punkty</th>
                                            <th class="text-left py-2 px-2 font-semibold">Legi / set (pierwszy do)</th>
                                            <th class="text-left py-2 pl-2 font-semibold">Sety / mecz (pierwszy do)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="stage in activeFormatStages" x-bind:key="stage.value">
                                            <tr class="border-b border-border/60 last:border-0">
                                                <td class="py-2 pr-3 whitespace-nowrap" x-text="stage.label"></td>
                                                <td class="py-2 px-2">
                                                    <select class="select-field w-full min-w-[5rem]"
                                                            x-bind:name="'matchFormats[' + stage.value + '][startingScore]'"
                                                            x-model.number="matchFormats[stage.value].startingScore">
                                                        <template x-for="score in startingScoreOptions" x-bind:key="score">
                                                            <option x-bind:value="score" x-text="score"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 px-2">
                                                    <select class="select-field w-full min-w-[4rem]"
                                                            x-bind:name="'matchFormats[' + stage.value + '][legsToWinSet]'"
                                                            x-model.number="matchFormats[stage.value].legsToWinSet">
                                                        <template x-for="n in 15" x-bind:key="n">
                                                            <option x-bind:value="n" x-text="n"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 pl-2">
                                                    <select class="select-field w-full min-w-[4rem]"
                                                            x-bind:name="'matchFormats[' + stage.value + '][setsToWinMatch]'"
                                                            x-model.number="matchFormats[stage.value].setsToWinMatch">
                                                        <template x-for="n in 5" x-bind:key="n">
                                                            <option x-bind:value="n" x-text="n"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <template x-for="stage in activeFormatStages" x-bind:key="'dl-'+stage.value">
                                <div class="mt-4 pt-3 border-t border-border/40" x-show="matchFormats[stage.value]">
                                    <p class="text-sm font-medium text-text mb-2" x-text="stage.label"></p>
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <label class="block">
                                            <span class="flex items-center gap-2 text-sm mb-1">
                                                <input type="checkbox"
                                                       :checked="matchFormats[stage.value].dartLimit != null"
                                                       @change="
                                                           if ($event.target.checked) {
                                                               matchFormats[stage.value].dartLimit = matchFormats[stage.value].dartLimit || 45;
                                                           } else {
                                                               matchFormats[stage.value].dartLimit = null;
                                                               matchFormats[stage.value].lossThreshold = null;
                                                           }
                                                       ">
                                                Ogranicznik lotek
                                            </span>
                                            <div class="flex items-center gap-2" x-show="matchFormats[stage.value].dartLimit != null" x-cloak>
                                                <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                        @click="matchFormats[stage.value].dartLimit = Math.max(15, (matchFormats[stage.value].dartLimit || 45) - 3)">−</button>
                                                <input class="input-field text-center w-24" type="number" min="15" max="99" step="3"
                                                       x-bind:name="'matchFormats[' + stage.value + '][dartLimit]'"
                                                       x-model.number="matchFormats[stage.value].dartLimit">
                                                <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                        @click="matchFormats[stage.value].dartLimit = Math.min(99, (matchFormats[stage.value].dartLimit || 45) + 3)">+</button>
                                            </div>
                                            <input type="hidden" x-bind:name="'matchFormats[' + stage.value + '][dartLimit]'" value=""
                                                   x-show="matchFormats[stage.value].dartLimit == null">
                                        </label>
                                        <label class="block" x-show="matchFormats[stage.value].dartLimit != null" x-cloak>
                                            <span class="flex items-center gap-2 text-sm mb-1">
                                                <input type="checkbox"
                                                       :checked="matchFormats[stage.value].lossThreshold != null"
                                                       @change="matchFormats[stage.value].lossThreshold = $event.target.checked ? (matchFormats[stage.value].lossThreshold || 50) : null">
                                                Próg przegranej
                                            </span>
                                            <input class="input-field" type="number" min="2" max="170"
                                                   x-bind:name="'matchFormats[' + stage.value + '][lossThreshold]'"
                                                   x-show="matchFormats[stage.value].lossThreshold != null"
                                                   x-model.number="matchFormats[stage.value].lossThreshold">
                                            <input type="hidden" x-bind:name="'matchFormats[' + stage.value + '][lossThreshold]'" value=""
                                                   x-show="matchFormats[stage.value].lossThreshold == null">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="w-full max-w-3xl rounded-lg border border-border bg-bg/40 p-4"
                             x-show="tournamentFormat === 'groups_playoff' && hasConsolationBracket && consolationFormatStages.length"
                             x-cloak>
                            <p class="text-accent font-semibold text-sm mb-1">Format gry — drabinka pocieszenia</p>
                            <p class="text-text-secondary/70 text-xs mb-4">
                                Punkty, legi i sety osobno dla etapów drabinki pocieszenia.
                            </p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-text-secondary">
                                    <thead class="text-accent">
                                        <tr class="border-b border-border">
                                            <th class="text-left py-2 pr-3 font-semibold">Etap</th>
                                            <th class="text-left py-2 px-2 font-semibold">Punkty</th>
                                            <th class="text-left py-2 px-2 font-semibold">Legi / set (pierwszy do)</th>
                                            <th class="text-left py-2 pl-2 font-semibold">Sety / mecz (pierwszy do)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="stage in consolationFormatStages" x-bind:key="'c-'+stage.value">
                                            <tr class="border-b border-border/60 last:border-0">
                                                <td class="py-2 pr-3 whitespace-nowrap" x-text="stage.label"></td>
                                                <td class="py-2 px-2">
                                                    <select class="select-field w-full min-w-[5rem]"
                                                            x-bind:name="'consolationMatchFormats[' + stage.value + '][startingScore]'"
                                                            x-model.number="consolationMatchFormats[stage.value].startingScore">
                                                        <template x-for="score in startingScoreOptions" x-bind:key="'cs-'+score">
                                                            <option x-bind:value="score" x-text="score"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 px-2">
                                                    <select class="select-field w-full min-w-[4rem]"
                                                            x-bind:name="'consolationMatchFormats[' + stage.value + '][legsToWinSet]'"
                                                            x-model.number="consolationMatchFormats[stage.value].legsToWinSet">
                                                        <template x-for="n in 15" x-bind:key="'cl-'+n">
                                                            <option x-bind:value="n" x-text="n"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 pl-2">
                                                    <select class="select-field w-full min-w-[4rem]"
                                                            x-bind:name="'consolationMatchFormats[' + stage.value + '][setsToWinMatch]'"
                                                            x-model.number="consolationMatchFormats[stage.value].setsToWinMatch">
                                                        <template x-for="n in 5" x-bind:key="'cset-'+n">
                                                            <option x-bind:value="n" x-text="n"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <template x-for="stage in consolationFormatStages" x-bind:key="'cdl-'+stage.value">
                                <div class="mt-4 pt-3 border-t border-border/40" x-show="consolationMatchFormats[stage.value]">
                                    <p class="text-sm font-medium text-text mb-2" x-text="stage.label"></p>
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <label class="block">
                                            <span class="flex items-center gap-2 text-sm mb-1">
                                                <input type="checkbox"
                                                       :checked="consolationMatchFormats[stage.value].dartLimit != null"
                                                       @change="
                                                           if ($event.target.checked) {
                                                               consolationMatchFormats[stage.value].dartLimit = consolationMatchFormats[stage.value].dartLimit || 45;
                                                           } else {
                                                               consolationMatchFormats[stage.value].dartLimit = null;
                                                               consolationMatchFormats[stage.value].lossThreshold = null;
                                                           }
                                                       ">
                                                Ogranicznik lotek
                                            </span>
                                            <div class="flex items-center gap-2" x-show="consolationMatchFormats[stage.value].dartLimit != null" x-cloak>
                                                <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                        @click="consolationMatchFormats[stage.value].dartLimit = Math.max(15, (consolationMatchFormats[stage.value].dartLimit || 45) - 3)">−</button>
                                                <input class="input-field text-center w-24" type="number" min="15" max="99" step="3"
                                                       x-bind:name="'consolationMatchFormats[' + stage.value + '][dartLimit]'"
                                                       x-model.number="consolationMatchFormats[stage.value].dartLimit">
                                                <button type="button" class="btn btn-secondary !py-1 !px-3"
                                                        @click="consolationMatchFormats[stage.value].dartLimit = Math.min(99, (consolationMatchFormats[stage.value].dartLimit || 45) + 3)">+</button>
                                            </div>
                                            <input type="hidden" x-bind:name="'consolationMatchFormats[' + stage.value + '][dartLimit]'" value=""
                                                   x-show="consolationMatchFormats[stage.value].dartLimit == null">
                                        </label>
                                        <label class="block" x-show="consolationMatchFormats[stage.value].dartLimit != null" x-cloak>
                                            <span class="flex items-center gap-2 text-sm mb-1">
                                                <input type="checkbox"
                                                       :checked="consolationMatchFormats[stage.value].lossThreshold != null"
                                                       @change="consolationMatchFormats[stage.value].lossThreshold = $event.target.checked ? (consolationMatchFormats[stage.value].lossThreshold || 50) : null">
                                                Próg przegranej
                                            </span>
                                            <input class="input-field" type="number" min="2" max="170"
                                                   x-bind:name="'consolationMatchFormats[' + stage.value + '][lossThreshold]'"
                                                   x-show="consolationMatchFormats[stage.value].lossThreshold != null"
                                                   x-model.number="consolationMatchFormats[stage.value].lossThreshold">
                                            <input type="hidden" x-bind:name="'consolationMatchFormats[' + stage.value + '][lossThreshold]'" value=""
                                                   x-show="consolationMatchFormats[stage.value].lossThreshold == null">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <button type="submit" class="btn btn-primary px-8 py-2"
                                x-bind:disabled="participantCount < minPlayers">
                            Start turnieju
                        </button>
                        <p x-show="participantCount < minPlayers" class="text-accent/80 text-xs">
                            Potrzeba jeszcze <span x-text="minPlayers - participantCount"></span> uczestników
                        </p>
                    </form>
            </div>
        @endif

        <div class="flex justify-center mt-8 pt-2">
            <a href="{{ route('tournaments.show', ['tournament' => $tournament->id]) }}" class="btn btn-primary">
                Powrót
            </a>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script>
        function tournamentStartForm() {
            const el = document.getElementById('tournament-start-config');
            const config = el ? JSON.parse(el.textContent) : {};

            return {
                groupsCount: config.groupsCount ?? 2,
                playoffBracketSize: config.playoffBracketSize ?? 4,
                tournamentFormat: config.tournamentFormat ?? 'groups_playoff',
                grandFinalMode: config.grandFinalMode ?? 'reset',
                seBracketSize: config.seBracketSize ?? 4,
                seByeCount: config.seByeCount ?? 0,
                groupCountOptions: config.groupCountOptions ?? [],
                bracketOptionsByGroupCount: config.bracketOptionsByGroupCount ?? {},
                startConfigPreview: config.startConfigPreview ?? {},
                matchFormatStagesByBracket: config.matchFormatStagesByBracket ?? {},
                matchFormatStagesByBracketSe: config.matchFormatStagesByBracketSe ?? {},
                matchFormatStagesByBracketDe: config.matchFormatStagesByBracketDe ?? {},
                startingScoreOptions: config.startingScoreOptions ?? [],
                defaultMatchFormat: config.defaultMatchFormat ?? {},
                defaultMatchFormatsByStage: config.defaultMatchFormatsByStage ?? {},
                hasOrganizationFormatPresets: !!config.hasOrganizationFormatPresets,
                oldMatchFormats: config.oldMatchFormats ?? {},
                oldConsolationMatchFormats: config.oldConsolationMatchFormats ?? {},
                matchFormats: {},
                consolationMatchFormats: {},
                hasConsolationBracket: !!config.hasConsolationBracket,
                minPlayers: config.minPlayers ?? 4,
                minPlayersPerGroup: config.minPlayersPerGroup ?? 3,
                minGroups: 2,
                participantCount: config.participantCount ?? 0,
                init() {
                    this.onParticipantCountChange(this.participantCount, { preserveUserEdits: false });
                    this.primeConsolationMatchFormats();
                    window.addEventListener('tournament-participant-count', (e) => {
                        const next = Number(e.detail?.participantCount);
                        if (!Number.isNaN(next)) {
                            this.onParticipantCountChange(next);
                        }
                    });
                    // Po zamontowaniu <select> Alpine potrafi nadpisać model pierwszą opcją (101/1/1).
                    this.$nextTick(() => {
                        this.syncMatchFormats({ preserveUserEdits: false });
                        this.syncConsolationMatchFormats({ preserveUserEdits: false });
                    });
                },
                onParticipantCountChange(next, { preserveUserEdits = true } = {}) {
                    this.participantCount = next;
                    this.refreshSeBracket();
                    this.refreshGroupAvailability();
                    this.syncMatchFormats({ preserveUserEdits });
                    this.syncConsolationMatchFormats({ preserveUserEdits });
                    this.$nextTick(() => {
                        this.syncGroupsCount();
                        this.syncBracketSelect();
                    });
                },
                refreshSeBracket() {
                    let power = 1;
                    while (power < this.participantCount) {
                        power *= 2;
                    }
                    this.seBracketSize = Math.max(4, power);
                    this.seByeCount = Math.max(0, this.seBracketSize - this.participantCount);
                },
                groupSizesFor(playerCount, groupsCount) {
                    if (groupsCount < 1 || playerCount < 0) {
                        return [];
                    }
                    const baseSize = Math.floor(playerCount / groupsCount);
                    const remainder = playerCount % groupsCount;
                    const sizes = [];
                    for (let i = 0; i < groupsCount; i++) {
                        sizes.push(baseSize + (i < remainder ? 1 : 0));
                    }
                    return sizes;
                },
                distributeAdvances(groupSizes, bracketSize) {
                    const groupsCount = groupSizes.length;
                    const playerCount = groupSizes.reduce((sum, size) => sum + size, 0);
                    if (groupsCount < 1 || playerCount < 1) {
                        return null;
                    }
                    if (bracketSize < groupsCount || bracketSize > playerCount) {
                        return null;
                    }
                    const advances = [];
                    const remainders = [];
                    for (let i = 0; i < groupsCount; i++) {
                        const exact = (bracketSize * groupSizes[i]) / playerCount;
                        advances[i] = Math.floor(exact);
                        remainders[i] = exact - advances[i];
                    }
                    const toAdd = bracketSize - advances.reduce((sum, n) => sum + n, 0);
                    if (toAdd < 0) {
                        return null;
                    }
                    const indices = Array.from({ length: groupsCount }, (_, i) => i);
                    indices.sort((a, b) => remainders[b] - remainders[a] || a - b);
                    for (let k = 0; k < toAdd; k++) {
                        advances[indices[k]] += 1;
                    }
                    for (let i = 0; i < groupsCount; i++) {
                        if (advances[i] < 1 || advances[i] > groupSizes[i]) {
                            return null;
                        }
                    }
                    if (advances.reduce((sum, n) => sum + n, 0) !== bracketSize) {
                        return null;
                    }
                    return advances;
                },
                bracketOptionLabel(bracketSize) {
                    const stage = ({
                        128: '1/64 finału',
                        64: '1/32 finału',
                        32: '1/16 finału',
                        16: '1/8 finału',
                        8: '1/4 finału',
                        4: '1/2 finału',
                    })[bracketSize] ?? `${bracketSize} graczy`;
                    return `${stage} — ${bracketSize} graczy awansujących`;
                },
                rebuildStartConfig() {
                    const playerCount = this.participantCount;
                    const groupCounts = this.allowedGroupCountsForPlayers(playerCount);
                    this.groupCountOptions = groupCounts;
                    const preview = {};
                    const bracketMap = {};
                    for (const groupsCount of groupCounts) {
                        const sizes = this.groupSizesFor(playerCount, groupsCount);
                        preview[groupsCount] = {};
                        const options = [];
                        for (let bracketSize = 4; bracketSize <= 128; bracketSize *= 2) {
                            if (bracketSize < groupsCount || bracketSize > playerCount) {
                                continue;
                            }
                            const advances = this.distributeAdvances(sizes, bracketSize);
                            if (!advances) {
                                continue;
                            }
                            preview[groupsCount][bracketSize] = {
                                groupSizes: sizes,
                                advances,
                            };
                            options.push({
                                value: bracketSize,
                                label: this.bracketOptionLabel(bracketSize),
                            });
                        }
                        bracketMap[groupsCount] = options;
                    }
                    this.startConfigPreview = preview;
                    this.bracketOptionsByGroupCount = bracketMap;
                },
                allowedGroupCountsForPlayers(playerCount) {
                    const minPer = this.minPlayersPerGroup;
                    const minGroups = this.minGroups;
                    const maxGroups = Math.floor(Math.max(0, playerCount) / minPer);
                    if (maxGroups < minGroups) {
                        return [];
                    }
                    const options = [];
                    for (let groups = minGroups; groups <= maxGroups; groups++) {
                        if (groups <= playerCount && Math.floor(playerCount / groups) >= minPer) {
                            options.push(groups);
                        }
                    }
                    return options;
                },
                refreshGroupAvailability() {
                    this.rebuildStartConfig();
                    if (
                        this.participantCount >= this.minPlayers
                        && this.tournamentFormat === 'groups_playoff'
                        && !this.canUseGroupsPlayoff
                    ) {
                        this.tournamentFormat = 'single_elimination';
                    }
                    this.syncGroupsCount();
                    this.syncBracketSelect();
                },
                get minPlayersForGroups() {
                    return this.minPlayersPerGroup * this.minGroups;
                },
                get canUseGroupsPlayoff() {
                    return this.groupCountOptions.length > 0;
                },
                get groupsPlayoffDisabledReason() {
                    if (this.canUseGroupsPlayoff) {
                        return '';
                    }
                    return `Za mało zawodników do utworzenia grup — potrzeba min. ${this.minPlayersForGroups} graczy `
                        + `(${this.minGroups} grupy po co najmniej ${this.minPlayersPerGroup}). `
                        + `Obecnie: ${this.participantCount}.`;
                },
                get bracketOptions() {
                    const opts = this.bracketOptionsByGroupCount[this.groupsCount]
                        ?? this.bracketOptionsByGroupCount[String(this.groupsCount)]
                        ?? [];
                    return opts.length
                        ? opts
                        : [{ value: 4, label: '1/2 finału — 4 graczy awansujących' }];
                },
                get preview() {
                    const byGroup = this.startConfigPreview[this.groupsCount]
                        ?? this.startConfigPreview[String(this.groupsCount)]
                        ?? {};
                    return byGroup[this.playoffBracketSize]
                        ?? byGroup[String(this.playoffBracketSize)]
                        ?? null;
                },
                get remainingAfterAdvance() {
                    return Math.max(0, this.participantCount - Number(this.playoffBracketSize || 0));
                },
                get canEnableConsolation() {
                    return this.tournamentFormat === 'groups_playoff' && this.remainingAfterAdvance >= 2;
                },
                get consolationBracketSize() {
                    const n = this.remainingAfterAdvance;
                    if (n < 2) {
                        return 0;
                    }
                    let power = 1;
                    while (power < n) {
                        power *= 2;
                    }
                    return power;
                },
                get consolationByeCount() {
                    return Math.max(0, this.consolationBracketSize - this.remainingAfterAdvance);
                },
                get consolationFormatStages() {
                    if (!this.hasConsolationBracket || !this.canEnableConsolation) {
                        return [];
                    }
                    return this.matchFormatStagesByBracketSe[this.consolationBracketSize]
                        ?? this.matchFormatStagesByBracketSe[String(this.consolationBracketSize)]
                        ?? [];
                },
                get activeFormatStages() {
                    if (this.tournamentFormat === 'double_elimination') {
                        return this.matchFormatStagesByBracketDe[this.seBracketSize]
                            ?? this.matchFormatStagesByBracketDe[String(this.seBracketSize)]
                            ?? [];
                    }
                    if (this.tournamentFormat === 'single_elimination') {
                        return this.matchFormatStagesByBracketSe[this.seBracketSize]
                            ?? this.matchFormatStagesByBracketSe[String(this.seBracketSize)]
                            ?? [];
                    }
                    return this.matchFormatStagesByBracket[this.playoffBracketSize]
                        ?? this.matchFormatStagesByBracket[String(this.playoffBracketSize)]
                        ?? [];
                },
                syncMatchFormats({ preserveUserEdits = true } = {}) {
                    const stages = this.activeFormatStages;
                    const next = {};
                    for (const stage of stages) {
                        next[stage.value] = {
                            ...this.defaultMatchFormat,
                            ...(this.defaultMatchFormatsByStage[stage.value] ?? {}),
                            ...(this.oldMatchFormats[stage.value] ?? {}),
                            ...(preserveUserEdits ? (this.matchFormats[stage.value] ?? {}) : {}),
                        };
                    }
                    this.matchFormats = next;
                },
                primeConsolationMatchFormats() {
                    const next = { ...this.consolationMatchFormats };
                    for (const size of [2, 4, 8, 16, 32, 64, 128]) {
                        const stages = this.matchFormatStagesByBracketSe[size]
                            ?? this.matchFormatStagesByBracketSe[String(size)]
                            ?? [];
                        for (const stage of stages) {
                            next[stage.value] = {
                                ...this.defaultMatchFormat,
                                ...(this.defaultMatchFormatsByStage[stage.value] ?? {}),
                                ...(this.oldConsolationMatchFormats[stage.value] ?? {}),
                                ...(this.consolationMatchFormats[stage.value] ?? {}),
                            };
                        }
                    }
                    this.consolationMatchFormats = next;
                },
                syncConsolationMatchFormats({ preserveUserEdits = true } = {}) {
                    if (!this.canEnableConsolation) {
                        this.hasConsolationBracket = false;
                    }
                    const stages = this.consolationFormatStages;
                    const next = { ...this.consolationMatchFormats };
                    for (const stage of stages) {
                        next[stage.value] = {
                            ...this.defaultMatchFormat,
                            ...(this.defaultMatchFormatsByStage[stage.value] ?? {}),
                            ...(this.oldConsolationMatchFormats[stage.value] ?? {}),
                            ...(preserveUserEdits ? (this.consolationMatchFormats[stage.value] ?? {}) : {}),
                        };
                    }
                    this.consolationMatchFormats = next;
                },
                syncGroupsCount() {
                    const sel = this.$refs.groupsSelect;
                    if (sel) {
                        sel.replaceChildren();
                        for (const option of this.groupCountOptions) {
                            const elOpt = document.createElement('option');
                            elOpt.value = String(option);
                            elOpt.textContent = String(option);
                            sel.appendChild(elOpt);
                        }
                    }
                    if (!this.groupCountOptions.includes(this.groupsCount)) {
                        this.groupsCount = this.groupCountOptions[0] ?? 2;
                    }
                    if (sel) {
                        sel.value = String(this.groupsCount);
                    }
                },
                syncBracketSelect() {
                    const opts = this.bracketOptions;
                    const sel = this.$refs.bracketSelect;
                    if (!sel) {
                        return;
                    }
                    sel.replaceChildren();
                    for (const option of opts) {
                        const elOpt = document.createElement('option');
                        elOpt.value = String(option.value);
                        elOpt.textContent = option.label;
                        sel.appendChild(elOpt);
                    }
                    if (!opts.some((option) => Number(option.value) === Number(this.playoffBracketSize))) {
                        this.playoffBracketSize = opts[0]?.value ?? 4;
                    }
                    sel.value = String(this.playoffBracketSize);
                },
                onFormatChange() {
                    this.syncMatchFormats({ preserveUserEdits: false });
                    this.syncConsolationMatchFormats({ preserveUserEdits: false });
                },
                onGroupsChange() {
                    this.syncBracketSelect();
                    this.syncMatchFormats();
                    this.syncConsolationMatchFormats();
                },
                onBracketChange() {
                    this.syncMatchFormats();
                    this.syncConsolationMatchFormats();
                },
                onConsolationToggle() {
                    this.syncConsolationMatchFormats();
                },
            };
        }
    </script>
@endsection
