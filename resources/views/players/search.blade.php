@extends('layouts.app')

@section('title', 'Szukaj graczy')

@section('content')
    <div class="py-8">
        <h1 class="text-3xl font-bold text-light-green mb-6">Szukaj graczy</h1>

        <form action="{{ route('players.search') }}" method="GET" class="flex gap-3 mb-8 max-w-xl">
            <input type="text"
                   name="q"
                   value="{{ old('q', $q) }}"
                   placeholder="Wpisz nazwę gracza..."
                   class="flex-1 bg-lighter-bg text-light-white px-4 py-3 rounded border border-light-green focus:outline-none focus:ring-2 focus:ring-light-green">
            <button type="submit" class="btn btn-primary flex items-center gap-2" title="Szukaj">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Szukaj
            </button>
        </form>

        @if($q !== '')
            @if($players->isEmpty())
                <p class="text-light-white">Brak graczy pasujących do „{{ e($q) }}”.</p>
            @else
                <p class="text-light-white mb-4">Znaleziono {{ $players->count() }} graczy.</p>
                <ul class="space-y-2 max-w-2xl">
                    @foreach($players as $p)
                        <li>
                            <a href="{{ route('players.show', $p) }}" class="block py-3 px-4 bg-lighter-bg rounded border border-border hover:border-light-green text-light-white hover:text-light-green transition">
                                {{ $p->name }}
                                <span class="text-light-gray text-sm">(zarejestrowany)</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
@endsection
