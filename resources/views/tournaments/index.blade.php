@extends('layouts.app')

@section('title', 'Turnieje')

@section('content')

    <x-catalog-index
        :items="$items"
        :has-more="$hasMore"
        :url="route('tournaments.index')"
        kind="tournament"
        heading="Turnieje"
        lead="Turnieje w organizacjach i turnieje jednorazowe."
        placeholder="Nazwa turnieju lub organizacji…"
        empty-title="Brak turniejów"
        empty-description="Gdy pojawią się turnieje w organizacjach lub jednorazowe, zobaczysz je tutaj."
        :query="$query"
        :summary="$summary"
        :page="$page"
        :sort="$sort"
        :status="$status"
        show-status-filter
        :create-url="auth()->check() && auth()->user()->can_create_organizations ? route('tournaments.create') : null"
        create-label="Dodaj turniej"
    />

@endsection
