@extends('layouts.app')

@section('title', 'Edycja powiązanych użytkowników')

@section('content')
    <div class="container mx-auto py-8">

        <h1 class="text-2xl font-bold text-light-green mb-6">
            Użytkownicy ligi: {{ $leagueDomain->name }}
        </h1>

        <form action="{{ route('leagues.relatedUsers', $leagueDomain->id) }}" method="GET" class="mb-6 flex items-center space-x-4">
            <input type="text" name="search" placeholder="Szukaj użytkownika..."
                   value="{{ request('search') }}" class="input-orange flex-1">
            <button type="submit" class="btn btn-primary">Szukaj</button>
        </form>

        @if(!empty($users))
            @foreach($users as $user)
                <li class="flex justify-between items-center text-light-white">
                    <span>{{ $user->name }} ({{ $user->email }})</span>
                </li>
            @endforeach
        @endif


        <div class="mb-8 bg-lighter-bg p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold text-light-orange mb-4">Aktualnie powiązani użytkownicy</h2>
            @if(!empty($relatedUsers))
                <p class="text-light-white">Brak użytkowników powiązanych z tą ligą.</p>
            @else
                <ul class="list-disc pl-6 space-y-1 text-light-white">
                    @foreach($relatedUsers as $user)
                        <li class="flex justify-between items-center">
                            <span>{{ $user->name }} ({{ $user->email }})</span>
{{--                            <form action="{{ route('leagues.relatedUsers.remove', [$leagueDomain, $user]) }}" method="POST">--}}
{{--                                @csrf--}}
{{--                                @method('DELETE')--}}
{{--                                <button class="text-light-red hover:underline">Usuń</button>--}}
{{--                            </form>--}}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
