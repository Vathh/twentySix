@extends('layouts.app')

@section('title', 'Szukaj graczy')

@section('content')
    <div class="py-8 max-w-3xl">
        <h1 class="page-title">Szukaj graczy</h1>

        <form action="{{ route('players.search') }}" method="GET" class="flex gap-3 mb-8">
            <input type="text"
                   name="q"
                   value="{{ old('q', $q) }}"
                   placeholder="Wpisz nazwę gracza..."
                   class="input-field flex-1">
            <button type="submit" class="btn btn-primary flex items-center gap-2 shrink-0" title="Szukaj">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Szukaj
            </button>
        </form>

        @if($q !== '')
            @if($players->isEmpty())
                <x-empty-state
                    title="Brak wyników"
                    description="Nie znaleziono graczy pasujących do „{{ $q }}”."
                />
            @else
                <p class="text-text-secondary mb-4">Znaleziono {{ $players->count() }} graczy.</p>
                <ul class="space-y-2">
                    @foreach($players as $index => $p)
                        <li style="--stagger: {{ $index }}" class="animate-stagger-item">
                            <a href="{{ route('players.show', $p) }}" class="list-item block hover:border-accent hover:text-accent">
                                {{ $p->name }}
                                <span class="text-text-muted text-sm">(zarejestrowany)</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
@endsection
