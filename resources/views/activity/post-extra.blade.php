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
    <p class="feed-post-body">
        <template x-for="(part, index) in (item.sentenceParts || [{text: item.sentence, url: null}])" :key="index">
            <span><a x-show="part.url" x-cloak class="feed-post-mention" :href="part.url" x-text="part.text"></a><span x-show="!part.url" x-text="part.text"></span></span>
        </template>
    </p>
    @include('activity.engagement', ['seed' => null])
</li>
