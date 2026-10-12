@extends('layouts.app')

@section('title', 'Mecz kamerkowy')

@section('content')
    <div class="max-w-3xl mx-auto flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold">Mecz kamerkowy</h1>
            <p class="text-text-muted mt-1">501, pierwszy do 2 legów. Każdy przy swojej tarczy, dwie kamerki.</p>
        </div>

        <section class="rounded-xl border border-border p-4 flex flex-col gap-3">
            <h2 class="font-semibold">Nowy mecz</h2>
            @if(count($friends) === 0)
                <p class="text-text-muted">Zaproś najpierw kogoś do znajomych. Mecz kamerkowy jest tylko między znajomymi.</p>
            @else
                <form method="POST" action="{{ route('webcam.store') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                    @csrf
                    <label class="flex flex-col gap-1 flex-1">
                        <span class="text-sm text-text-muted">Znajomy</span>
                        <select name="friend_user_id" class="rounded-lg border border-border bg-transparent px-3 py-2" required>
                            <option value="">Wybierz…</option>
                            @foreach($friends as $friend)
                                <option value="{{ $friend['userId'] }}">{{ $friend['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary">Załóż mecz</button>
                </form>
            @endif
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="font-semibold">Twoje mecze</h2>
            @forelse($matches as $match)
                <a href="{{ $match['href'] }}" class="rounded-xl border border-border px-4 py-3 flex items-center justify-between gap-3 no-underline hover:border-accent/50">
                    <span>
                        <span class="font-medium">{{ $match['opponentName'] }}</span>
                        <span class="text-text-muted text-sm block">{{ $match['statusLabel'] }}</span>
                    </span>
                    <span class="tabular-nums text-text-muted">{{ $match['youAreHost'] ? $match['hostLegsWon'] : $match['guestLegsWon'] }} : {{ $match['youAreHost'] ? $match['guestLegsWon'] : $match['hostLegsWon'] }}</span>
                </a>
            @empty
                <p class="text-text-muted">Nie masz jeszcze meczu kamerkowego.</p>
            @endforelse
        </section>
    </div>
@endsection
