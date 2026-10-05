@extends('layouts.app')

@section('title', 'Powiązani użytkownicy')

@section('content')
    <x-people-page
        title="Powiązani użytkownicy"
        kind="Powiązani"
        tone="league"
        :organization-name="$league->organization?->name"
        :organization-url="$league->organization ? route('organizations.show', $league->organization) : null"
        :league-name="$league->name"
        :league-url="route('leagues.show', $league)"
        lead="Konta, z których składasz szczeble."
    >
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
            empty-related="Nikt jeszcze nie jest powiązany z tą ligą."
        />
    </x-people-page>
@endsection
