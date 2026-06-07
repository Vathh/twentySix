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
            {{-- Strefa 2: Dodaj uczestników (zakładki) --}}
            <div
                x-data="{ activeTab: @json($addTab) }"
                class="mb-8 bg-lighter-bg rounded-lg shadow overflow-hidden"
            >
                <div class="border-b border-dark-bg px-6 pt-6">
                    <h2 class="text-xl font-semibold text-light-orange mb-4">Dodaj uczestników</h2>
                    <div class="flex gap-1">
                        <button
                            type="button"
                            x-on:click="activeTab = 'registered'"
                            x-bind:class="activeTab === 'registered'
                                ? 'bg-light-orange text-dark-bg'
                                : 'bg-dark-bg text-light-white hover:bg-dark-bg/80'"
                            class="px-5 py-2 rounded-t-lg font-semibold text-sm transition"
                        >
                            Zarejestrowani
                        </button>
                        <button
                            type="button"
                            x-on:click="activeTab = 'guests'"
                            x-bind:class="activeTab === 'guests'
                                ? 'bg-light-orange text-dark-bg'
                                : 'bg-dark-bg text-light-white hover:bg-dark-bg/80'"
                            class="px-5 py-2 rounded-t-lg font-semibold text-sm transition"
                        >
                            Goście
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    {{-- Zakładka: Zarejestrowani --}}
                    <div x-show="activeTab === 'registered'" x-cloak>
                        {{-- Wyszukiwarka --}}
                        <div class="mb-6">
                            <h3 class="text-light-green font-semibold mb-2">Wyszukaj użytkownika</h3>
                            <form action="{{ route('tournaments.start', $tournament->id) }}" method="GET" class="flex items-center gap-4">
                                <input type="hidden" name="tab" value="registered">
                                <input type="text" name="search" placeholder="Min. 5 znaków..."
                                       value="{{ request('search') }}" class="input-orange flex-1">
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

                        {{-- Oczekujące zaproszenia --}}
                        @if($invitationPipeline->isNotEmpty())
                            <div class="mb-6">
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

                        {{-- Stały skład --}}
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

                    {{-- Zakładka: Goście --}}
                    <div x-show="activeTab === 'guests'" x-cloak>
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

        {{-- Strefa 3: Start turnieju --}}
        @if($canManageParticipants)
            <div
                x-data="{
                    groupsCount: {{ (int) old('groupsCount', 2) }},
                    advancePerGroup: {{ (int) old('advancePerGroup', 2) }},
                    tabletsCount: {{ (int) old('tabletsCount', 2) }},
                    advancesByGroupCount: @json($advancesByGroupCount),
                    minPlayers: {{ $minPlayers }},
                    participantCount: {{ $participantCount }},
                    get allowedAdvances() {
                        return this.advancesByGroupCount[this.groupsCount] ?? [1];
                    },
                    get bracketSize() {
                        return this.groupsCount * this.advancePerGroup;
                    },
                    syncAdvance() {
                        if (!this.allowedAdvances.includes(this.advancePerGroup)) {
                            this.advancePerGroup = this.allowedAdvances[0] ?? 1;
                        }
                    }
                }"
                x-init="syncAdvance()"
                class="mb-8 bg-lighter-bg p-6 rounded-lg shadow"
            >
                <h2 class="text-xl font-semibold text-light-orange mb-4">Start turnieju</h2>

                @if($participants->isEmpty())
                    <p class="text-light-white text-sm">Dodaj uczestników powyżej, aby wystartować turniej.</p>
                @else
                    <form action="{{ route('tournaments.run', $tournament->id) }}" method="POST" class="flex flex-col items-center gap-4">
                        @csrf

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 w-full max-w-2xl">
                            <div class="flex flex-col">
                                <label for="groupsCount" class="text-light-green font-semibold mb-2">Liczba grup</label>
                                <select id="groupsCount" name="groupsCount" class="select-green"
                                        x-model.number="groupsCount" x-on:change="syncAdvance()">
                                    @foreach ($groupCountOptions as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label for="advancePerGroup" class="text-light-green font-semibold mb-2">Awans z grupy</label>
                                <select id="advancePerGroup" name="advancePerGroup" class="select-green"
                                        x-model.number="advancePerGroup">
                                    <template x-for="advance in allowedAdvances" x-bind:key="advance">
                                        <option x-bind:value="advance" x-text="advance"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label for="tabletsCount" class="text-light-green font-semibold mb-2">Liczba tabletów</label>
                                <input id="tabletsCount" type="number" name="tabletsCount" min="1"
                                       class="select-green" x-model.number="tabletsCount">
                            </div>
                        </div>

                        <p class="text-light-orange text-sm">
                            Drabinka playoff: <span x-text="bracketSize"></span> graczy (grupy × awans)
                        </p>

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

        <div class="flex justify-center">
            <a href="{{ route('tournaments.show', ['tournament' => $tournament->id]) }}" class="btn btn-primary">
                Powrót
            </a>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
@endsection
