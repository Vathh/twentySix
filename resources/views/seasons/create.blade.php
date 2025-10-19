@extends('layouts.app')

@section('title', 'Tworzenie nowego sezonu')

@section('content')

    <div class="flex justify-center items-center min-h-[70vh]">
        <form class="bg-lighter-bg rounded-2xl p-20 " action="{{ route('seasons.store') }}?leagueId={{ $leagueId }}" method="POST">
            @csrf
            <div class="flex flex-col items-center">
                <h1 class="text-center text-light-green mb-10 text-2xl">Tworzenie nowego sezonu</h1>

                <label class="mb-3 text-xl text-light-orange" for="login"><b>Nazwa sezonu</b></label>
                <input class="mb-5 input-orange"
                       type="text"
                       placeholder="Wprowadź nazwę sezonu"
                       name="seasonName"
                       value="{{ old('seasonName') }}"
                       required>

                <label class="mb-3 text-xl text-light-orange" for="login"><b>Data rozpoczęcia</b></label>
                <input class="mb-5 input-orange"
                       type="date"
                       name="startDate"
                       value="{{ old('startDate') }}"
                       required>

                <label class="mb-3 text-xl text-light-orange" for="login"><b>Data zakończenia</b></label>
                <input class="mb-5 input-orange"
                       type="date"
                       name="endDate"
                       value="{{ old('endDate') }}"
                       required>

                <button class="btn btn-primary mt-8" type="submit" name="loginBtn">Stwórz sezon</button>

                <a href="{{ route('leagues.show', ['league' => $leagueId]) }}" class="btn btn-primary mt-5" type="submit" name="loginBtn">Powrót</a>

                <x-errors/>
            </div>
        </form>
    </div>

@endsection

