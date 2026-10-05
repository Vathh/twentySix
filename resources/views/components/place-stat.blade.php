@props([
    'label',
    'icon' => 'calendar',
    'href' => null,
])

@php
    $tag = filled($href) ? 'a' : 'span';
@endphp

<{{ $tag }}
    @if($tag === 'a') href="{{ $href }}" @endif
    {{ $attributes->class(['place-stat']) }}
>
    <span class="place-stat-icon" aria-hidden="true">
        @switch($icon)
            @case('seasons')
                <svg class="place-stat-svg" viewBox="0 0 16 16" fill="none">
                    <path d="M2.25 5.25 8 2.75l5.75 2.5L8 7.75 2.25 5.25Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                    <path d="M2.25 8.15 8 10.65l5.75-2.5M2.25 11.05 8 13.55l5.75-2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                @break
            @case('activity')
                <svg class="place-stat-svg" viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="8" r="5.25" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M8 5.1V8.2l2.15 1.35" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                @break
            @case('people')
                <svg class="place-stat-svg" viewBox="0 0 16 16" fill="none">
                    <circle cx="6" cy="5.1" r="1.55" stroke="currentColor" stroke-width="1.3"/>
                    <circle cx="10.6" cy="5.6" r="1.25" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M2.7 12.4c.45-1.9 1.75-2.85 3.3-2.85s2.85.95 3.3 2.85" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    <path d="M9.35 9.7c.85-.25 1.7-.15 2.45.45.65.7.95 1.55 1.15 2.25" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                @break
            @case('guest')
                <svg class="place-stat-svg" viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="5.15" r="1.9" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M3.4 12.7c.55-2.25 2.15-3.4 4.6-3.4s4.05 1.15 4.6 3.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
                @break
            @case('leagues')
                <svg class="place-stat-svg" viewBox="0 0 16 16" fill="none">
                    <path d="M6.25 2.75h3.5M4.25 6.75h7.5M2.25 10.75h11.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
                @break
            @case('tournaments')
                <svg class="place-stat-svg" viewBox="0 0 16 16" fill="none">
                    <path d="M5.25 2.75h5.5v3.1a2.75 2.75 0 0 1-5.5 0v-3.1Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                    <path d="M5.25 4.15H3.4a1.7 1.7 0 0 0 1.7 2.85M10.75 4.15h1.85a1.7 1.7 0 0 1-1.7 2.85M8 8.6v2.15M5.6 13.25h4.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                @break
            @default
                <svg class="place-stat-svg" viewBox="0 0 16 16" fill="none">
                    <rect x="2.25" y="3.25" width="11.5" height="10.5" rx="1.5" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M2.25 6.5h11.5M5.25 2.15v2.2M10.75 2.15v2.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
        @endswitch
    </span>
    <span class="place-stat-copy">
        <span class="place-stat-label">{{ $label }}</span>
        <span class="place-stat-value">{{ $slot }}</span>
    </span>
</{{ $tag }}>
