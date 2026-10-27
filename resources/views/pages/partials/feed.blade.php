@if(empty($feed['items']))
    <p class="text-sm text-text-secondary text-center">Na razie cisza. Zagraj albo dołącz do organizacji, a historie pojawią się tutaj.</p>
@else
    <ul class="flex flex-col gap-3">
        @foreach($feed['items'] as $item)
            @include('activity.post', ['item' => $item])
        @endforeach
    </ul>
    @if($feed['nextCursor'])
        <div class="mt-4 flex justify-center">
            <a href="{{ route('pages.home', ['cursor' => $feed['nextCursor']]) }}" class="btn btn-secondary">Starsze</a>
        </div>
    @endif
@endif
<p class="mt-4 text-center">
    <a href="{{ route('settings.feed.edit') }}" class="text-xs text-accent">Ustawienia tablicy</a>
</p>
