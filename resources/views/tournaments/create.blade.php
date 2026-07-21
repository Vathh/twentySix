@extends('layouts.app')

@section('title', 'Tworzenie nowego turnieju')

@section('content')

    <div class="flex justify-center items-center min-h-[70vh] px-4">
        <form class="form-card"
              action="{{ route('tournaments.store') }}{{ $seasonId ? '?seasonId='.$seasonId : '' }}"
              method="POST">
            @csrf
            <div class="flex flex-col items-stretch">
                <h1 class="page-title text-center">Tworzenie nowego turnieju</h1>

                @if($seasonId)
                    <p class="card-description text-center mb-6">Turniej zostanie dodany do wybranego sezonu.</p>
                @else
                    <p class="card-description text-center mb-6">Turniej jednorazowy — bez powiązania z ligą i sezonem.</p>
                @endif

                <label class="form-label text-accent" for="tournamentName">Nazwa turnieju</label>
                <input class="mb-5 input-field"
                       id="tournamentName"
                       type="text"
                       placeholder="Wprowadź nazwę turnieju"
                       name="tournamentName"
                       value="{{ old('tournamentName') }}"
                       required>

                <label class="form-label text-accent" for="date">Data wydarzenia</label>
                <input class="mb-5 input-field"
                       id="date"
                       type="date"
                       name="date"
                       value="{{ old('date') }}"
                       required>

                <button class="btn btn-primary mt-2" type="submit">Stwórz turniej</button>

                @if($seasonId)
                    <a href="{{ route('seasons.show', ['season' => $seasonId]) }}" class="btn btn-secondary mt-4 text-center">Powrót do sezonu</a>
                @else
                    <a href="{{ route('tournaments.index') }}" class="btn btn-secondary mt-4 text-center">Powrót do turniejów</a>
                @endif

                <x-errors/>
            </div>
        </form>
    </div>

@endsection
