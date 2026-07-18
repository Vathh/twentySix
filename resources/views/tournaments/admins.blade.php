@extends('layouts.app')

@section('title', 'Administratorzy — '.$tournament->name)

@section('content')
    <div class="container mx-auto py-8 max-w-3xl">
        <a href="{{ route('tournaments.show', $tournament->id) }}" class="text-light-green hover:underline text-sm mb-4 inline-block">← Powrót do turnieju</a>

        <h1 class="text-2xl font-bold text-light-green mb-6">Administratorzy turnieju: {{ $tournament->name }}</h1>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-900/50 border border-green-600 rounded text-light-green">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-900/50 border border-red-600 rounded text-light-orange">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 p-3 bg-red-900/50 border border-red-600 rounded text-light-orange">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-8 bg-lighter-bg p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold text-light-orange mb-4">Obecni administratorzy</h2>
            @if($admins->isEmpty())
                <p class="text-light-white">Brak administratorów.</p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($admins as $admin)
                        <div class="flex items-center justify-center flex-col bg-dark-bg shadow rounded-lg p-6 hover:shadow-xl min-w-[140px]">
                            <span class="btn__title mb-4 text-wrap text-center">{{ $admin['name'] }}</span>
                            <form action="{{ route('tournaments.admins.remove', $tournament->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="user_id" value="{{ $admin['id'] }}">
                                <button type="submit" class="btn-mini">Usuń</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mb-8 bg-lighter-bg p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold text-light-orange mb-4">Dodaj administratora</h2>
            <form method="GET" action="{{ route('tournaments.admins', $tournament->id) }}" class="mb-6 flex flex-wrap items-center gap-3">
                <input
                    type="search"
                    name="search"
                    value="{{ old('search', $search ?? '') }}"
                    placeholder="Szukaj po nazwie gracza (min. 2 znaki)"
                    class="flex-1 min-w-[200px] rounded border border-border bg-dark-bg px-3 py-2 text-light-white"
                >
                <button type="submit" class="px-4 py-2 rounded bg-light-green text-dark-bg font-semibold text-sm">Szukaj</button>
            </form>

            @if($searchUsers->isEmpty())
                <p class="text-text-muted text-sm">
                    @if(($search ?? '') !== '')
                        Brak wyników.
                    @else
                        Wpisz fragment nazwy gracza, aby znaleźć użytkownika do dodania.
                    @endif
                </p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($searchUsers as $user)
                        <div class="flex items-center justify-center flex-col bg-dark-bg shadow rounded-lg p-6 hover:shadow-xl min-w-[140px]">
                            <span class="btn__title mb-4 text-wrap text-center">{{ $user->player?->name ?? $user->email }}</span>
                            <form action="{{ route('tournaments.admins.add', $tournament->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                <button type="submit" class="btn-mini">Dodaj</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
