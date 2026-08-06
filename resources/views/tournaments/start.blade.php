@extends('layouts.app')

@section('title', 'Start turnieju')

@section('content')
    <div class="container mx-auto py-6 sm:py-8 max-w-5xl min-w-0">

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

        {{-- Strefa 1: Uczestnicy turnieju (sticky + przewijana lista) --}}
        <div
            class="mb-8 card border-2 border-success/40 lg:sticky lg:top-4 lg:z-20"
            @if($canManageParticipants)
                x-data
                x-init="$store.tournamentStartLive.init(@js([
                    'participants' => $participantsLive ?? [],
                    'participantCount' => $participantCount,
                    'minPlayers' => $minPlayers,
                    'canManage' => true,
                    'csrfToken' => csrf_token(),
                    'inviteUrl' => route('tournaments.invitations.send', $tournament->id),
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
            {{-- Strefa 2: Dodaj uczestników --}}
            <div class="mb-8 card overflow-hidden">
                <div class="border-b border-border px-6 pt-6 pb-4">
                    <h2 class="text-xl font-semibold text-accent mb-4">Dodaj uczestników</h2>

                    {{-- QR zgłoszenia --}}
                    @if(!empty($joinCode) && !empty($joinUrl))
                        <div
                            class="mb-6 p-4 rounded-lg border border-border bg-bg/40"
                            x-data="tournamentJoinRequestsLive(@js([
                                'channel' => 'tournament.'.$tournament->id,
                                'snapshotUrl' => route('tournaments.join-requests-live', $tournament->id),
                                'csrfToken' => csrf_token(),
                                'inviteUrl' => route('tournaments.invitations.send', $tournament->id),
                                'invitationPipeline' => $invitationPipelineLive ?? [],
                                'initialRequests' => $pendingJoinRequestsLive ?? [],
                                'initialParticipants' => $participantsLive ?? [],
                                'participantCount' => $participantCount,
                                'minPlayers' => $minPlayers,
                                'canManage' => true,
                                'reverb' => \App\Support\Broadcasting\ReverbClientConfig::forWeb(),
                            ]))"
                            x-init="init()"
                        >
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
                        <form action="{{ route('tournaments.participants.guests.create', $tournament->id) }}" method="POST" class="flex flex-wrap items-center gap-4">
                            @csrf
                            <input type="text"
                                   name="name"
                                   placeholder="Imię gościa..."
                                   value="{{ old('name') }}"
                                   maxlength="20"
                                   class="input-field flex-1 min-w-[200px]"
                                   required>
                            <button type="submit" class="btn btn-primary">Dodaj gościa</button>
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
                    x-data="{ activeTab: @json($addTab) }"
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
                                Goście ligi / sezonu
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        {{-- Stały skład --}}
                        <div x-show="activeTab === 'registered'">
                            <div x-data="{ selectedRegulars: [] }">
                                <h3 class="text-accent font-semibold mb-2">Stały skład ligi / sezonu</h3>
                                <p class="text-text-secondary text-sm mb-3">Zaznacz bywalców i wyślij masowe zaproszenia.</p>

                                @if($regulars->isEmpty())
                                    <p class="text-text-secondary text-sm">
                                        Brak powiązanych użytkowników.
                                        <a href="{{ route('seasons.relatedUsers', $tournament->season->id) }}" class="text-accent underline">Sezon</a>
                                        ·
                                        <a href="{{ route('leagues.relatedUsers', $tournament->season->league->id) }}" class="text-accent underline">Liga</a>
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
                            <p class="text-text-secondary text-sm mb-4">Dodaj gości z puli ligi/sezonu do tego turnieju.</p>

                            @if($relatedGuests->isEmpty())
                                <p class="text-text-secondary text-sm">
                                    Brak powiązanych gości.
                                    <a href="{{ route('seasons.guests', $tournament->season->id) }}" class="text-accent underline">Sezon</a>
                                    ·
                                    <a href="{{ route('leagues.guests', $tournament->season->league->id) }}" class="text-accent underline">Liga</a>
                                </p>
                            @else
                                <div class="flex flex-wrap gap-3">
                                    @foreach($relatedGuests as $guest)
                                        <div class="flex flex-col items-center bg-bg rounded-lg p-4 min-w-[110px]">
                                            <span class="text-text-secondary text-sm text-center mb-2">{{ $guest['name'] }}</span>
                                            @if($guest['inTournament'])
                                                <span class="text-xs text-accent font-semibold">W turnieju</span>
                                            @else
                                                <form action="{{ route('tournaments.participants.guests.add', $tournament->id) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="player_id" value="{{ $guest['playerId'] }}">
                                                    <button type="submit" class="btn-mini">Dodaj</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
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
                    'tabletsCount' => (int) old('tabletsCount', $defaultGroupsCount ?: 1),
                    'tournamentFormat' => $defaultTournamentFormat,
                    'grandFinalMode' => (string) old('grandFinalMode', 'reset'),
                    'seBracketSize' => $seBracketSize,
                    'seByeCount' => $seByeCount,
                    'groupCountOptions' => $groupCountOptions,
                    'bracketOptionsByGroupCount' => $bracketOptionsByGroupCount,
                    'startConfigPreview' => $startConfigPreview,
                    'matchFormatStagesByBracket' => $matchFormatStagesByBracket,
                    'matchFormatStagesByBracketSe' => $matchFormatStagesByBracketSe,
                    'startingScoreOptions' => $startingScoreOptions,
                    'defaultMatchFormat' => $defaultMatchFormat,
                    'defaultMatchFormatsByStage' => $defaultMatchFormatsByStage,
                    'hasLeagueFormatPresets' => $hasLeagueFormatPresets,
                    'oldMatchFormats' => $oldMatchFormats,
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

                @if($participants->isEmpty())
                    <p class="text-text-secondary text-sm">Dodaj uczestników powyżej, aby wystartować turniej.</p>
                @elseif($participantCount < $minPlayers)
                    <p class="text-text-secondary text-sm">
                        Potrzeba co najmniej {{ $minPlayers }} uczestników (obecnie {{ $participantCount }}).
                    </p>
                @else
                    <form action="{{ route('tournaments.run', $tournament->id) }}" method="POST" class="flex flex-col items-center gap-4">
                        @csrf

                        <div class="w-full max-w-2xl flex flex-col gap-3">
                            <p class="text-accent font-semibold">Rodzaj turnieju</p>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="radio" name="tournamentFormat" value="groups_playoff"
                                       class="mt-1"
                                       x-model="tournamentFormat"
                                       @change="onFormatChange()"
                                       @disabled($groupCountOptions === [])>
                                <span>
                                    <span class="font-medium text-text-primary">Grupy + drabinka</span>
                                    <span class="block text-text-secondary/70 text-xs mt-0.5">
                                        Faza grupowa, potem playoff
                                        @if($groupCountOptions === [])
                                            — niedostępne przy {{ $participantCount }} graczach (min. {{ $minPlayersPerGroup * 2 }} do grup)
                                        @endif
                                    </span>
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

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 w-full max-w-2xl"
                             x-show="tournamentFormat === 'groups_playoff'"
                             x-cloak>
                            <div class="flex flex-col">
                                <label for="groupsCount" class="text-accent font-semibold mb-2">Liczba grup</label>
                                <select id="groupsCount" name="groupsCount" class="select-field"
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
                            <div class="flex flex-col">
                                <label for="tabletsCountGroups" class="text-accent font-semibold mb-2">Liczba tabletów</label>
                                <input id="tabletsCountGroups" type="number" name="tabletsCount" min="1"
                                       class="select-field" x-model.number="tabletsCount"
                                       x-bind:disabled="tournamentFormat !== 'groups_playoff'">
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
                            <div class="flex flex-col gap-3">
                                <div class="flex flex-col">
                                    <label for="tabletsCountSe" class="text-accent font-semibold mb-2">Liczba tabletów</label>
                                    <input id="tabletsCountSe" type="number" min="1"
                                           class="select-field" x-model.number="tabletsCount"
                                           x-bind:name="(tournamentFormat === 'single_elimination' || tournamentFormat === 'double_elimination') ? 'tabletsCount' : null">
                                </div>
                                <div class="flex flex-col" x-show="tournamentFormat === 'double_elimination'" x-cloak>
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

                        <div class="w-full max-w-3xl rounded-lg border border-border bg-bg/40 p-4"
                             x-show="activeFormatStages.length"
                             x-cloak>
                            <p class="text-accent font-semibold text-sm mb-1">Format gry per etap</p>
                            <p class="text-text-secondary/70 text-xs mb-4"
                               x-text="hasLeagueFormatPresets
                                   ? 'Domyślne z presetów ligi — możesz nadpisać przed startem. Tablet odczyta format z meczu.'
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
                        </div>

                        <button type="submit" class="btn btn-primary px-8 py-2"
                                x-bind:disabled="participantCount < minPlayers">
                            Start turnieju
                        </button>
                        <p x-show="participantCount < minPlayers" class="text-accent/80 text-xs">
                            Potrzeba jeszcze <span x-text="minPlayers - participantCount"></span> uczestników
                        </p>
                    </form>
                @endif
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
                tabletsCount: config.tabletsCount ?? 2,
                tournamentFormat: config.tournamentFormat ?? 'groups_playoff',
                grandFinalMode: config.grandFinalMode ?? 'reset',
                seBracketSize: config.seBracketSize ?? 4,
                seByeCount: config.seByeCount ?? 0,
                groupCountOptions: config.groupCountOptions ?? [],
                bracketOptionsByGroupCount: config.bracketOptionsByGroupCount ?? {},
                startConfigPreview: config.startConfigPreview ?? {},
                matchFormatStagesByBracket: config.matchFormatStagesByBracket ?? {},
                matchFormatStagesByBracketSe: config.matchFormatStagesByBracketSe ?? {},
                startingScoreOptions: config.startingScoreOptions ?? [],
                defaultMatchFormat: config.defaultMatchFormat ?? {},
                defaultMatchFormatsByStage: config.defaultMatchFormatsByStage ?? {},
                hasLeagueFormatPresets: !!config.hasLeagueFormatPresets,
                oldMatchFormats: config.oldMatchFormats ?? {},
                matchFormats: {},
                minPlayers: config.minPlayers ?? 4,
                minPlayersPerGroup: config.minPlayersPerGroup ?? 3,
                participantCount: config.participantCount ?? 0,
                init() {
                    if (this.tournamentFormat === 'groups_playoff' && this.groupCountOptions.length === 0) {
                        this.tournamentFormat = 'single_elimination';
                    }
                    window.addEventListener('tournament-participant-count', (e) => {
                        const next = Number(e.detail?.participantCount);
                        if (!Number.isNaN(next)) {
                            this.participantCount = next;
                            this.refreshSeBracket();
                        }
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
                get activeFormatStages() {
                    if (this.tournamentFormat === 'single_elimination' || this.tournamentFormat === 'double_elimination') {
                        return this.matchFormatStagesByBracketSe[this.seBracketSize]
                            ?? this.matchFormatStagesByBracketSe[String(this.seBracketSize)]
                            ?? [];
                    }
                    return this.matchFormatStagesByBracket[this.playoffBracketSize]
                        ?? this.matchFormatStagesByBracket[String(this.playoffBracketSize)]
                        ?? [];
                },
                syncMatchFormats() {
                    const stages = this.activeFormatStages;
                    const next = {};
                    for (const stage of stages) {
                        next[stage.value] = {
                            ...this.defaultMatchFormat,
                            ...(this.defaultMatchFormatsByStage[stage.value] ?? {}),
                            ...(this.oldMatchFormats[stage.value] ?? {}),
                            ...(this.matchFormats[stage.value] ?? {}),
                        };
                    }
                    this.matchFormats = next;
                },
                syncGroupsCount() {
                    if (!this.groupCountOptions.includes(this.groupsCount)) {
                        this.groupsCount = this.groupCountOptions[0] ?? 2;
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
                    this.syncMatchFormats();
                },
                onGroupsChange() {
                    this.syncBracketSelect();
                    this.syncMatchFormats();
                },
                onBracketChange() {
                    this.syncMatchFormats();
                },
            };
        }
    </script>
@endsection
