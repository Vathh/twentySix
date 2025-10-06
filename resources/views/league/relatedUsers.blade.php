@extends('layouts.app')

@section('title', 'Edycja powiązanych użytkowników')

@section('content')
    <div class="container mx-auto py-8">

        <h1 class="text-2xl font-bold text-light-green mb-6">
            Użytkownicy ligi: {{ $league->name }}
        </h1>

        <form action="{{ route('leagues.relatedUsers', $league) }}" method="GET" class="mb-6 flex items-center space-x-4">
            <input type="text" name="search" placeholder="Szukaj użytkownika..."
                   value="{{ request('search') }}" class="input-orange flex-1">
            <button type="submit" class="btn btn-primary">Szukaj</button>
        </form>

        {{-- Lista powiązanych użytkowników --}}
        <div class="mb-8 bg-lighter-bg p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold text-light-orange mb-4">Aktualnie powiązani użytkownicy</h2>
            @if($relatedUsers->isEmpty())
                <p class="text-light-white">Brak użytkowników powiązanych z tą ligą.</p>
            @else
                <ul class="list-disc pl-6 space-y-1 text-light-white">
                    @foreach($relatedUsers as $user)
                        <li class="flex justify-between items-center">
                            <span>{{ $user->name }} ({{ $user->email }})</span>
                            <form action="{{ route('leagues.relatedUsers.remove', [$leagueDomain, $user]) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button class="text-light-red hover:underline">Usuń</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Dodawanie nowego użytkownika --}}
        @if($allUsers->isNotEmpty())
            <div class="bg-lighter-bg p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold text-light-orange mb-4">Dodaj użytkownika do ligi</h2>
                <form action="{{ route('leagues.relatedUsers.add', $leagueDomain) }}" method="POST">
                    @csrf
                    <div class="flex space-x-4 items-center">
                        <select name="user_id" class="input-orange flex-1" required>
                            <option value="">Wybierz użytkownika...</option>
                            @foreach($allUsers as $user)
                                @if(!$relatedUsers->contains($user))
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                @endif
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary">Dodaj</button>
                    </div>
                </form>
            </div>
        @else
            <p class="text-light-white mt-4">Brak użytkowników do dodania.</p>
        @endif

        {{-- Wyświetlanie błędów walidacji --}}
        @if($errors->any())
            <ul class="px-4 py-2 border-2 rounded border-light-red text-light-red mt-6">
                @foreach($errors->all() as $error)
                    <li class="my-2">{{ $error }}</li>
                @endforeach
            </ul>
        @endif

    </div>
@endsection
