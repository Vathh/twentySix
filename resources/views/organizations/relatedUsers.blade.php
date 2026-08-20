@extends('layouts.app')

@section('title', 'Edycja powiązanych użytkowników')

@section('content')
    <div class="container mx-auto py-8 max-w-4xl">
        <a href="{{ route('organizations.show', $organization->id) }}" class="link-back mb-4 inline-block">← {{ $organization->name }}</a>

        <h1 class="page-title">Użytkownicy organizacji: {{ $organization->name }}</h1>

        <x-related-user-search
            :search-url="route('organizations.relatedUsers', $organization->id)"
            :add-url="route('organizations.relatedUsers.add', $organization->id)"
            :remove-url="route('organizations.relatedUsers.remove', $organization->id)"
            :cancel-url-template="preg_replace('#/invitations/\d+/cancel$#', '/invitations/__ID__/cancel', route('organizations.relatedUsers.invitations.cancel', [$organization->id, 0]))"
            :related="$relatedUsers"
            :pending="$pendingInvitations->map(fn ($invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->userPlayer?->name ?? 'Brak nazwy',
            ])->values()->all()"
            add-label="Zaproś"
            empty-related="Brak użytkowników powiązanych z tą organizacją."
        />

        <div class="flex justify-center mt-8">
            <a href="{{ route('organizations.show', ['organization' => $organization->id]) }}" class="btn btn-secondary">
                Powrót
            </a>
        </div>
    </div>
@endsection
