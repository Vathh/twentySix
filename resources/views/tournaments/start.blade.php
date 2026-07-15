@extends('layouts.app')

@section('title', 'Start turnieju')

@section('content')
    <div class="container mx-auto py-8 max-w-5xl">

        <h1 class="text-2xl font-bold text-light-green mb-4">
            Start turnieju: {{ $tournament->name }}
        </h1>

        <x-errors/>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-900/50 border border-green-600 rounded text-light-green">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-900/50 border border-red-600 rounded text-light-white">{{ session('error') }}</div>
        @endif

        @if(!$canManageParticipants)
            <div class="mb-6 p-4 rounded-lg bg-lighter-bg border border-light-orange text-light-white">
                Turniej już wystartował — zaproszenia i zmiany uczestników są zablokowane.
            </div>
        @endif

        {{-- Strefa 1: Uczestnicy turnieju (sticky + przewijana lista) --}}
        <div class="mb-8 bg-lighter-bg p-6 rounded-lg shadow border-2 border-light-green/40 lg:sticky lg:top-4 lg:z-20">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                <h2 class="text-xl font-semibold text-light-green">Uczestnicy turnieju</h2>
                <span @class([
                    'text-sm font-semibold px-3 py-1 rounded-full shrink-0',
                    'bg-light-green/20 text-light-green' => $participantCount >= $minPlayers,
                    'bg-light-orange/20 text-light-orange' => $participantCount < $minPlayers,
                ])>
                    {{ $participantCount }} / min. {{ $minPlayers }}
                </span>
            </div>
            <p class="text-light-white text-sm mb-3">
                W turnieju grają wszyscy z tej listy — zaakceptowane zaproszenia oraz goście dodani do turnieju.
            </p>

            @if($participants->isEmpty())
                <p class="text-light-white/80 italic text-sm">
                    Brak uczestników. Użyj sekcji poniżej, aby wysłać zaproszenia lub dodać gości.
                </p>
            @else
                @if($participantCount > 12)
                    <p class="text-light-orange/70 text-xs mb-2">Duża lista — przewiń, aby zobaczyć wszystkich.</p>
                @endif
                <div class="max-h-36 sm:max-h-44 overflow-y-auto overflow-x-hidden pr-1 -mr-1">
                    <div class="flex flex-wrap gap-2">
                        @foreach($participants as $participant)
                            <div class="flex items-center gap-1 bg-dark-bg text-light-white pl-3 pr-1 py-1.5 rounded-lg shadow text-sm">
                                <span>
                                    {{ $participant['name'] }}
                                    @if($participant['kind'] === 'guest')
                                        <span class="text-xs text-light-orange ml-1">gość</span>
                                    @endif
                                </span>
                                @if($canManageParticipants)
                                    @if($participant['kind'] === 'user')
                                        <form action="{{ route('tournaments.invitations.remove', [$tournament->id, $participant['invitationId']]) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-red-400 hover:text-red-300 font-bold w-7 h-7 rounded hover:bg-red-900/30" title="Usuń z turnieju">×</button>
                                        </form>
                                    @else
                                        <form action="{{ route('tournaments.participants.guests.remove', $tournament->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="player_id" value="{{ $participant['playerId'] }}">
                                            <button type="submit" class="text-red-400 hover:text-red-300 font-bold w-7 h-7 rounded hover:bg-red-900/30" title="Usuń z turnieju">×</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if($canManageParticipants)
            {{-- Strefa 2: Dodaj uczestników --}}
            <div class="mb-8 bg-lighter-bg rounded-lg shadow overflow-hidden">
                <div class="border-b border-dark-bg px-6 pt-6 pb-4">
                    <h2 class="text-xl font-semibold text-light-orange mb-4">Dodaj uczestników</h2>

                    {{-- Wyszukiwarka — zawsze widoczna (niezależnie od zakładek) --}}
                    <div class="mb-2">
                        <h3 class="text-light-green font-semibold mb-2">Wyszukaj użytkownika</h3>
                        <p class="text-light-white text-sm mb-3">Wpisz imię lub fragment nazwy gracza i wyślij zaproszenie.</p>
                        <form action="{{ route('tournaments.start', $tournament->id) }}" method="GET" class="flex flex-wrap items-center gap-4">
                            <input type="text" name="search" placeholder="Min. 2 znaki..."
                                   value="{{ request('search') }}" class="input-orange flex-1 min-w-[200px]">
                            <button type="submit" class="btn btn-primary">Szukaj</button>
                        </form>

                        @if(request('search') && $searchUsers->isEmpty())
                            <p class="text-light-white mt-3 text-sm">Brak wyników.</p>
                        @elseif($searchUsers->isNotEmpty())
                            <div class="flex flex-wrap gap-2 mt-3">
                                @foreach($searchUsers as $user)
                                    <div class="flex items-center gap-2 bg-dark-bg rounded-lg px-3 py-2">
                                        <span class="text-light-white text-sm">{{ $user->player->name }}</span>
                                        <form action="{{ route('tournaments.invitations.send', $tournament->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                                            <button type="submit" class="btn-mini">Zaproś</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Goście bez konta — zawsze widoczne --}}
                    <div class="mt-6 pt-6 border-t border-dark-bg">
                        <h3 class="text-light-green font-semibold mb-2">Dodaj gościa (bez konta)</h3>
                        <p class="text-light-white text-sm mb-3">
                            Gracz niezarejestrowany w aplikacji — trafi od razu na listę uczestników turnieju.
                        </p>
                        <form action="{{ route('tournaments.participants.guests.create', $tournament->id) }}" method="POST" class="flex flex-wrap items-center gap-4">
                            @csrf
                            <input type="text"
                                   name="name"
                                   placeholder="Imię gościa..."
                                   value="{{ old('name') }}"
                                   maxlength="20"
                                   class="input-orange flex-1 min-w-[200px]"
                                   required>
                            <button type="submit" class="btn btn-primary">Dodaj gościa</button>
                        </form>
                    </div>

                    {{-- Oczekujące zaproszenia --}}
                    @if($invitationPipeline->isNotEmpty())
                        <div class="mt-6">
                            <h3 class="text-light-green font-semibold mb-2">Zaproszenia w toku</h3>
                            <div class="overflow-x-auto rounded-lg border border-dark-bg">
                                <table class="w-full text-light-white text-sm">
                                    <thead class="bg-dark-bg">
                                        <tr class="text-light-green">
                                            <th class="text-left py-2 px-3">Zawodnik</th>
                                            <th class="text-left py-2 px-3">Status</th>
                                            <th class="text-left py-2 px-3">Akcja</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($invitationPipeline as $invitation)
                                            <tr class="border-t border-dark-bg/50">
                                                <td class="py-2 px-3">{{ $invitation->userPlayer?->name ?? '—' }}</td>
                                                <td class="py-2 px-3">{{ $invitation->status->label() }}</td>
                                                <td class="py-2 px-3">
                                                    @if($invitation->status === \App\Enums\TournamentInvitationStatus::PENDING)
                                                        <form action="{{ route('tournaments.invitations.cancel', [$tournament->id, $invitation->id]) }}" method="POST" class="inline">
                                                            @csrf
                                                            <button type="submit" class="btn-mini">Anuluj</button>
                                                        </form>
                                                    @elseif($invitation->status->canReinvite())
                                                        <form action="{{ route('tournaments.invitations.send', $tournament->id) }}" method="POST" class="inline">
                                                            @csrf
                                                            <input type="hidden" name="user_id" value="{{ $invitation->userId }}">
                                                            <button type="submit" class="btn-mini">Zaproś ponownie</button>
                                                        </form>
                                                    @else
                                                        <span class="text-light-orange/60">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                @if($tournament->season)
                <div
                    x-data="{ activeTab: @json($addTab) }"
                >
                    <div class="border-b border-dark-bg px-6">
                        <div class="flex gap-1">
                            <button
                                type="button"
                                x-on:click="activeTab = 'registered'"
                                x-bind:class="activeTab === 'registered'
                                    ? 'bg-light-orange text-dark-bg'
                                    : 'bg-dark-bg text-light-white hover:bg-dark-bg/80'"
                                class="px-5 py-2 rounded-t-lg font-semibold text-sm transition"
                            >
                                Stały skład
                            </button>
                            <button
                                type="button"
                                x-on:click="activeTab = 'guests'"
                                x-bind:class="activeTab === 'guests'
                                    ? 'bg-light-orange text-dark-bg'
                                    : 'bg-dark-bg text-light-white hover:bg-dark-bg/80'"
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
                                <h3 class="text-light-green font-semibold mb-2">Stały skład ligi / sezonu</h3>
                                <p class="text-light-white text-sm mb-3">Zaznacz bywalców i wyślij masowe zaproszenia.</p>

                                @if($regulars->isEmpty())
                                    <p class="text-light-white text-sm">
                                        Brak powiązanych użytkowników.
                                        <a href="{{ route('seasons.relatedUsers', $tournament->season->id) }}" class="text-light-green underline">Sezon</a>
                                        ·
                                        <a href="{{ route('leagues.relatedUsers', $tournament->season->league->id) }}" class="text-light-green underline">Liga</a>
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
                                                        ? 'bg-light-green text-dark-bg'
                                                        : 'bg-dark-bg text-light-white'"
                                                    class="cursor-pointer px-3 py-2 rounded-lg text-sm transition shadow select-none"
                                                >
                                                    {{ $regular['name'] }}
                                                </div>
                                            @else
                                                <div class="px-3 py-2 rounded-lg text-sm bg-dark-bg/50 text-light-white/60 border border-light-orange/20">
                                                    {{ $regular['name'] }}
                                                    <span class="text-xs text-light-orange block">{{ $regular['invitationStatus']?->label() }}</span>
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
                            <h3 class="text-light-green font-semibold mb-2">Powiązani goście</h3>
                            <p class="text-light-white text-sm mb-4">Dodaj gości z puli ligi/sezonu do tego turnieju.</p>

                            @if($relatedGuests->isEmpty())
                                <p class="text-light-white text-sm">
                                    Brak powiązanych gości.
                                    <a href="{{ route('seasons.guests', $tournament->season->id) }}" class="text-light-green underline">Sezon</a>
                                    ·
                                    <a href="{{ route('leagues.guests', $tournament->season->league->id) }}" class="text-light-green underline">Liga</a>
                                </p>
                            @else
                                <div class="flex flex-wrap gap-3">
                                    @foreach($relatedGuests as $guest)
                                        <div class="flex flex-col items-center bg-dark-bg rounded-lg p-4 min-w-[110px]">
                                            <span class="text-light-white text-sm text-center mb-2">{{ $guest['name'] }}</span>
                                            @if($guest['inTournament'])
                                                <span class="text-xs text-light-green font-semibold">W turnieju</span>
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
            <div
                x-data="{
                    groupsCount: {{ $defaultGroupsCount }},
                    playoffBracketSize: {{ $defaultPlayoffBracketSize }},
                    tabletsCount: {{ (int) old('tabletsCount', $defaultGroupsCount) }},
                    groupCountOptions: @json($groupCountOptions),
                    bracketOptionsByGroupCount: @json($bracketOptionsByGroupCount),
                    startConfigPreview: @json($startConfigPreview),
                    matchFormatStagesByBracket: @json($matchFormatStagesByBracket),
                    startingScoreOptions: @json($startingScoreOptions),
                    defaultMatchFormat: @json($defaultMatchFormat),
                    oldMatchFormats: @json($oldMatchFormats),
                    matchFormats: {},
                    minPlayers: {{ $minPlayers }},
                    minPlayersPerGroup: {{ $minPlayersPerGroup }},
                    participantCount: {{ $participantCount }},
                    get bracketOptions() {
                        const opts = this.bracketOptionsByGroupCount[this.groupsCount]
                            ?? this.bracketOptionsByGroupCount[String(this.groupsCount)]
                            ?? [];
                        return opts.length ? opts : [{ value: 4, label: '1/2 finału — 4 graczy awansujących' }];
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
                        sel.innerHTML = opts
                            .map(function (option) {
                                return ['<option value=', option.value, '>', option.label, '</option>'].join('');
                            })
                            .join('');
                        if (!opts.some(function (option) { return Number(option.value) === Number(this.playoffBracketSize); }.bind(this))) {
                            this.playoffBracketSize = opts[0]?.value ?? 4;
                        }
                        sel.value = String(this.playoffBracketSize);
                    },
                    onGroupsChange() {
                        this.syncBracketSelect();
                        this.syncMatchFormats();
                    },
                    onBracketChange() {
                        this.syncMatchFormats();
                    }
                }"
                x-init="syncGroupsCount(); syncBracketSelect(); syncMatchFormats()"
                class="mb-8 bg-lighter-bg p-6 rounded-lg shadow"
            >
                <h2 class="text-xl font-semibold text-light-orange mb-4">Start turnieju</h2>

                @if($participants->isEmpty())
                    <p class="text-light-white text-sm">Dodaj uczestników powyżej, aby wystartować turniej.</p>
                @elseif($groupCountOptions === [])
                    <p class="text-light-white text-sm">
                        Przy {{ $participantCount }} uczestnikach nie da się utworzyć grup
                        (min. {{ $minPlayersPerGroup }} zawodników w grupie, min. 2 grupy —
                        potrzeba co najmniej {{ $minPlayersPerGroup * 2 }} graczy).
                    </p>
                @else
                    <form action="{{ route('tournaments.run', $tournament->id) }}" method="POST" class="flex flex-col items-center gap-4">
                        @csrf

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 w-full max-w-2xl">
                            <div class="flex flex-col">
                                <label for="groupsCount" class="text-light-green font-semibold mb-2">Liczba grup</label>
                                <select id="groupsCount" name="groupsCount" class="select-green"
                                        x-model.number="groupsCount" x-on:change="onGroupsChange()">
                                    @foreach ($groupCountOptions as $option)
                                        <option value="{{ $option }}" @selected($defaultGroupsCount === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                <p class="text-light-white/70 text-xs mt-2">
                                    Min. {{ $minPlayersPerGroup }} zawodników w grupie
                                </p>
                            </div>
                            <div class="flex flex-col">
                                <label for="playoffBracketSize" class="text-light-green font-semibold mb-2">Etap drabinki</label>
                                <select id="playoffBracketSize"
                                        x-ref="bracketSelect"
                                        name="playoffBracketSize"
                                        class="select-green"
                                        x-model.number="playoffBracketSize"
                                        x-on:change="onBracketChange()">
                                    @foreach ($defaultBracketOptions as $option)
                                        <option value="{{ $option['value'] }}" @selected($defaultPlayoffBracketSize === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                <p class="text-light-white/70 text-xs mt-2">
                                    Od tego etapu zaczyna się faza pucharowa
                                </p>
                            </div>
                            <div class="flex flex-col">
                                <label for="tabletsCount" class="text-light-green font-semibold mb-2">Liczba tabletów</label>
                                <input id="tabletsCount" type="number" name="tabletsCount" min="1"
                                       class="select-green" x-model.number="tabletsCount">
                            </div>
                        </div>

                        <div class="w-full max-w-2xl rounded-lg border border-dark-bg bg-dark-bg/40 p-4"
                             x-show="preview"
                             x-cloak>
                            <p class="text-light-green font-semibold text-sm mb-3">Podgląd podziału</p>
                            <template x-for="(advanceCount, index) in (preview?.advances ?? [])" x-bind:key="index">
                                <div class="flex flex-wrap items-baseline justify-between gap-2 py-1 text-sm text-light-white border-b border-dark-bg/60 last:border-0">
                                    <span>
                                        Grupa <span x-text="index + 1"></span>:
                                        <span x-text="preview.groupSizes[index]"></span> graczy
                                    </span>
                                    <span class="text-light-orange">
                                        → <span x-text="advanceCount"></span> awansujących
                                    </span>
                                </div>
                            </template>
                        </div>

                        <p class="text-light-orange text-sm">
                            Drabinka playoff: <span x-text="playoffBracketSize"></span> graczy awansujących
                        </p>

                        <div class="w-full max-w-3xl rounded-lg border border-dark-bg bg-dark-bg/40 p-4"
                             x-show="activeFormatStages.length"
                             x-cloak>
                            <p class="text-light-green font-semibold text-sm mb-1">Format gry per etap</p>
                            <p class="text-light-white/70 text-xs mb-4">
                                Domyślnie 501 · 1 set · 2 legi. Tablet odczyta format z meczu — bez konfiguracji przy starcie gry.
                            </p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-light-white">
                                    <thead class="text-light-green">
                                        <tr class="border-b border-dark-bg">
                                            <th class="text-left py-2 pr-3 font-semibold">Etap</th>
                                            <th class="text-left py-2 px-2 font-semibold">Punkty</th>
                                            <th class="text-left py-2 px-2 font-semibold">Legi / set</th>
                                            <th class="text-left py-2 pl-2 font-semibold">Sety / mecz</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="stage in activeFormatStages" x-bind:key="stage.value">
                                            <tr class="border-b border-dark-bg/60 last:border-0">
                                                <td class="py-2 pr-3 whitespace-nowrap" x-text="stage.label"></td>
                                                <td class="py-2 px-2">
                                                    <select class="select-green w-full min-w-[5rem]"
                                                            x-bind:name="'matchFormats[' + stage.value + '][startingScore]'"
                                                            x-model.number="matchFormats[stage.value].startingScore">
                                                        <template x-for="score in startingScoreOptions" x-bind:key="score">
                                                            <option x-bind:value="score" x-text="score"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 px-2">
                                                    <select class="select-green w-full min-w-[4rem]"
                                                            x-bind:name="'matchFormats[' + stage.value + '][legsToWinSet]'"
                                                            x-model.number="matchFormats[stage.value].legsToWinSet">
                                                        <template x-for="n in 15" x-bind:key="n">
                                                            <option x-bind:value="n" x-text="n"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="py-2 pl-2">
                                                    <select class="select-green w-full min-w-[4rem]"
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
                        <p x-show="participantCount < minPlayers" class="text-light-orange/80 text-xs">
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
@endsection
