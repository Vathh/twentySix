@extends('layouts.app')

@section('title', 'Nowa liga')

@section('content')
    <div class="container mx-auto py-6 max-w-3xl px-4">
        <a href="{{ route('organizations.show', $organization) }}" class="link-back mb-4 inline-block">← Powrót do organizacji</a>
        <h1 class="page-title">Nowa liga w {{ $organization->name }}</h1>
        <p class="text-text-muted mb-6">Piramida szczebli (pozycja 0 = najwyższa). Sezon turniejowy organizacji zostaje osobno.</p>

        <form class="form-card !max-w-none" method="POST" action="{{ route('leagues.store', $organization) }}"
              x-data="leagueDivisionForm()">
            @csrf

            <label class="form-label text-accent" for="leagueName">Nazwa ligi</label>
            <input class="mb-5 input-field" type="text" id="leagueName" name="leagueName"
                   value="{{ old('leagueName') }}" required maxlength="80">

            <label class="form-label text-accent" for="description">Opis</label>
            <textarea class="input-field mb-6 h-24 resize-none" id="description" name="description" maxlength="500">{{ old('description') }}</textarea>

            <h2 class="section-title">Szczeble</h2>
            <p class="text-text-muted text-sm mb-4">Awans/baraż ustawiasz na niższym szczeblu (ile miejsc idzie w górę).</p>

            <template x-for="(division, index) in divisions" :key="index">
                <div class="card mb-4">
                    <div class="flex items-center justify-between mb-3">
                        <p class="font-semibold text-accent" x-text="index === 0 ? 'Najwyższy szczebel' : ('Szczebel ' + (index + 1))"></p>
                        <button type="button" class="btn-mini-danger" x-show="divisions.length > 1" @click="remove(index)">Usuń</button>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="form-label">Nazwa</span>
                            <input class="input-field" type="text" :name="'divisions['+index+'][name]'" x-model="division.name" required>
                        </label>
                        <label class="block">
                            <span class="form-label">Pojemność</span>
                            <input class="input-field" type="number" min="2" max="16" :name="'divisions['+index+'][capacity]'" x-model.number="division.capacity" required>
                        </label>
                        <label class="block">
                            <span class="form-label">Start (X01)</span>
                            <select class="input-field" :name="'divisions['+index+'][startingScore]'" x-model.number="division.startingScore">
                                @foreach($startingScores as $score)
                                    <option value="{{ $score }}">{{ $score }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="form-label">Legi do wygrania seta</span>
                            <input class="input-field" type="number" min="1" max="15" :name="'divisions['+index+'][legsToWinSet]'" x-model.number="division.legsToWinSet" required>
                        </label>
                        <label class="block">
                            <span class="form-label">Sety do wygrania meczu</span>
                            <input class="input-field" type="number" min="1" max="5" :name="'divisions['+index+'][setsToWinMatch]'" x-model.number="division.setsToWinMatch" required>
                        </label>
                        <template x-if="index > 0">
                            <div class="grid sm:grid-cols-2 gap-3 sm:col-span-2">
                                <label class="block">
                                    <span class="form-label">Awans bezpośredni</span>
                                    <input class="input-field" type="number" min="0" max="8" :name="'divisions['+index+'][promoteDirect]'" x-model.number="division.promoteDirect">
                                </label>
                                <label class="block">
                                    <span class="form-label">Miejsca barażowe</span>
                                    <input class="input-field" type="number" min="0" max="8" :name="'divisions['+index+'][promotePlayoff]'" x-model.number="division.promotePlayoff">
                                </label>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <button type="button" class="btn btn-secondary mb-6" @click="add()">+ Dodaj szczebel</button>

            <button class="btn btn-primary w-full" type="submit">Utwórz ligę</button>
            <x-errors/>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        function leagueDivisionForm() {
            return {
                divisions: @json(old('divisions', $defaultDivisions)),
                add() {
                    this.divisions.push({
                        name: '',
                        capacity: 8,
                        startingScore: 501,
                        legsToWinSet: 2,
                        setsToWinMatch: 1,
                        promoteDirect: 2,
                        promotePlayoff: 0,
                    });
                },
                remove(index) {
                    this.divisions.splice(index, 1);
                },
            };
        }
    </script>
@endsection
