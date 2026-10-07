@extends('layouts.app')

@section('title', 'Organizacje')

@section('content')

    <x-catalog-index
        :items="$items"
        :has-more="$hasMore"
        :url="route('organizations.index')"
        kind="organization"
        heading="Organizacje"
        lead="Publiczne organizacje darterskie."
        placeholder="Nazwa organizacji…"
        empty-title="Brak organizacji"
        empty-description="Utwórz pierwszą organizację, aby organizować sezony i turnieje."
        :query="$query"
        :summary="$summary"
        :page="$page"
        :sort="$sort"
        :create-url="auth()->check() && auth()->user()->can_create_organizations ? route('organizations.create') : null"
        create-label="Dodaj organizację"
    />

@endsection
