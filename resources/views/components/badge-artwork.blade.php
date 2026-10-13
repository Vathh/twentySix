@props([
    'src',
    'alt' => '',
    'locked' => false,
])

@php
    $placement = \App\Support\Badge\BadgeArtworkFrame::placement($src);
@endphp

<span {{ $attributes->class(['badge-artwork', 'is-locked' => $locked]) }}>
    <img
        src="{{ asset($src) }}"
        alt="{{ $alt }}"
        style="width: {{ $placement['width'] }}%; height: {{ $placement['height'] }}%; left: {{ $placement['left'] }}%; top: {{ $placement['top'] }}%;"
    >
</span>
