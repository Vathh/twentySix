@props([
    'kind',
    'href',
    'name',
    'meta' => null,
    'statusLabel' => null,
    'statusVariant' => null,
    'roleLabel' => null,
    'roleAccent' => false,
])

@php
    $kindLabel = match ($kind) {
        'organization' => 'Organizacja',
        'season' => 'Sezon',
        'tournament' => 'Turniej',
        'league' => 'Liga',
        default => $kind,
    };
@endphp

<a href="{{ $href }}" {{ $attributes->class([
    'place-card',
    'place-card--'.$kind,
    'is-'.$statusVariant => filled($statusVariant),
]) }}>
    <span class="place-kind">{{ $kindLabel }}</span>
    <div class="place-card-head">
        <span class="place-card-name">{{ $name }}</span>
        @if(filled($statusLabel))
            <x-place-status
                :label="$statusLabel"
                :variant="$statusVariant ?: 'planned'"
                class="shrink-0"
            />
        @elseif(filled($roleLabel))
            <span @class(['place-card-role', 'is-accent' => $roleAccent])>{{ $roleLabel }}</span>
        @endif
    </div>
    @if(filled($meta))
        <p class="place-card-meta">{{ $meta }}</p>
    @endif
</a>
