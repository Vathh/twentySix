@extends('layouts.app')

@section('title', 'Goście')

@section('content')
    <x-people-page
        title="Goście"
        kind="Goście"
        tone="season"
        :organization-name="$season->organization?->name"
        :organization-url="$season->organization ? route('organizations.show', $season->organization->id) : null"
        :season-name="$season->name"
        :season-url="route('seasons.show', $season->id)"
        lead="Gracze bez konta, przypisani do tego sezonu."
    >
        <x-guest-roster
            :add-action="route('seasons.guests.add', $season->id)"
            :remove-action="route('seasons.guests.remove', $season->id)"
            :guests="$guests"
            empty="W tym sezonie nie ma jeszcze gości."
        />
    </x-people-page>
@endsection
