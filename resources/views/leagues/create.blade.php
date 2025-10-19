@extends('layouts.app')

@section('title', 'Tworzenie nowej ligi')

@section('content')

    <div class="flex justify-center items-center min-h-[70vh]">
        <form class="bg-lighter-bg rounded-2xl p-20 " action="{{ route('leagues.store') }}" method="POST">
            @csrf
            <div class="flex flex-col items-center">
                <h1 class="text-center text-light-green mb-10 text-2xl">Tworzenie nowej ligi</h1>

                <label class="mb-3 text-xl text-light-orange" for="login"><b>Nazwa ligi</b></label>
                <input class="mb-5 input-orange"
                       type="text"
                       placeholder="Wprowadź nazwę ligi"
                       name="leagueName"
                       value="{{ old('leagueName') }}"
                       required>

                <label class="mb-3 text-xl text-light-orange" for="description"><b>Opis ligi</b></label>
                <textarea
                    class=" input-orange mb-2 h-32 resize-none"
                    id="description"
                    name="description"
                    maxlength="500"
                    oninput="updateCounter()"
                    placeholder="Opis ligi (np. lokalizacja, terminy spotkań, poziom, zasady...)"
                >{{ old('description') }}</textarea>

                <div class="text-light-green text-sm text-right">
                    <span id="charCount">0</span>/500
                </div>

                <button class="btn btn-primary mt-8" type="submit" name="loginBtn">Stwórz ligę</button>

                <a href="{{ route('leagues.index') }}" class="btn btn-primary mt-5" type="submit" name="loginBtn">Powrót</a>

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

