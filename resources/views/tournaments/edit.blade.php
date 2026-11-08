@extends('layouts.app')

@section('title', 'Edycja turnieju '.$tournament->name)

@section('content')

    <div class="flex justify-center items-center min-h-[70vh] px-4">
        <form class="form-card"
              action="{{ route('tournaments.update', $tournament->id) }}"
              method="POST">
            @csrf
            @method('PUT')

            <div class="flex flex-col items-stretch">
                <h1 class="page-title text-center">Edycja turnieju</h1>
                <p class="card-description text-center mb-6">
                    @if($tournament->canEditDate())
                        Nazwa i data rozgrywek.
                    @else
                        Po starcie można zmienić tylko nazwę. Datę zmienisz po anulowaniu rozgrywek.
                    @endif
                </p>

                <label class="form-label text-accent" for="tournamentName">Nazwa turnieju</label>
                <input
                    class="mb-3 input-field"
                    type="text"
                    id="tournamentName"
                    name="tournamentName"
                    value="{{ old('tournamentName', $tournament->name) }}"
                    maxlength="25"
                    required
                >

                @if($tournament->canEditDate())
                    <label class="form-label text-accent" for="date">Data wydarzenia</label>
                    <input
                        class="mb-3 input-field"
                        type="date"
                        id="date"
                        name="date"
                        value="{{ old('date', $tournament->getDate()) }}"
                        required
                    >
                @else
                    <p class="form-label text-accent">Data wydarzenia</p>
                    <p class="mb-3 text-text-secondary">{{ $tournament->getPlayDateFormatted() }}</p>
                @endif

                <button class="btn btn-primary" type="submit">Zapisz zmiany</button>
                <a href="{{ route('tournaments.show', $tournament->id) }}" class="btn btn-secondary mt-4 text-center">Powrót</a>

                <x-errors/>
            </div>
        </form>
    </div>

@endsection
