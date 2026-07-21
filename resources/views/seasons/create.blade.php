@extends('layouts.app')

@section('title', 'Tworzenie nowego sezonu')

@section('content')

    <div class="flex justify-center items-center min-h-[70vh] px-4">
        <form class="form-card" action="{{ route('seasons.store') }}?leagueId={{ $leagueId }}" method="POST">
            @csrf
            <div class="flex flex-col items-stretch">
                <h1 class="page-title text-center">Tworzenie nowego sezonu</h1>

                <label class="form-label text-accent" for="seasonName">Nazwa sezonu</label>
                <input class="mb-5 input-field"
                       type="text"
                       id="seasonName"
                       placeholder="Wprowadź nazwę sezonu"
                       name="seasonName"
                       value="{{ old('seasonName') }}"
                       required>

                <label class="form-label text-accent" for="startDate">Data rozpoczęcia</label>
                <input class="mb-5 input-field"
                       type="date"
                       id="startDate"
                       name="startDate"
                       value="{{ old('startDate') }}"
                       required>

                <label class="form-label text-accent" for="endDate">Data zakończenia</label>
                <input class="mb-5 input-field"
                       type="date"
                       id="endDate"
                       name="endDate"
                       value="{{ old('endDate') }}"
                       required>

                <button class="btn btn-primary mt-2" type="submit" name="loginBtn">Stwórz sezon</button>
                <a href="{{ route('leagues.show', ['league' => $leagueId]) }}" class="btn btn-secondary mt-4 text-center">Powrót</a>

                <x-errors/>
            </div>
        </form>
    </div>

@endsection
