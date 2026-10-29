@extends('layouts.app')

@section('title', 'Turnieje')

@section('content')

    <x-catalog-index
        :items="$items"
        :has-more="$hasMore"
        :url="route('tournaments.index')"
        kind="tournament"
        heading="Turnieje"
        lead="Turnieje w sezonach i turnieje jednorazowe organizacji."
        placeholder="Nazwa turnieju lub organizacji…"
        empty-title="Brak turniejów"
        empty-description="Gdy pojawią się turnieje w sezonie albo jednorazowe w organizacji, zobaczysz je tutaj."
        :query="$query"
        :summary="$summary"
        :page="$page"
        :sort="$sort"
        :status="$status"
        show-status-filter
    />

@endsection
