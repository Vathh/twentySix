@php
    $header = $item['header'] ?? $item['actor'] ?? [];
    $name = $header['name'] ?? '';
    $url = $header['url'] ?? null;
    $kind = $item['kind']['label'] ?? 'Historia';
    $when = \Carbon\Carbon::parse($item['occurredAt'])->locale(app()->getLocale())->diffForHumans();
@endphp

<li class="feed-post" x-data="feedPost(@js($item))">
    <div class="feed-post-head">
        @if($url)
            <a href="{{ $url }}" class="feed-post-avatar" aria-label="{{ $name }}">
                <x-player-avatar
                    :name="$name"
                    :initials="$header['initials'] ?? null"
                    :avatar-url="$header['avatarUrl'] ?? null"
                    size="sm"
                />
            </a>
        @else
            <span class="feed-post-avatar">
                <x-player-avatar
                    :name="$name"
                    :initials="$header['initials'] ?? null"
                    :avatar-url="$header['avatarUrl'] ?? null"
                    size="sm"
                />
            </span>
        @endif
        <div class="min-w-0">
            @if($url)
                <a href="{{ $url }}" class="feed-post-name">{{ $name }}</a>
            @else
                <p class="feed-post-name">{{ $name }}</p>
            @endif
            <p class="feed-post-meta">{{ $when }} · {{ $kind }}</p>
        </div>
    </div>
    <p class="feed-post-body">
        @foreach($item['sentenceParts'] ?? [['text' => $item['sentence'], 'url' => null]] as $part)
            @if(!empty($part['url']))
                <a href="{{ $part['url'] }}" class="feed-post-mention">{{ $part['text'] }}</a>
            @else
                {{ $part['text'] }}
            @endif
        @endforeach
    </p>
    @include('activity.engagement', ['seed' => $item])
</li>
