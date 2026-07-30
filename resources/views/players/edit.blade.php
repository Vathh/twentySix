@extends('layouts.app')

@section('title', 'Edycja profilu')

@section('content')
    <div class="flex justify-center items-start min-h-[70vh] px-4 py-8">
        <form class="form-card w-full max-w-3xl"
              action="{{ route('players.update', $player) }}"
              method="POST"
              x-data="{ count: {{ strlen(old('description', $player->description ?? '')) }} }">
            @csrf
            @method('PUT')

            <div class="flex flex-col items-stretch">
                <h1 class="page-title text-center">Edycja profilu</h1>
                <p class="text-text-secondary text-center mb-6">{{ $player->name }}</p>

                <label class="form-label text-accent" for="description">Opis</label>
                <textarea
                    class="input-field mb-2 h-40 resize-y"
                    id="description"
                    name="description"
                    maxlength="1000"
                    placeholder="Napisz coś o sobie…"
                    x-on:input="count = $el.value.length"
                >{{ old('description', $player->description) }}</textarea>

                <div class="text-accent text-sm text-right mb-6">
                    <span x-text="count">0</span>/1000
                </div>

                <div class="flex flex-wrap gap-3 justify-center">
                    <button class="btn btn-primary" type="submit">Zapisz</button>
                    <a href="{{ route('players.show', $player) }}" class="btn btn-mini border border-border text-text-secondary bg-transparent hover:bg-bg-elevated">Anuluj</a>
                </div>

                <x-errors/>
            </div>
        </form>
    </div>
@endsection
