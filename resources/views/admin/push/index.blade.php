@extends('layouts.app')

@section('title', 'Powiadomienia push — panel')

@section('content')
<div class="max-w-3xl mx-auto px-4 pt-6 pb-12">
    <div class="mb-6">
        <a href="{{ route('admin.dashboard') }}" class="text-text-secondary text-sm hover:text-accent">← Panel</a>
        <h1 class="text-xl sm:text-2xl font-semibold text-accent mt-1">Powiadomienia push</h1>
        <p class="text-text-secondary text-sm mt-2">
            Ręczna wysyłka. Żaden push Training nie wychodzi sam. Na tym etapie jedna grupa: wszyscy z tokenem.
        </p>
    </div>

    <div class="card p-5 mb-8">
        <h2 class="text-accent font-semibold mb-4">Nowa wysyłka</h2>
        <form method="POST" action="{{ route('admin.push.store') }}" class="space-y-4"
              onsubmit="return confirm('Na pewno wysłać to powiadomienie?');">
            @csrf
            <div>
                <label class="block text-text-secondary text-sm mb-1" for="title">Tytuł</label>
                <input id="title" name="title" type="text" maxlength="120" required
                       value="{{ old('title') }}" class="input w-full">
                @error('title') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-text-secondary text-sm mb-1" for="body">Treść</label>
                <textarea id="body" name="body" rows="4" maxlength="500" required class="input w-full">{{ old('body') }}</textarea>
                @error('body') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-text-secondary text-sm mb-1">Odbiorcy</label>
                <div class="space-y-2 text-sm text-text">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="mode" value="all" @checked(old('mode', 'all') === 'all')>
                        Wszyscy zarejestrowani z tokenem push
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="mode" value="self" @checked(old('mode') === 'self')>
                        Próba tylko do mnie
                    </label>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-text">
                <input type="checkbox" name="confirm" value="1" @checked(old('confirm'))>
                Potwierdzam wysyłkę
            </label>
            @error('confirm') <p class="text-danger text-xs">{{ $message }}</p> @enderror
            <button type="submit" class="btn btn-primary">Wyślij</button>
        </form>
    </div>

    <div class="card p-5">
        <h2 class="text-accent font-semibold mb-4">Historia wysyłek</h2>
        @forelse($history as $row)
            <div class="border-b border-border/40 py-3 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <p class="text-text font-medium">{{ $row->title }}</p>
                    <p class="text-text-muted text-xs whitespace-nowrap">
                        {{ $row->sent_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    </p>
                </div>
                <p class="text-text-secondary mt-1 whitespace-pre-line">{{ $row->body }}</p>
                <p class="text-text-muted text-xs mt-2">
                    {{ $row->test_only ? 'Próba do siebie' : 'Wszyscy z tokenem' }}
                    · odbiorców: {{ $row->recipient_count }}
                    · {{ $row->admin?->player?->name ?? $row->admin?->email ?? 'admin' }}
                </p>
            </div>
        @empty
            <p class="text-text-muted text-sm">Brak wysyłek.</p>
        @endforelse
    </div>
</div>
@endsection
