@extends('layouts.app')

@section('title', 'Sezony')

@section('content')

    <x-catalog-index
        :items="$items"
        :has-more="$hasMore"
        :url="route('seasons.index')"
        kind="season"
        heading="Sezony"
        lead="Sezony turniejowe ze wszystkich organizacji."
        placeholder="Nazwa sezonu lub organizacji…"
        empty-title="Brak sezonów"
        empty-description="Sezony pojawią się po utworzeniu ich w organizacjach."
        :query="$query"
        :summary="$summary"
        :page="$page"
        :sort="$sort"
        :status="$status"
        show-status-filter
        :create-url="auth()->check() && auth()->user()->can_create_organizations ? route('seasons.create') : null"
        create-label="Dodaj sezon"
    />

@endsection
