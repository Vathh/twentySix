@extends('layouts.app')

@section('title')
    Edycja sezonu {{ $season->name }}
@endsection

@section('content')

    <div class="flex justify-center items-center min-h-[70vh]">
        <form class="bg-lighter-bg rounded-2xl p-20"
              action="{{ route('seasons.update', $season->id) }}"
              method="POST">
            @csrf
            @method('PUT')

            <div class="flex flex-col items-center">
                <h1 class="text-center text-light-green mb-10 text-2xl">Edycja sezonu</h1>

                <label class="mb-3 text-xl text-light-orange" for="leagueName"><b>Nazwa sezonu</b></label>
                <input
                    class="mb-5 input-orange"
                    type="text"
                    name="leagueName"
                    value="{{ old('leagueName', $season->name) }}"
                    required
                >

                <label class="mb-3 text-xl text-light-orange" for="login"><b>Data rozpoczęcia</b></label>
                <input class="mb-5 input-orange"
                       type="date"
                       name="startDate"
                       value="{{ old('startDate', $season->startDate->format('Y-m-d')) }}"
                       required>

                <label class="mb-3 text-xl text-light-orange" for="login"><b>Data zakończenia</b></label>
                <input class="mb-5 input-orange"
                       type="date"
                       name="endDate"
                       value="{{ old('endDate', $season->endDate->format('Y-m-d')) }}"
                       required>

                <button class="btn btn-primary mt-8" type="submit">Zapisz zmiany</button>

                <a href="{{ route('seasons.show', $season->id) }}"
                   class="btn btn-primary mt-5">Powrót</a>

                <x-errors/>
            </div>
        </form>
    </div>

@endsection

@section('scripts')
    <script>
        function updateCounter() {
            const textarea = document.getElementById('description');
            const counter = document.getElementById('charCount');
            counter.textContent = textarea.value.length;
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateCounter();
        });
    </script>
@endsection
