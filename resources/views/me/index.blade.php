@extends('layouts.app')

@section('title', 'Gdzie gram')

@section('content')
    <div class="max-w-6xl mx-auto px-4 pt-6 pb-12">
        <header class="entity-header mb-6">
            <p class="entity-eyebrow">Konto</p>
            <h1 class="entity-title">Gdzie gram</h1>
            <span class="entity-rule" aria-hidden="true"></span>
        </header>
        <p class="text-text-muted mb-8 max-w-2xl text-sm">
            Trwające sezony turniejowe, ligi i organizacje, w których jesteś w składzie, grasz albo którymi zarządzasz.
            Publiczny katalog jest w <a href="{{ route('organizations.index') }}" class="text-accent underline">Rozgrywkach</a>.
        </p>

        <section class="mb-8">
            <h2 class="section-title mt-0">Sezony</h2>
            @if(count($seasons) === 0)
                <p class="text-text-muted text-sm">Nie jesteś powiązany z żadnym trwającym sezonem turniejowym.</p>
            @else
                @php
                    $formatDay = function (?string $date): ?string {
                        if (! filled($date)) {
                            return null;
                        }

                        return \Illuminate\Support\Carbon::parse($date)
                            ->locale(app()->getLocale())
                            ->translatedFormat('j F Y');
                    };
                @endphp
                <div class="index-grid">
                    @foreach($seasons as $index => $item)
                        @php
                            $start = $formatDay($item['startDate'] ?? null);
                            $end = $formatDay($item['endDate'] ?? null);
                            $seasonMeta = $item['organizationName'];
                            if ($start && $end) {
                                $seasonMeta .= ' · '.$start.' – '.$end;
                            }
                        @endphp
                        <x-place-card
                            kind="season"
                            :href="$item['url']"
                            :name="$item['name']"
                            :meta="$seasonMeta"
                            :role-label="$item['roleLabel']"
                            :role-accent="$item['role'] === 'admin'"
                            style="--stagger: {{ $index }}"
                        />
                    @endforeach
                </div>
            @endif
        </section>

        <section class="mb-8">
            <h2 class="section-title mt-0">Ligi</h2>
            @if(count($leagues) === 0)
                <p class="text-text-muted text-sm">Nie jesteś w żadnej lidze piramidowej.</p>
            @else
                <div class="index-grid">
                    @foreach($leagues as $index => $item)
                        @php
                            $leagueMeta = $item['organizationName'];
                            if ($item['divisionName']) {
                                $leagueMeta .= ' · '.$item['divisionName'];
                            }
                        @endphp
                        <x-place-card
                            kind="league"
                            :href="$item['url']"
                            :name="$item['name']"
                            :meta="$leagueMeta"
                            :role-label="$item['roleLabel']"
                            :role-accent="$item['role'] === 'admin'"
                            style="--stagger: {{ $index }}"
                        />
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
                        <x-place-card
                            kind="organization"
                            :href="$item['url']"
                            :name="$item['name']"
                            :meta="$item['description'] ?: null"
                            :role-label="$item['roleLabel']"
                            :role-accent="$item['role'] === 'admin'"
                            style="--stagger: {{ $index }}"
                        />
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
