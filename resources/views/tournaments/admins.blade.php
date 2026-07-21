@extends('layouts.app')

@section('title', 'Administratorzy — '.$tournament->name)

@section('content')
    <div class="container mx-auto py-8 max-w-3xl">
        <a href="{{ route('tournaments.show', $tournament->id) }}" class="link-back mb-4 inline-block">← Powrót do turnieju</a>

        <h1 class="page-title">Administratorzy turnieju: {{ $tournament->name }}</h1>

        @if(session('success'))
            <div class="mb-4 alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="card mb-8">
            <h2 class="section-title text-accent">Obecni administratorzy</h2>
            @if($admins->isEmpty())
                <p class="text-text-secondary">Brak administratorów.</p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($admins as $admin)
                        <div class="tile flex items-center justify-center flex-col min-w-[140px]">
                            <span class="card-title mb-4 text-wrap text-center">{{ $admin['name'] }}</span>
                            <form action="{{ route('tournaments.admins.remove', $tournament->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="user_id" value="{{ $admin['id'] }}">
                                <button type="submit" class="btn-mini-danger">Usuń</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card mb-8">
            <h2 class="section-title text-accent">Dodaj administratora</h2>
            <form method="GET" action="{{ route('tournaments.admins', $tournament->id) }}" class="mb-6 flex flex-wrap items-center gap-3">
                <input
                    type="search"
                    name="search"
                    value="{{ old('search', $search ?? '') }}"
                    placeholder="Szukaj po nazwie gracza (min. 2 znaki)"
                    class="input-field flex-1 min-w-[200px]"
                >
                <button type="submit" class="btn btn-mini">Szukaj</button>
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
                        <div class="tile flex items-center justify-center flex-col min-w-[140px]">
                            <span class="card-title mb-4 text-wrap text-center">{{ $user->player?->name ?? $user->email }}</span>
                            <form action="{{ route('tournaments.admins.add', $tournament->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                <button type="submit" class="btn btn-mini">Dodaj</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
