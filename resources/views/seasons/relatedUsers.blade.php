@extends('layouts.app')

@section('title', 'Edycja powiązanych użytkowników')

@section('content')
    <div class="container mx-auto py-8 max-w-4xl">
        <a href="{{ route('seasons.show', $season->id) }}" class="link-back mb-4 inline-block">← {{ $season->name }}</a>

        <h1 class="page-title">Użytkownicy sezonu: {{ $season->name }}</h1>

        <x-related-user-search
            :search-url="route('seasons.relatedUsers', $season->id)"
            :add-url="route('seasons.relatedUsers.add', $season->id)"
            :remove-url="route('seasons.relatedUsers.remove', $season->id)"
            :related="collect($relatedUsers)->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->player->name ?? '—',
            ])->values()->all()"
            empty-related="Brak użytkowników powiązanych z tym sezonem."
        />

        <div class="flex justify-center mt-8">
            <a href="{{ route('seasons.show', ['season' => $season->id]) }}" class="btn btn-secondary">
                Powrót
            </a>
        </div>
    </div>
@endsection
