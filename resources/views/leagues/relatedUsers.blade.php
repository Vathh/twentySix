@extends('layouts.app')

@section('title', 'Edycja powiązanych użytkowników')

@section('content')
    <div class="container mx-auto py-8 max-w-4xl">

        <h1 class="page-title">Użytkownicy ligi: {{ $league->name }}</h1>

        <div class="card mb-8">
            <h2 class="section-title text-accent">Aktualnie powiązani użytkownicy</h2>
            @if(empty($relatedUsers))
                <p class="text-text-secondary">Brak użytkowników powiązanych z tą ligą.</p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($relatedUsers as $user)
                        <div class="tile flex items-center justify-center flex-col">
                            <span class="card-title mb-4 text-wrap text-center">{{ $user['name'] }}</span>
                            <form action="{{ route('leagues.relatedUsers.remove', $league->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                <button type="submit" class="btn-mini-danger">Usuń</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <h2 class="section-title text-center">Wyszukiwanie użytkowników</h2>

        <form action="{{ route('leagues.relatedUsers', $league->id) }}" method="GET" class="mb-6 flex flex-wrap items-center gap-4">
            <input type="text" name="search" placeholder="Szukaj użytkownika..."
                   value="{{ request('search') }}" class="input-field flex-1 min-w-[200px]">
            <button type="submit" class="btn btn-primary">Szukaj</button>
        </form>

        <x-errors/>

        @if(request('search') && $users->isEmpty())
            <p class="empty-state">Brak wyników wyszukiwania.</p>
        @else
            <div class="flex flex-wrap gap-3 justify-center">
                @foreach($users as $user)
                    <div class="tile flex items-center justify-center flex-col bg-bg-elevated">
                        <span class="card-title mb-4 text-wrap text-center">{{ $user->player->name }}</span>
                        <form action="{{ route('leagues.relatedUsers.add', $league->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                            <button type="submit" class="btn btn-mini">Dodaj</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex justify-center mt-8">
            <a href="{{ route('leagues.show', ['league' => $league->id]) }}" class="btn btn-secondary">
                Powrót
            </a>
        </div>
    </div>
@endsection
