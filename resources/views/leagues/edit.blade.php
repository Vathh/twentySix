@extends('layouts.app')

@section('title', 'Edycja ligi')

@section('content')
    <div class="container mx-auto py-6 max-w-3xl px-4">
        <a href="{{ route('leagues.show', $league) }}" class="link-back mb-4 inline-block">← Powrót</a>
        <h1 class="page-title">Edycja ligi</h1>
        <p class="text-text-muted mb-6">Poza sezonem możesz ruszać szczeble i format. W trakcie sezonu piramida jest zamrożona.</p>

        <form class="form-card !max-w-none" method="POST" action="{{ route('leagues.update', $league) }}">
            @csrf
            @method('PUT')

            <label class="form-label text-accent" for="leagueName">Nazwa ligi</label>
            <input class="mb-3 input-field" type="text" id="leagueName" name="leagueName"
                   value="{{ old('leagueName', $league->name) }}" required maxlength="80">

            <label class="form-label text-accent" for="description">Opis (opcjonalnie)</label>
            <textarea class="input-field mb-6 h-24 resize-none" id="description" name="description" maxlength="500">{{ old('description', $league->description) }}</textarea>

            @foreach($divisions as $index => $division)
                <div class="card mb-4">
                    <p class="font-semibold text-accent mb-3">{{ $index === 0 ? 'Najwyższy szczebel' : 'Szczebel '.($index + 1) }}</p>
                    <input type="hidden" name="divisions[{{ $index }}][id]" value="{{ $division->id }}">
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="form-label">Nazwa</span>
                            <input class="input-field" type="text" name="divisions[{{ $index }}][name]" value="{{ old("divisions.$index.name", $division->name) }}" required>
                        </label>
                        <label class="block">
                            <span class="form-label">Pojemność</span>
                            <input class="input-field" type="number" min="2" max="16" name="divisions[{{ $index }}][capacity]" value="{{ old("divisions.$index.capacity", $division->capacity) }}" required>
                        </label>
                        <label class="block">
                            <span class="form-label">Start (X01)</span>
                            <select class="input-field" name="divisions[{{ $index }}][startingScore]">
                                @foreach($startingScores as $score)
                                    <option value="{{ $score }}" @selected((int) old("divisions.$index.startingScore", $division->starting_score) === $score)>{{ $score }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="form-label">Legi do wygrania seta</span>
                            <input class="input-field" type="number" min="1" max="15" name="divisions[{{ $index }}][legsToWinSet]" value="{{ old("divisions.$index.legsToWinSet", $division->legs_to_win_set) }}" required>
                        </label>
                        <label class="block">
                            <span class="form-label">Sety do wygrania meczu</span>
                            <input class="input-field" type="number" min="1" max="5" name="divisions[{{ $index }}][setsToWinMatch]" value="{{ old("divisions.$index.setsToWinMatch", $division->sets_to_win_match) }}" required>
                        </label>
                        @php
                            $dartLimit = old("divisions.$index.dartLimit", $division->dart_limit);
                            $lossThreshold = old("divisions.$index.lossThreshold", $division->loss_threshold);
                        @endphp
                        <div class="sm:col-span-2 grid sm:grid-cols-2 gap-3" x-data="{
                            dartOn: {{ $dartLimit ? 'true' : 'false' }},
                            dartLimit: {{ $dartLimit ? (int) $dartLimit : 45 }},
                            lossOn: {{ $lossThreshold ? 'true' : 'false' }},
                            lossThreshold: {{ $lossThreshold ? (int) $lossThreshold : 50 }},
                        }">
                            <label class="block">
                                <span class="flex items-center gap-2 form-label">
                                    <input type="checkbox" x-model="dartOn" @change="if (!dartOn) lossOn = false">
                                    Ogranicznik lotek
                                </span>
                                <div class="flex items-center gap-2 mt-1" x-show="dartOn" x-cloak>
                                    <button type="button" class="btn btn-secondary !py-1 !px-3" @click="dartLimit = Math.max(15, dartLimit - 3)">−</button>
                                    <input class="input-field text-center w-24" type="number" min="15" max="99" step="3"
                                           name="divisions[{{ $index }}][dartLimit]" x-model.number="dartLimit">
                                    <button type="button" class="btn btn-secondary !py-1 !px-3" @click="dartLimit = Math.min(99, dartLimit + 3)">+</button>
                                </div>
                                <input type="hidden" name="divisions[{{ $index }}][dartLimit]" value="" x-show="!dartOn">
                            </label>
                            <label class="block" x-show="dartOn" x-cloak>
                                <span class="flex items-center gap-2 form-label">
                                    <input type="checkbox" x-model="lossOn">
                                    Próg przegranej
                                </span>
                                <input class="input-field mt-1" type="number" min="2" max="170" name="divisions[{{ $index }}][lossThreshold]"
                                       x-show="lossOn" x-model.number="lossThreshold">
                                <input type="hidden" name="divisions[{{ $index }}][lossThreshold]" value="" x-show="!lossOn">
                            </label>
                        </div>
                        @if($index > 0)
                            <label class="block">
                                <span class="form-label">Awans bezpośredni</span>
                                <input class="input-field" type="number" min="0" max="8" name="divisions[{{ $index }}][promoteDirect]" value="{{ old("divisions.$index.promoteDirect", $division->promote_direct) }}">
                            </label>
                            <label class="block">
                                <span class="form-label">Miejsca barażowe</span>
                                <input class="input-field" type="number" min="0" max="8" name="divisions[{{ $index }}][promotePlayoff]" value="{{ old("divisions.$index.promotePlayoff", $division->promote_playoff) }}">
                            </label>
                        @endif
                    </div>
                </div>
            @endforeach

            <button class="btn btn-primary w-full" type="submit">Zapisz</button>
            <x-errors/>
        </form>
    </div>
@endsection
