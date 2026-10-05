@extends('layouts.app')

@section('title', 'Powiązani użytkownicy')

@section('content')
    <x-people-page
        title="Powiązani użytkownicy"
        kind="Powiązani"
        tone="season"
        :organization-name="$season->organization?->name"
        :organization-url="$season->organization ? route('organizations.show', $season->organization->id) : null"
        :season-name="$season->name"
        :season-url="route('seasons.show', $season->id)"
        lead="Konta, które można zapraszać do turniejów tego sezonu."
    >
        <x-related-user-search
            :search-url="route('seasons.relatedUsers', $season->id)"
            :add-url="route('seasons.relatedUsers.add', $season->id)"
            :remove-url="route('seasons.relatedUsers.remove', $season->id)"
            :cancel-url-template="preg_replace('#/invitations/\d+/cancel$#', '/invitations/__ID__/cancel', route('seasons.relatedUsers.invitations.cancel', [$season->id, 0]))"
            :related="collect($relatedUsers)->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->player->name ?? '—',
            ])->values()->all()"
            :pending="$pendingInvitations->map(fn ($invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->userPlayer?->name ?? 'Brak nazwy',
            ])->values()->all()"
            add-label="Zaproś"
            empty-related="Nikt jeszcze nie jest powiązany z tym sezonem."
        />
    </x-people-page>
@endsection
