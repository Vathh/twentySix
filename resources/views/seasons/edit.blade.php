@extends('layouts.app')

@section('title')
    Edycja sezonu {{ $season->name }}
@endsection

@section('content')

    <div class="flex justify-center items-center min-h-[70vh] px-4">
        <form class="form-card"
              action="{{ route('seasons.update', $season->id) }}"
              method="POST">
            @csrf
            @method('PUT')

            <div class="flex flex-col items-stretch">
                <h1 class="page-title text-center">Edycja sezonu</h1>

                <label class="form-label text-accent" for="leagueName">Nazwa sezonu</label>
                <input
                    class="mb-5 input-field"
                    type="text"
                    id="leagueName"
                    name="leagueName"
                    value="{{ old('leagueName', $season->name) }}"
                    required
                >

                <label class="form-label text-accent" for="startDate">Data rozpoczęcia</label>
                <input class="mb-5 input-field"
                       type="date"
                       id="startDate"
                       name="startDate"
                       value="{{ old('startDate', $season->startDate->format('Y-m-d')) }}"
                       required>

                <label class="form-label text-accent" for="endDate">Data zakończenia</label>
                <input class="mb-5 input-field"
                       type="date"
                       id="endDate"
                       name="endDate"
                       value="{{ old('endDate', $season->endDate->format('Y-m-d')) }}"
                       required>

                <button class="btn btn-primary" type="submit">Zapisz zmiany</button>
                <a href="{{ route('seasons.show', $season->id) }}" class="btn btn-secondary mt-4 text-center">Powrót</a>

                <x-errors/>
            </div>
        </form>
    </div>

@endsection
