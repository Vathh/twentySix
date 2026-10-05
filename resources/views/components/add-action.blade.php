@props([
    'href' => null,
    'disabled' => false,
])

@php
    $tag = $disabled || blank($href) ? 'span' : 'a';
@endphp

<{{ $tag }}
    @if($tag === 'a') href="{{ $href }}" @endif
    @if($disabled) aria-disabled="true" @endif
    {{ $attributes->class([
        'btn-compact',
        'btn-primary' => ! $disabled,
        'btn-secondary' => $disabled,
    ]) }}
>
    <svg class="btn-compact-plus" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M8 3.25v9.5M3.25 8h9.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
    </svg>
    {{ $slot }}
</{{ $tag }}>
