@props([
    'player' => null,
    'name' => '',
    'initials' => null,
    'avatarUrl' => null,
    'size' => 'md',
])

@php
    $displayName = $player->name ?? $name;
    if ($initials !== null && $initials !== '') {
        $mono = $initials;
    } elseif (is_object($player) && method_exists($player, 'initials')) {
        $mono = $player->initials();
    } else {
        $mono = \App\Models\Player\Player::initialsFromName((string) $displayName);
    }

    if ($avatarUrl !== null && $avatarUrl !== '') {
        $url = $avatarUrl;
    } elseif (is_object($player) && method_exists($player, 'avatarUrl')) {
        $url = $player->avatarUrl();
    } elseif (is_object($player) && property_exists($player, 'avatarUrl')) {
        $url = $player->avatarUrl;
    } else {
        $url = null;
    }
    $sizeClass = match ($size) {
        'sm' => 'player-avatar--sm',
        'lg' => 'player-avatar--lg',
        default => 'player-avatar--md',
    };
@endphp

<span {{ $attributes->merge(['class' => 'player-avatar '.$sizeClass]) }}>
    @if($url)
        <img src="{{ $url }}" alt="" onerror="this.remove(); this.nextElementSibling.hidden = false;">
        <span class="player-avatar-fallback" hidden>{{ $mono }}</span>
    @else
        <span class="player-avatar-fallback">{{ $mono }}</span>
    @endif
</span>
