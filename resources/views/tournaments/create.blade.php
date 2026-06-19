@extends('layouts.app')

@section('title', 'Tworzenie nowego turnieju')

@section('content')

    <div class="flex justify-center items-center min-h-[70vh]">
        <form class="bg-lighter-bg rounded-2xl p-20 "
              action="{{ route('tournaments.store') }}{{ $seasonId ? '?seasonId='.$seasonId : '' }}"
              method="POST">
            @csrf
            <div class="flex flex-col items-center">
                <h1 class="text-center text-light-green mb-4 text-2xl">Tworzenie nowego turnieju</h1>

                @if($seasonId)
                    <p class="text-center text-[#a8a8a8] text-sm mb-8">Turniej zostanie dodany do wybranego sezonu.</p>
                @else
                    <p class="text-center text-[#a8a8a8] text-sm mb-8">Turniej jednorazowy — bez powiązania z ligą i sezonem.</p>
                @endif

                <label class="mb-3 text-xl text-light-orange" for="tournamentName"><b>Nazwa turnieju</b></label>
                <input class="mb-5 input-orange"
                       id="tournamentName"
                       type="text"
                       placeholder="Wprowadź nazwę turnieju"
                       name="tournamentName"
                       value="{{ old('tournamentName') }}"
                       required>

                <label class="mb-3 text-xl text-light-orange" for="date"><b>Data wydarzenia</b></label>
                <input class="mb-5 input-orange"
                       id="date"
                       type="date"
                       name="date"
                       value="{{ old('date') }}"
                       required>

                <button class="btn btn-primary mt-8" type="submit">Stwórz turniej</button>

                @if($seasonId)
                    <a href="{{ route('seasons.show', ['season' => $seasonId]) }}" class="btn btn-primary mt-5">Powrót do sezonu</a>
                @else
                    <a href="{{ route('tournaments.index') }}" class="btn btn-primary mt-5">Powrót do turniejów</a>
                @endif

                <x-errors/>
            </div>
        </form>
    </div>

@endsection
