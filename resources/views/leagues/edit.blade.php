@extends('layouts.app')

@section('title', 'Edycja ligi')

@section('content')

    <div class="flex justify-center items-center min-h-[70vh]">
        <form class="bg-lighter-bg rounded-2xl p-20"
              action="{{ route('leagues.update', $league->id) }}"
              method="POST">
            @csrf
            @method('PUT')

            <div class="flex flex-col items-center">
                <h1 class="text-center text-light-green mb-10 text-2xl">Edycja ligi</h1>

                <label class="mb-3 text-xl text-light-orange" for="leagueName"><b>Nazwa ligi</b></label>
                <input
                    class="mb-5 input-orange"
                    type="text"
                    name="leagueName"
                    value="{{ old('leagueName', $league->name) }}"
                    required
                >

                <label class="mb-3 text-xl text-light-orange" for="description"><b>Opis ligi</b></label>
                <textarea
                    class="input-orange mb-2 h-32 resize-none"
                    id="description"
                    name="description"
                    maxlength="500"
                    oninput="updateCounter()"
                >{{ old('description', $league->description) }}</textarea>

                <div class="text-light-green text-sm text-right w-full">
                    <span id="charCount">0</span>/500
                </div>

                <button class="btn btn-primary mt-8" type="submit">Zapisz zmiany</button>

                <a href="{{ route('leagues.show', $league->id) }}"
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
