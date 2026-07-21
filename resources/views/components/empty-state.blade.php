@props([
    'title' => 'Nic tu jeszcze nie ma',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'empty-panel']) }}>
    <svg class="empty-panel-icon" viewBox="0 0 64 64" fill="none" aria-hidden="true">
        <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="1.5" opacity="0.35"/>
        <circle cx="32" cy="32" r="18" stroke="currentColor" stroke-width="1.5" opacity="0.5"/>
        <circle cx="32" cy="32" r="8" stroke="currentColor" stroke-width="1.5" opacity="0.7"/>
        <circle cx="32" cy="32" r="2.5" fill="currentColor" opacity="0.8"/>
        <path d="M48 14 L54 8 M50 16 L56 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.55"/>
        <path d="M52 11 L58 16" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" opacity="0.4"/>
    </svg>
    <p class="empty-panel-title">{{ $title }}</p>
    @if($description)
        <p class="empty-panel-desc">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
