@extends('layouts.app')

@section('title', 'Tworzenie nowej organizacji')

@section('content')

    <div class="flex justify-center items-center min-h-[70vh] px-4">
        <form class="form-card" action="{{ route('organizations.store') }}" method="POST">
            @csrf
            <div class="flex flex-col items-stretch">
                <h1 class="page-title text-center">Tworzenie nowej organizacji</h1>

                <label class="form-label text-accent" for="organizationName">Nazwa organizacji</label>
                <input class="mb-5 input-field"
                       type="text"
                       id="organizationName"
                       placeholder="Wprowadź nazwę organizacji"
                       name="organizationName"
                       value="{{ old('organizationName') }}"
                       required>

                <label class="form-label text-accent" for="description">Opis organizacji</label>
                <textarea
                    class="input-field mb-2 h-32 resize-none"
                    id="description"
                    name="description"
                    maxlength="500"
                    oninput="updateCounter()"
                    placeholder="Opis organizacji (np. lokalizacja, terminy spotkań, poziom, zasady...)"
                >{{ old('description') }}</textarea>

                <div class="text-accent text-sm text-right mb-6">
                    <span id="charCount">0</span>/500
                </div>

                <button class="btn btn-primary" type="submit" name="loginBtn">Stwórz organizację</button>
                <a href="{{ route('organizations.index') }}" class="btn btn-secondary mt-4 text-center">Powrót</a>

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
