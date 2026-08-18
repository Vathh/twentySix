@extends('layouts.app')

@section('content')
    <div class="container mx-auto py-8">
        <h1 class="page-title">Zarządzanie administratorami organizacji: {{ $organization->name }}</h1>

        <div class="card mb-8">
            <h2 class="section-title text-accent">Administratorzy organizacji</h2>
            @if(empty($admins))
                <p class="text-text-secondary">Brak administratorów.</p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($admins as $admin)
                        <div class="tile flex items-center justify-center flex-col">
                            <span class="card-title mb-4 text-wrap text-center">{{ $admin['name'] }}</span>
                            <form action="{{ route('organizations.admins.remove', $organization->id) }}" method="POST">
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

        <h2 class="section-title text-center">Użytkownicy powiązani z organizacją</h2>

        @if($relatedUsers->isEmpty())
            <p class="empty-state">Brak użytkowników powiązanych z organizacją.</p>
        @else
            <div class="flex flex-wrap gap-3 justify-center">
                @foreach($relatedUsers as $user)
                    <div class="tile flex items-center justify-center flex-col bg-bg-elevated">
                        <span class="card-title mb-4 text-wrap text-center">{{ $user->name }}</span>
                        <form action="{{ route('organizations.admins.add', $organization->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                            <button type="submit" class="btn btn-mini">Dodaj</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex justify-center mt-8">
            <a href="{{ route('organizations.show', ['organization' => $organization->id]) }}" class="btn btn-secondary">
                Powrót
            </a>
        </div>
    </div>
@endsection
