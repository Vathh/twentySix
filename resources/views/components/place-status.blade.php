@props([
    'label',
    'variant' => 'planned',
    'loud' => false,
])

<span {{ $attributes->class([
    'inline-flex items-center',
    'badge-planned' => $variant === 'planned',
    'badge-status-live' => $variant === 'live',
    'badge-finished' => $variant === 'finished',
    'place-status' => $loud,
]) }}>
    @if($variant === 'live')
        <span class="place-live-dot" aria-hidden="true"></span>
    @endif
    {{ $label }}
</span>
