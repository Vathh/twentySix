@extends('layouts.app')

@section('title', 'Edycja graczy niezarejestrowanych')

@section('content')
    <div class="container mx-auto py-8 max-w-3xl">
        <a href="{{ route('organizations.show', $organization->id) }}" class="link-back mb-4 inline-block">← {{ $organization->name }}</a>

        <h1 class="page-title">{{ $organization->name }}</h1>

        <div class="card mb-8">
            <h2 class="section-title text-accent">Niezarejestrowani gracze</h2>
            @if(empty($guests))
                <p class="text-text-secondary">Brak graczy powiązanych z tą organizacją.</p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($guests as $guest)
                        <div class="tile flex items-center justify-center flex-col">
                            <span class="card-title mb-4 text-wrap text-center">{{ $guest['name'] }}</span>
                            <form action="{{ route('organizations.guests.remove', $organization->id) }}" method="POST">
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

        <form action="{{ route('organizations.guests.add', $organization->id) }}" method="POST" class="mb-6 flex flex-wrap items-center gap-4">
            @csrf
            <input type="text" name="name" placeholder="Dodaj gracza..." class="input-field flex-1 min-w-[200px]">
            <button type="submit" class="btn btn-primary">Dodaj</button>
        </form>

        <x-errors/>

        <div class="flex justify-center mt-8">
            <a href="{{ route('organizations.show', ['organization' => $organization->id]) }}" class="btn btn-secondary">
                Powrót
            </a>
        </div>
    </div>
@endsection
