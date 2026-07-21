@extends('layouts.app')

@section('title')
    Edycja ligi {{ $league->name }}
@endsection

@section('content')

    <div class="flex justify-center items-center min-h-[70vh] px-4">
        <form class="form-card"
              action="{{ route('leagues.update', $league->id) }}"
              method="POST">
            @csrf
            @method('PUT')

            <div class="flex flex-col items-stretch">
                <h1 class="page-title text-center">Edycja ligi</h1>

                <label class="form-label text-accent" for="leagueName">Nazwa ligi</label>
                <input
                    class="mb-5 input-field"
                    type="text"
                    id="leagueName"
                    name="leagueName"
                    value="{{ old('leagueName', $league->name) }}"
                    required
                >

                <label class="form-label text-accent" for="description">Opis ligi</label>
                <textarea
                    class="input-field mb-2 h-32 resize-none"
                    id="description"
                    name="description"
                    maxlength="500"
                    oninput="updateCounter()"
                >{{ old('description', $league->description) }}</textarea>

                <div class="text-accent text-sm text-right mb-6">
                    <span id="charCount">0</span>/500
                </div>

                <button class="btn btn-primary" type="submit">Zapisz zmiany</button>
                <a href="{{ route('leagues.show', $league->id) }}" class="btn btn-secondary mt-4 text-center">Powrót</a>

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
