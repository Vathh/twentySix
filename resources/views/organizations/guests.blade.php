@extends('layouts.app')

@section('title', 'Goście')

@section('content')
    <x-people-page
        title="Goście"
        kind="Goście"
        tone="organization"
        :organization-name="$organization->name"
        :organization-url="route('organizations.show', $organization->id)"
        lead="Gracze bez konta, przypisani do tej organizacji."
    >
        <x-guest-roster
            :add-action="route('organizations.guests.add', $organization->id)"
            :remove-action="route('organizations.guests.remove', $organization->id)"
            :guests="$guests"
            empty="W tej organizacji nie ma jeszcze gości."
        />
    </x-people-page>
@endsection
