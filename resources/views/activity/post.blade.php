@php
    $actor = $item['actor'] ?? [];
    $name = $actor['name'] ?? '';
    $playerId = $actor['playerId'] ?? null;
    $kind = $item['kind']['label'] ?? 'Historia';
    $when = \Carbon\Carbon::parse($item['occurredAt'])->locale(app()->getLocale())->diffForHumans();
@endphp

<li class="feed-post" x-data="feedPost(@js($item))">
    <div class="feed-post-head">
        @if($playerId)
            <a href="{{ route('players.show', $playerId) }}" class="feed-post-avatar" aria-label="{{ $name }}">
                <x-player-avatar
                    :name="$name"
                    :initials="$actor['initials'] ?? null"
                    :avatar-url="$actor['avatarUrl'] ?? null"
                    size="sm"
                />
            </a>
        @else
            <span class="feed-post-avatar">
                <x-player-avatar
                    :name="$name"
                    :initials="$actor['initials'] ?? null"
                    :avatar-url="$actor['avatarUrl'] ?? null"
                    size="sm"
                />
            </span>
        @endif
        <div class="min-w-0">
            @if($playerId)
                <a href="{{ route('players.show', $playerId) }}" class="feed-post-name">{{ $name }}</a>
            @else
                <p class="feed-post-name">{{ $name }}</p>
            @endif
            <p class="feed-post-meta">{{ $when }} · {{ $kind }}</p>
        </div>
    </div>
    <p class="feed-post-body">{{ $item['sentence'] }}</p>
    @include('activity.engagement', ['seed' => $item])
</li>
