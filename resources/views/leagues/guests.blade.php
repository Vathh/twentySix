@extends('layouts.app')

@section('title', 'Goście ligi')

@section('content')
    <div class="container mx-auto py-8 max-w-3xl">
        <a href="{{ route('leagues.show', $league) }}" class="link-back mb-4 inline-block">← {{ $league->name }}</a>

        <h1 class="page-title">{{ $league->name }}</h1>
        <p class="text-text-muted mb-6">Pula gości bez konta — stąd dodajesz ich do szczebli.</p>

        <div class="card mb-8">
            <h2 class="section-title text-accent">Niezarejestrowani gracze</h2>
            @if(empty($guests))
                <p class="text-text-secondary">Brak gości w tej lidze.</p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($guests as $guest)
                        <div class="tile flex items-center justify-center flex-col">
                            <span class="card-title mb-4 text-wrap text-center">{{ $guest['name'] }}</span>
                            <form action="{{ route('leagues.guests.remove', $league) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="player_id" value="{{ $guest['id'] }}">
                                <button type="submit" class="btn-mini-danger">Usuń</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <h2 class="section-title text-center">Dodawanie graczy</h2>

        <form action="{{ route('leagues.guests.add', $league) }}" method="POST" class="mb-6 flex flex-wrap items-center gap-4">
            @csrf
            <input type="text" name="name" placeholder="Dodaj gracza..." class="input-field flex-1 min-w-[200px]">
            <button type="submit" class="btn btn-primary">Dodaj</button>
        </form>

        <x-errors/>

        <div class="flex justify-center mt-8">
            <a href="{{ route('leagues.show', $league) }}" class="btn btn-secondary">
                Powrót
            </a>
        </div>
    </div>
@endsection
