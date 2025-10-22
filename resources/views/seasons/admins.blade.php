@extends('layouts.app')

@section('content')
    <div class="container mx-auto py-8">
        <h1 class="text-2xl font-bold text-light-green mb-6">Zarządzanie administratorami sezonu: {{ $season->name }}</h1>

        <div class="mb-8 bg-lighter-bg p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold text-light-orange mb-4">Administratorzy sezonu</h2>
            @if(empty($admins))
                <p class="text-light-white">Brak administratorów.</p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($admins as $admin)
                        <div class="flex items-center justify-center flex-col bg-dark-bg shadow rounded-lg p-6 hover:shadow-xl">
                            <span class="btn__title mb-4 text-wrap">{{ $admin['name'] }}</span>
                            <form action="{{ route('seasons.admins.remove', $season->id) }}" method="POST">
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

        <h2 class="text-2xl text-light-green text-center mb-7">Użytkownicy powiązani z sezonem</h2>

        @if($relatedUsers->isEmpty())
            <p class="text-light-white text-center">Brak użytkowników powiązanych z sezonem.</p>
        @else
            <div class="flex flex-wrap gap-3">
                @foreach($relatedUsers as $user)
                    <div class="flex items-center justify-center flex-col bg-lighter-bg shadow rounded-lg p-6 hover:shadow-xl">
                        <span class="btn__title mb-4 text-wrap">{{ $user->name }}</span>
                        <form action="{{ route('seasons.admins.add', $season->id) }}" method="POST">
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
