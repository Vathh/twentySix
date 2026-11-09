@extends('layouts.app')

@section('title', 'Porównanie odznaczeń')

@section('content')
    @php
        $mineUnlocked = collect($checkout['mine']['items'])->where('timesEarned', '>', 0)->count();
        $theirUnlocked = collect($checkout['theirs']['items'])->where('timesEarned', '>', 0)->count();
        $checkoutTotal = count($checkout['mine']['items']);
    @endphp
    <div class="compare-sheet py-2 sm:py-4">
        <header class="compare-head">
            <a href="{{ route('players.show', $player['id']) }}" class="link-back">Wróć do profilu {{ $player['name'] }}</a>
            <h1 class="compare-title">Porównanie odznaczeń</h1>
            <p class="compare-note">Liczą się mecze 501 w lidze i turnieju. Jaśniejsza ramka to wyższy poziom.</p>
        </header>

        <div class="compare-names">
            <p class="compare-names__side" title="{{ $viewer['name'] }}">{{ $viewer['name'] }}</p>
            <span class="compare-vs">vs</span>
            <p class="compare-names__side is-right" title="{{ $player['name'] }}">{{ $player['name'] }}</p>
        </div>

        @forelse($sections as $section)
            <section class="compare-section">
                <h2 class="compare-section__title">{{ $section['title'] }}</h2>
                @foreach($section['rows'] as $row)
                    @php
                        $mineRank = ((int) $row['mine']['levelNumber']) * 1000 + (int) $row['mine']['progressPercent'];
                        $theirRank = ((int) $row['theirs']['levelNumber']) * 1000 + (int) $row['theirs']['progressPercent'];
                    @endphp
                    <div class="compare-row">
                        <p class="compare-type">{{ $section['key'] === 'qf' ? 'QF '.$row['label'] : $row['label'] }}</p>
                        <div class="compare-pair">
                            @include('players.partials.compare-badge-card', [
                                'item' => $row['mine'],
                                'ahead' => $mineRank > $theirRank,
                            ])
                            <span class="compare-pair__mark" aria-hidden="true"></span>
                            @include('players.partials.compare-badge-card', [
                                'item' => $row['theirs'],
                                'ahead' => $theirRank > $mineRank,
                            ])
                        </div>
                    </div>
                @endforeach
            </section>
        @empty
            <p class="compare-empty">Żadne z was nie odblokowało jeszcze odznaki.</p>
        @endforelse
    </div>

    <section class="compare-wheels-band">
        <h2 class="compare-section__title">Checkouty 100+</h2>
        <div class="compare-wheels">
            <div class="compare-wheel">
                <p class="compare-wheel__name" title="{{ $viewer['name'] }}">{{ $viewer['name'] }}</p>
                <p class="compare-wheel__count">{{ $mineUnlocked }} / {{ $checkoutTotal }}</p>
                <x-checkout-wheel :hits="$checkout['mine']['hits']" :items="$checkout['mine']['items']" />
            </div>
            <div class="compare-wheel">
                <p class="compare-wheel__name" title="{{ $player['name'] }}">{{ $player['name'] }}</p>
                <p class="compare-wheel__count">{{ $theirUnlocked }} / {{ $checkoutTotal }}</p>
                <x-checkout-wheel :hits="$checkout['theirs']['hits']" :items="$checkout['theirs']['items']" />
            </div>
        </div>
    </section>
@endsection
