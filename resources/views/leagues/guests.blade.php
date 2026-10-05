@extends('layouts.app')

@section('title', 'Goście')

@section('content')
    <x-people-page
        title="Goście"
        kind="Goście"
        tone="league"
        :organization-name="$league->organization?->name"
        :organization-url="$league->organization ? route('organizations.show', $league->organization) : null"
        :league-name="$league->name"
        :league-url="route('leagues.show', $league)"
        lead="Gracze bez konta, z których składasz szczeble."
    >
        <x-guest-roster
            :add-action="route('leagues.guests.add', $league)"
            :remove-action="route('leagues.guests.remove', $league)"
            :guests="$guests"
            empty="W tej lidze nie ma jeszcze gości."
        />
    </x-people-page>
@endsection