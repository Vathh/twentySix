@extends('layouts.app')

@section('title', 'Powiązani użytkownicy')

@section('content')
    <x-people-page
        title="Powiązani użytkownicy"
        kind="Powiązani"
        tone="organization"
        :organization-name="$organization->name"
        :organization-url="route('organizations.show', $organization->id)"
        lead="Konta, które można zapraszać do rozgrywek tej organizacji."
    >
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
            empty-related="Nikt jeszcze nie jest powiązany z tą organizacją."
        />
    </x-people-page>
@endsection
