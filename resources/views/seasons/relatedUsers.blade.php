@extends('layouts.app')

@section('title', 'Edycja powiązanych użytkowników')

@section('content')
    <div class="container mx-auto py-8">

        <h1 class="text-2xl font-bold text-light-green mb-6">
            Użytkownicy sezonu: {{ $season->name }}
        </h1>

        <div class="mb-8 bg-lighter-bg p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold text-light-orange mb-4">Aktualnie powiązani użytkownicy</h2>
            @if(empty($relatedUsers))
                <p class="text-light-white">Brak użytkowników powiązanych z tym sezonem.</p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($relatedUsers as $user)
                        <div class="flex items-center justify-center flex-col bg-dark-bg shadow rounded-lg p-6 hover:shadow-xl">
                            <span class="btn__title mb-4 text-wrap">{{ $user['name'] }}</span>
                            <form action="{{ route('leagues.relatedUsers.remove', $season->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="user_id" value="{{ $user['id'] }}">
                                <button type="submit" class="btn-mini">Usuń</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <h2 class="text-2xl text-light-green text-center mb-7">Dodawanie użytkowników</h2>

        <form action="{{ route('leagues.relatedUsers', $season->id) }}" method="GET" class="mb-6 flex items-center space-x-4">
            <input type="text" name="search" placeholder="Szukaj użytkownika..."
                   value="{{ request('search') }}" class="input-orange flex-1">
            <button type="submit" class="btn btn-primary">Szukaj</button>
        </form>

        <x-errors/>

        @if(request('search') && $users->isEmpty())
            <p class="text-light-white text-center">Brak wyników wyszukiwania.</p>
        @else
            <div class="flex flex-wrap gap-3">
                @foreach($users as $user)
                    <div class="flex items-center justify-center flex-col bg-lighter-bg shadow rounded-lg p-6 hover:shadow-xl">
                        <span class="btn__title mb-4 text-wrap">{{ $user->player->name }}</span>
                        <form action="{{ route('leagues.relatedUsers.add', $season->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                            <button type="submit" class="btn-mini">Dodaj</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex justify-center mt-5">
            <a href="{{ route('seasons.show', ['season' => $season->id]) }}" class="btn btn-primary">
                Powrót
            </a>
        </div>
    </div>
@endsection
