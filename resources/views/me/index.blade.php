@extends('layouts.app')

@section('title', 'Gdzie gram')

@section('content')
    <div class="max-w-6xl mx-auto px-4 pt-10 pb-16">
        <header class="entity-header mb-10">
            <p class="entity-eyebrow">Konto</p>
            <h1 class="entity-title">Gdzie gram</h1>
            <span class="entity-rule" aria-hidden="true"></span>
        </header>
        <p class="text-text-muted mb-10 max-w-2xl">
            Trwające sezony turniejowe, ligi i organizacje, w których jesteś w składzie, grasz albo którymi zarządzasz.
            Publiczny katalog jest w <a href="{{ route('organizations.index') }}" class="text-accent underline">Rozgrywkach</a>.
        </p>

        <section class="mb-12">
            <h2 class="section-title mt-0">Sezony</h2>
            @if(count($seasons) === 0)
                <p class="text-text-muted text-sm">Nie jesteś powiązany z żadnym trwającym sezonem turniejowym.</p>
            @else
                <div class="index-grid">
                    @foreach($seasons as $index => $item)
                        <a href="{{ $item['url'] }}" class="block" style="--stagger: {{ $index }}">
                            <div class="index-card">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-text leading-snug">{{ $item['name'] }}</h3>
                                    <span class="shrink-0 text-xs font-semibold {{ $item['role'] === 'admin' ? 'text-accent' : 'text-text-muted' }}">{{ $item['roleLabel'] }}</span>
                                </div>
                                <p class="card-description mb-0">
                                    {{ $item['organizationName'] }}
                                    @if($item['startDate'] && $item['endDate'])
                                        · {{ $item['startDate'] }} – {{ $item['endDate'] }}
                                    @endif
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="mb-12">
            <h2 class="section-title mt-0">Ligi</h2>
            @if(count($leagues) === 0)
                <p class="text-text-muted text-sm">Nie jesteś w żadnej lidze piramidowej.</p>
            @else
                <div class="index-grid">
                    @foreach($leagues as $index => $item)
                        <a href="{{ $item['url'] }}" class="block" style="--stagger: {{ $index }}">
                            <div class="index-card">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-text leading-snug">{{ $item['name'] }}</h3>
                                    <span class="shrink-0 text-xs font-semibold {{ $item['role'] === 'admin' ? 'text-accent' : 'text-text-muted' }}">{{ $item['roleLabel'] }}</span>
                                </div>
                                <p class="card-description mb-0">
                                    {{ $item['organizationName'] }}
                                    @if($item['divisionName'])
                                        · {{ $item['divisionName'] }}
                                    @endif
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section>
            <h2 class="section-title mt-0">Organizacje</h2>
            @if(count($organizations) === 0)
                <p class="text-text-muted text-sm">Nie jesteś powiązany z żadną organizacją.</p>
            @else
                <div class="index-grid">
                    @foreach($organizations as $index => $item)
                        <a href="{{ $item['url'] }}" class="block" style="--stagger: {{ $index }}">
                            <div class="index-card">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-text leading-snug">{{ $item['name'] }}</h3>
                                    <span class="shrink-0 text-xs font-semibold {{ $item['role'] === 'admin' ? 'text-accent' : 'text-text-muted' }}">{{ $item['roleLabel'] }}</span>
                                </div>
                                <p class="card-description mb-0">{{ $item['description'] ?: 'Organizacja' }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
