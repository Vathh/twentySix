@props([
    'title',
    'subtitle' => null,
    'url' => null,
])

<li {{ $attributes->merge(['class' => 'p-3 rounded-lg border border-border bg-bg-elevated/50']) }}>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="min-w-0">
            @if($url)
                <a href="{{ $url }}" class="text-text font-semibold hover:text-accent">{{ $title }}</a>
            @else
                <p class="text-text font-semibold leading-snug">{{ $title }}</p>
            @endif
            @if($subtitle)
                <p class="text-text-muted text-sm mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            {{ $slot }}
        </div>
    </div>
</li>
