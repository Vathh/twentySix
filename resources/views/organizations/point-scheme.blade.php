@extends('layouts.app')

@section('title', 'Punktacja turniejowa')

@section('content')
    @php
        $initialBand = $bands[0]['min_players'] ?? 4;
        foreach ($bands as $bandOption) {
            $prefix = 'bands.'.$bandOption['min_players'];
            $hasError = collect($errors->keys())->contains(
                fn (string $key) => $key === $prefix || str_starts_with($key, $prefix.'.'),
            );
            if ($hasError) {
                $initialBand = $bandOption['min_players'];
                break;
            }
        }
    @endphp

    <div class="container mx-auto py-5 max-w-5xl">
        <h1 class="page-title">Punktacja turniejowa: {{ $organization->name }}</h1>

        <p class="text-text-secondary text-sm mb-2">
            Punkty sezonu za miejsce w turnieju. W każdym paśmie wielkości osobno układają się zakresy dla
            <strong>SE / grup + playoff</strong> i dla <strong>podwójnej eliminacji</strong>.
            Zmiana wejdzie w turnieje, które wystartują po zapisie. Turniej już wystartowany zostaje przy tabeli z momentu startu.
        </p>
        @if(! old('bands'))
            <p class="text-text-secondary text-sm mb-6">
                @if($matchesDefaults)
                    Te liczby są takie jak domyślna punktacja twentySix.
                @else
                    Organizacja ma własną punktację, inną niż domyślna tabela twentySix.
                @endif
            </p>
        @endif

        <form action="{{ route('organizations.point-scheme.update', $organization->id) }}" method="POST"
              x-data="{ band: {{ (int) $initialBand }} }">
            @csrf
            @method('PUT')

            <x-errors/>

            <div class="flex flex-wrap gap-2 mb-6">
                @foreach($bands as $band)
                    <button type="button"
                            class="btn btn-secondary"
                            :class="band === {{ $band['min_players'] }} ? '!bg-bg-elevated !border-border text-accent' : ''"
                            @click="band = {{ $band['min_players'] }}">
                        {{ $band['name'] }}
                    </button>
                @endforeach
            </div>

            @foreach($bands as $band)
                @php
                    $min = $band['min_players'];
                    $seRows = old('bands.'.$min.'.se', $band['se']);
                    $deRows = old('bands.'.$min.'.de', $band['de']);
                @endphp
                <div class="card mb-6" x-show="band === {{ $min }}" x-cloak>
                    <h2 class="section-title text-accent">{{ $band['name'] }}</h2>
                    <p class="text-text-secondary text-sm mb-4">
                        Miejsca od 1 do {{ $band['max_players'] }} muszą być pokryte w całości, bez przerw i bez nakładek.
                        Gorsze miejsce nie dostaje więcej punktów niż lepsze.
                    </p>

                    <div class="grid lg:grid-cols-2 gap-6">
                        @foreach(['se' => 'SE / grupy + playoff', 'de' => 'Podwójna eliminacja'] as $format => $label)
                            @php
                                $rows = $format === 'se' ? $seRows : $deRows;
                            @endphp
                            <div x-data="pointSchemeRows(@js($rows))">
                                <h3 class="font-semibold mb-3">{{ $label }}</h3>
                                <div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 text-xs text-text-secondary mb-2">
                                    <span>Od miejsca</span>
                                    <span>Do miejsca</span>
                                    <span>Punkty</span>
                                    <span></span>
                                </div>
                                <template x-for="(row, index) in rows" :key="index">
                                    <div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 mb-2 items-center">
                                        <input class="input-field" type="number" min="1" max="{{ $band['max_players'] }}" inputmode="numeric"
                                               :name="'bands[{{ $min }}][{{ $format }}][' + index + '][place_from]'"
                                               x-model="row.place_from">
                                        <input class="input-field" type="number" min="1" max="{{ $band['max_players'] }}" inputmode="numeric"
                                               :name="'bands[{{ $min }}][{{ $format }}][' + index + '][place_to]'"
                                               x-model="row.place_to">
                                        <input class="input-field" type="number" min="0" max="65535" inputmode="numeric"
                                               :name="'bands[{{ $min }}][{{ $format }}][' + index + '][points]'"
                                               x-model="row.points">
                                        <button type="button" class="btn-mini-danger" @click="remove(index)">Usuń</button>
                                    </div>
                                </template>
                                <button type="button" class="btn btn-secondary mt-2" @click="add()">Dodaj zakres</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex flex-wrap gap-3 justify-center">
                <button type="submit" class="btn">Zapisz</button>
                <a href="{{ route('organizations.show', $organization->id) }}" class="btn btn-secondary">Powrót</a>
            </div>
        </form>

        <form action="{{ route('organizations.point-scheme.reset', $organization->id) }}" method="POST" class="flex justify-center mt-4"
              onsubmit="return confirm('Przywrócić domyślne punkty twentySix? Turnieje, które już wystartowały, zostaną przy dotychczasowej tabeli.');">
            @csrf
            <button type="submit" class="btn btn-secondary">Przywróć domyślne</button>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('pointSchemeRows', (initial) => ({
                rows: Array.isArray(initial) && initial.length > 0
                    ? initial
                    : [{ place_from: '', place_to: '', points: '' }],
                add() {
                    this.rows.push({ place_from: '', place_to: '', points: '' });
                },
                remove(index) {
                    if (this.rows.length <= 1) {
                        return;
                    }
                    this.rows.splice(index, 1);
                },
            }));
        });
    </script>
@endsection
