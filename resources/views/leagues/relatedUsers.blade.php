@extends('layouts.app')

@section('title', 'Powiązani użytkownicy ligi')

@section('content')
    <div class="container mx-auto py-8 max-w-4xl">
        <a href="{{ route('leagues.show', $league) }}" class="link-back mb-4 inline-block">← {{ $league->name }}</a>

        <h1 class="page-title">Użytkownicy ligi: {{ $league->name }}</h1>
        <p class="text-text-muted mb-6">Pula powiązanych — stąd dodajesz zawodników do szczebli.</p>

        <x-related-user-search
            :search-url="route('leagues.relatedUsers', $league)"
            :add-url="route('leagues.relatedUsers.add', $league)"
            :remove-url="route('leagues.relatedUsers.remove', $league)"
            :cancel-url-template="preg_replace('#/invitations/\d+/cancel$#', '/invitations/__ID__/cancel', route('leagues.relatedUsers.invitations.cancel', [$league, 0]))"
            :related="$relatedUsers"
            :pending="$pendingInvitations->map(fn ($invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->userPlayer?->name ?? 'Brak nazwy',
            ])->values()->all()"
            add-label="Zaproś"
            empty-related="Brak użytkowników powiązanych z tą ligą."
        />

        <div class="flex justify-center mt-8">
            <a href="{{ route('leagues.show', $league) }}" class="btn btn-secondary">
                Powrót
            </a>
        </div>
    </div>
@endsection
