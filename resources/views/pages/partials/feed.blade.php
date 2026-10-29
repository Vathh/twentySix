@if(empty($feed['items']))
    <p class="text-sm text-text-secondary text-center">Na razie cisza. Zagraj albo dołącz do organizacji, a historie pojawią się tutaj.</p>
@else
    <div x-data="homeFeed(@js(['url' => route('pages.home'), 'nextCursor' => $feed['nextCursor']]))">
        <ul class="flex flex-col gap-3">
            @foreach($feed['items'] as $item)
                @include('activity.post', ['item' => $item])
            @endforeach
            <template x-for="item in extra" :key="item.id">
                @include('activity.post-extra')
            </template>
        </ul>
        <div class="mt-4 text-center text-sm text-text-secondary" data-feed-end x-show="nextCursor || error" x-cloak>
            <span x-show="loading">Wczytywanie…</span>
            <p x-show="error" x-text="error"></p>
            <button type="button" class="btn btn-secondary mt-2" x-show="error" @click="retryOlder()">Spróbuj ponownie</button>
        </div>
    </div>
@endif
<p class="mt-4 text-center">
    <a href="{{ route('settings.feed.edit') }}" class="text-xs text-accent">Ustawienia tablicy</a>
</p>
