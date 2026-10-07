@props([
    'title',
    'subtitle' => null,
    'url' => null,
    'avatarName' => null,
    'avatarUrl' => null,
    'avatarInitials' => null,
])

<li {{ $attributes->merge(['class' => 'p-3 rounded-lg border border-border bg-bg-elevated/50']) }}>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            @if($avatarName !== null)
                <x-player-avatar
                    :name="$avatarName"
                    :initials="$avatarInitials"
                    :avatar-url="$avatarUrl"
                    size="md"
                />
            @endif
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
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            {{ $slot }}
        </div>
    </div>
</li>
