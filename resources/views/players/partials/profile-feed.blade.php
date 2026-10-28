@php
    $profileFeed = $profileFeed ?? ['items' => [], 'nextCursor' => null];
@endphp

@if(empty($profileFeed['items']))
    <p class="text-sm text-text-secondary text-center">Na tej tablicy nie ma jeszcze historii.</p>
@else
    <ul class="flex flex-col gap-3">
        @foreach($profileFeed['items'] as $item)
            @include('activity.post', ['item' => $item])
        @endforeach
        <template x-for="item in feed.extra" :key="item.id">
            <li class="feed-post" x-data="feedPost(item)">
                <div class="feed-post-head">
                    <a class="feed-post-avatar" x-show="item.header && item.header.url" x-cloak :href="item.header && item.header.url">
                        <span class="player-avatar player-avatar--sm">
                            <img x-show="item.header && item.header.avatarUrl" :src="item.header && item.header.avatarUrl" alt="" class="feed-post-photo">
                            <span class="player-avatar-fallback" x-show="!(item.header && item.header.avatarUrl)" x-text="item.header ? item.header.initials : ''"></span>
                        </span>
                    </a>
                    <span class="feed-post-avatar" x-show="!(item.header && item.header.url)" x-cloak>
                        <span class="player-avatar player-avatar--sm">
                            <img x-show="item.header && item.header.avatarUrl" :src="item.header && item.header.avatarUrl" alt="" class="feed-post-photo">
                            <span class="player-avatar-fallback" x-show="!(item.header && item.header.avatarUrl)" x-text="item.header ? item.header.initials : ''"></span>
                        </span>
                    </span>
                    <div class="min-w-0">
                        <a class="feed-post-name" x-show="item.header && item.header.url" x-cloak :href="item.header && item.header.url" x-text="item.header ? item.header.name : ''"></a>
                        <p class="feed-post-name" x-show="!(item.header && item.header.url)" x-cloak x-text="item.header ? item.header.name : ''"></p>
                        <p class="feed-post-meta">
                            <span x-text="feedAgo(item.occurredAt)"></span>
                            <span> · </span>
                            <span x-text="item.kind ? item.kind.label : 'Historia'"></span>
                        </p>
                    </div>
                </div>
                <p class="feed-post-body" x-text="item.sentence"></p>
                @include('activity.engagement', ['seed' => null])
            </li>
        </template>
    </ul>
    <div class="mt-4 flex justify-center" x-show="feed.nextCursor" x-cloak>
        <button type="button" class="btn btn-secondary" @click="loadOlderFeed()" :disabled="feed.loading">
            <span x-text="feed.loading ? 'Wczytywanie…' : 'Starsze'"></span>
        </button>
    </div>
@endif

@if(!empty($isOwnProfile))
    <p class="mt-4 text-center">
        <a href="{{ route('settings.feed.edit') }}" class="text-xs text-accent">Ustawienia tablicy</a>
    </p>
@endif
