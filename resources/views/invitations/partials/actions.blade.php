@if(!empty($acceptUrl))
    <form action="{{ $acceptUrl }}" method="POST" class="m-0">
        @csrf
        <button type="submit" class="{{ $acceptClass ?? 'btn btn-mini text-xs py-1 px-2' }}">{{ $acceptLabel ?? 'Akceptuj' }}</button>
    </form>
@endif
@if(!empty($rejectUrl))
    <form action="{{ $rejectUrl }}" method="POST" class="m-0">
        @csrf
        <button type="submit" class="text-xs py-1 px-2 rounded-md border border-accent text-accent hover:bg-accent/10 transition">Odrzuć</button>
    </form>
@endif
