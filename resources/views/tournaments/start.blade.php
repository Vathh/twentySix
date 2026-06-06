@extends('layouts.app')

@section('title', 'Start turnieju')

@section('content')
    <div class="container mx-auto py-8">

        <h1 class="text-2xl font-bold text-light-green mb-6">
            Start turnieju: {{ $tournament->name }}
        </h1>

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-red-900/40 border border-red-500 text-light-white">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div
            x-data="{
                selected: [],
                groupsCount: {{ (int) old('groupsCount', 2) }},
                advancePerGroup: {{ (int) old('advancePerGroup', 2) }},
                tabletsCount: {{ (int) old('tabletsCount', 2) }},
                advancesByGroupCount: @json($advancesByGroupCount),
                minPlayers: {{ $minPlayers }},
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
            <h2 class="text-xl font-semibold text-light-orange mb-4">
                Zawodnicy turnieju
            </h2>

            @if(empty($players))
                <p class="text-light-white">
                    Brak użytkowników powiązanych z tym sezonem.
                </p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($players as $player)
                        <div
                            x-on:click="selected.includes({{ $player->id }})
                                ? selected = selected.filter(id => id !== {{ $player->id }})
                                : selected.push({{ $player->id }})"
                            x-bind:class="selected.includes({{ $player->id }})
                                ? 'bg-light-green text-dark-bg'
                                : 'bg-dark-bg text-light-white'"
                            class="cursor-pointer px-4 py-2 rounded-lg transition duration-200 shadow hover:shadow-lg select-none"
                        >
                            {{ $player->name }}
                        </div>
                    @endforeach
                </div>

                <form action="{{ route('tournaments.run', $tournament->id) }}" method="POST" class="mt-6 flex flex-col items-center gap-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 w-full max-w-2xl mt-4">
                        <div class="flex flex-col">
                            <label for="groupsCount" class="text-light-green font-semibold mb-2">Liczba grup</label>
                            <select
                                id="groupsCount"
                                name="groupsCount"
                                class="select-green"
                                x-model.number="groupsCount"
                                x-on:change="syncAdvance()"
                            >
                                @foreach ($groupCountOptions as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex flex-col">
                            <label for="advancePerGroup" class="text-light-green font-semibold mb-2">Awans z grupy</label>
                            <select
                                id="advancePerGroup"
                                name="advancePerGroup"
                                class="select-green"
                                x-model.number="advancePerGroup"
                            >
                                <template x-for="advance in allowedAdvances" x-bind:key="advance">
                                    <option x-bind:value="advance" x-text="advance"></option>
                                </template>
                            </select>
                        </div>

                        <div class="flex flex-col">
                            <label for="tabletsCount" class="text-light-green font-semibold mb-2">Liczba tabletów</label>
                            <input
                                id="tabletsCount"
                                type="number"
                                name="tabletsCount"
                                min="1"
                                class="select-green"
                                x-model.number="tabletsCount"
                            >
                        </div>
                    </div>

                    <p class="text-light-orange text-sm">
                        Graczy w drabince playoff: <span x-text="bracketSize"></span>
                        (grupy × awans)
                    </p>

                    <span class="text-light-orange font-semibold">
                        Zaznaczono: <span x-text="selected.length"></span>
                        (minimum <span x-text="minPlayers"></span>)
                    </span>

                    <input type="hidden" name="selectedPlayers" x-bind:value="JSON.stringify(selected)">

                    <button
                        type="submit"
                        class="btn btn-primary px-6 py-2"
                        x-bind:disabled="selected.length < minPlayers"
                    >
                        Start turnieju
                    </button>
                </form>
            @endif
        </div>

        <div>
            <h2 class="text-2xl text-light-green text-center mb-7">Dodawanie użytkowników</h2>
            <div class="flex justify-around">
                <p class="btn btn-primary text-wrap"><a href="{{ route('seasons.relatedUsers', $tournament->season->id) }}">Edycja powiązanych użytkowników</a></p>
                <p class="btn btn-primary text-wrap"><a href="{{ route('seasons.guests', $tournament->season->id) }}">Edycja graczy niezarejestrowanych</a></p>
            </div>
        </div>

        <div class="flex justify-center mt-10">
            <a href="{{ route('tournaments.show', ['tournament' => $tournament->id]) }}" class="btn btn-primary">
                Powrót
            </a>
        </div>
    </div>
@endsection
