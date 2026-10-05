@props([
    'current',
    'organizationName' => null,
    'organizationUrl' => null,
    'seasonName' => null,
    'seasonUrl' => null,
    'tournamentName' => null,
    'tournamentStatusLabel' => null,
    'tournamentStatusVariant' => null,
    'oneOff' => false,
    'currentMeta' => null,
    'leagueName' => null,
    'leagueUrl' => null,
    'peopleTitle' => null,
    'peopleKind' => null,
    'peopleTone' => 'organization',
])

@php
    $slots = [];
    $onPeoplePage = filled($peopleTitle);

    if (filled($organizationName)) {
        $slots[] = [
            'kind' => 'organization',
            'kindLabel' => 'Organizacja',
            'name' => $organizationName,
            'url' => $current === 'organization' && ! $onPeoplePage ? null : $organizationUrl,
            'current' => $current === 'organization' && ! $onPeoplePage,
        ];
    }

    if (filled($seasonName)) {
        $slots[] = [
            'kind' => 'season',
            'kindLabel' => 'Sezon',
            'name' => $seasonName,
            'url' => $current === 'season' && ! $onPeoplePage ? null : $seasonUrl,
            'current' => $current === 'season' && ! $onPeoplePage,
        ];
    }

    if (filled($leagueName)) {
        $slots[] = [
            'kind' => 'league',
            'kindLabel' => 'Liga',
            'name' => $leagueName,
            'url' => $leagueUrl,
            'current' => false,
        ];
    }

    if ($onPeoplePage) {
        $slots[] = [
            'kind' => $peopleTone,
            'kindLabel' => $peopleKind ?: 'Ludzie',
            'name' => $peopleTitle,
            'url' => null,
            'current' => true,
        ];
    }

    if ($current === 'tournament' && ! $onPeoplePage) {
        $slots[] = [
            'kind' => 'tournament',
            'kindLabel' => $oneOff ? 'Turniej jednorazowy' : 'Turniej',
            'name' => $tournamentName,
            'url' => null,
            'current' => true,
            'statusLabel' => $tournamentStatusLabel,
            'statusVariant' => $tournamentStatusVariant ?: 'planned',
        ];
    }
@endphp

@if($slots !== [])
    <nav class="place-bar" aria-label="Gdzie jesteś">
        <ol class="place-bar-track">
            @foreach($slots as $slot)
                @if(! $loop->first)
                    <li class="place-bar-sep" aria-hidden="true">›</li>
                @endif
                <li @class([
                    'place-bar-item',
                    'is-current' => $slot['current'],
                    'is-ancestor' => ! $slot['current'],
                ])>
                    @php $slotTag = filled($slot['url']) ? 'a' : 'div'; @endphp
                    <{{ $slotTag }}
                        @if($slotTag === 'a') href="{{ $slot['url'] }}" @endif
                        @class([
                            'place-slot',
                            'place-slot--'.$slot['kind'],
                            'is-current' => $slot['current'],
                            'is-ancestor' => ! $slot['current'],
                            'is-'.($slot['statusVariant'] ?? '') => $slot['current'] && isset($slot['statusVariant']),
                        ])
                        @if($slot['current']) aria-current="page" @endif
                    >
                        <span class="place-kind">{{ $slot['kindLabel'] }}</span>
                        @if($slot['current'])
                            <div class="place-current-row">
                                <h1 class="place-name">{{ $slot['name'] }}</h1>
                                @if(! empty($slot['statusLabel']))
                                    <x-place-status
                                        :label="$slot['statusLabel']"
                                        :variant="$slot['statusVariant']"
                                        loud
                                    />
                                @endif
                            </div>
                            @if(filled($currentMeta))
                                <p class="place-slot-meta">{{ $currentMeta }}</p>
                            @endif
                        @else
                            <span class="place-name">{{ $slot['name'] }}</span>
                        @endif
                    </{{ $slotTag }}>
                </li>
            @endforeach
        </ol>
    </nav>
@endif
