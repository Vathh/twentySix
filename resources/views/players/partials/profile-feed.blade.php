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
            @include('activity.post-extra')
        </template>
    </ul>
    <div class="mt-4 text-center text-sm text-text-secondary" data-feed-end x-show="feed.nextCursor || feed.error" x-cloak>
        <span x-show="feed.loading">Wczytywanie…</span>
        <p x-show="feed.error" x-text="feed.error"></p>
        <button type="button" class="btn btn-secondary mt-2" x-show="feed.error" @click="retryOlderFeed()">Spróbuj ponownie</button>
    </div>
@endif

@if(!empty($isOwnProfile))
    <p class="mt-4 text-center">
        <a href="{{ route('settings.feed.edit') }}" class="text-xs text-accent">Ustawienia tablicy</a>
    </p>
@endif
