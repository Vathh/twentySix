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
            Trwające turnieje, sezony turniejowe, ligi i organizacje, w których jesteś w składzie, grasz albo którymi zarządzasz.
            Publiczny katalog jest w <a href="{{ route('organizations.index') }}" class="text-accent underline">Rozgrywkach</a>.
        </p>

        <section class="mb-8">
            <h2 class="section-title mt-0">Turnieje</h2>
            @if(count($tournaments) === 0)
                <p class="text-text-muted text-sm">Nie masz teraz turnieju do rozegrania ani do prowadzenia.</p>
            @else
                <div x-data="{ shown: 3 }">
                <div class="index-grid">
                    @foreach($tournaments as $index => $item)
                        @php
                            $tournamentMeta = collect([
                                $item['organizationName'] ?? null,
                                $item['seasonName'] ?? null,
                                filled($item['date'] ?? null)
                                    ? \Illuminate\Support\Carbon::parse($item['date'])->locale(app()->getLocale())->translatedFormat('j F Y')
                                    : null,
                                $item['statusLabel'] ?? null,
                            ])->filter()->implode(' · ');
                        @endphp
                        <div @if($index >= 3) x-show="shown > {{ $index }}" x-cloak @endif>
                        <x-place-card
                            kind="tournament"
                            :href="$item['url']"
                            :name="$item['name']"
                            :meta="$tournamentMeta"
                            :role-label="$item['roleLabel']"
                            :role-accent="$item['role'] === 'admin'"
                            style="--stagger: {{ $index }}"
                        />
                        </div>
                    @endforeach
                </div>
                @if(count($tournaments) > 3)
                    <button type="button" class="mt-4 text-sm font-semibold text-accent" x-show="shown < {{ count($tournaments) }}" x-cloak @click="shown = Math.min(shown + 3, {{ count($tournaments) }})">
                        Załaduj więcej
                    </button>
                @endif
                </div>
            @endif
        </section>

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
                <div x-data="{ shown: 3 }">
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
                        <div @if($index >= 3) x-show="shown > {{ $index }}" x-cloak @endif>
                        <x-place-card
                            kind="season"
                            :href="$item['url']"
                            :name="$item['name']"
                            :meta="$seasonMeta"
                            :role-label="$item['roleLabel']"
                            :role-accent="$item['role'] === 'admin'"
                            style="--stagger: {{ $index }}"
                        />
                        </div>
                    @endforeach
                </div>
                @if(count($seasons) > 3)
                    <button type="button" class="mt-4 text-sm font-semibold text-accent" x-show="shown < {{ count($seasons) }}" x-cloak @click="shown = Math.min(shown + 3, {{ count($seasons) }})">
                        Załaduj więcej
                    </button>
                @endif
                </div>
            @endif
        </section>

        <section class="mb-8">
            <h2 class="section-title mt-0">Ligi</h2>
            @if(count($leagues) === 0)
                <p class="text-text-muted text-sm">Nie jesteś w żadnej lidze piramidowej.</p>
            @else
                <div x-data="{ shown: 3 }">
                <div class="index-grid">
                    @foreach($leagues as $index => $item)
                        @php
                            $leagueMeta = $item['organizationName'];
                            if ($item['divisionName']) {
                                $leagueMeta .= ' · '.$item['divisionName'];
                            }
                        @endphp
                        <div @if($index >= 3) x-show="shown > {{ $index }}" x-cloak @endif>
                        <x-place-card
                            kind="league"
                            :href="$item['url']"
                            :name="$item['name']"
                            :meta="$leagueMeta"
                            :role-label="$item['roleLabel']"
                            :role-accent="$item['role'] === 'admin'"
                            style="--stagger: {{ $index }}"
                        />
                        </div>
                    @endforeach
                </div>
                @if(count($leagues) > 3)
                    <button type="button" class="mt-4 text-sm font-semibold text-accent" x-show="shown < {{ count($leagues) }}" x-cloak @click="shown = Math.min(shown + 3, {{ count($leagues) }})">
                        Załaduj więcej
                    </button>
                @endif
                </div>
            @endif
        </section>

        <section>
            <h2 class="section-title mt-0">Organizacje</h2>
            @if(count($organizations) === 0)
                <p class="text-text-muted text-sm">Nie jesteś powiązany z żadną organizacją.</p>
            @else
                <div x-data="{ shown: 3 }">
                <div class="index-grid">
                    @foreach($organizations as $index => $item)
                        <div @if($index >= 3) x-show="shown > {{ $index }}" x-cloak @endif>
                            <x-place-card
                                kind="organization"
                                :href="$item['url']"
                                :name="$item['name']"
                                :meta="$item['description'] ?: null"
                                :role-label="$item['roleLabel']"
                                :role-accent="$item['role'] === 'admin'"
                                style="--stagger: {{ $index }}"
                            />
                            @if($item['role'] !== 'admin')
                                <form
                                    method="POST"
                                    action="{{ route('me.organizations.leave', $item['id']) }}"
                                    class="mt-2"
                                    data-confirm="Wypisać się ze składu organizacji {{ $item['name'] }}?"
                                    onsubmit="return confirm(this.dataset.confirm);"
                                >
                                    @csrf
                                    <button type="submit" class="text-sm text-text-muted underline">Wypisz się</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if(count($organizations) > 3)
                    <button type="button" class="mt-4 text-sm font-semibold text-accent" x-show="shown < {{ count($organizations) }}" x-cloak @click="shown = Math.min(shown + 3, {{ count($organizations) }})">
                        Załaduj więcej
                    </button>
                @endif
                </div>
            @endif
        </section>
    </div>
@endsection
