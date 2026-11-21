@extends('layouts.app')

@section('title', 'Trening — '.($user->player?->name ?? $user->email))

@section('content')
@php
    $kindOptions = [
        'exercise' => 'Ćwiczenia',
        'plan' => 'Plany',
        'challenge' => 'Wyzwania',
        'event' => 'Eventy',
    ];
    $selectedKinds = is_array($filters['kinds'] ?? null)
        ? $filters['kinds']
        : (is_string($filters['kinds'] ?? null) && $filters['kinds'] !== ''
            ? explode(',', $filters['kinds'])
            : []);
@endphp
<div class="max-w-6xl mx-auto px-4 pt-6 pb-12">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('admin.users.show', $user->id) }}" class="text-text-secondary text-sm hover:text-accent">← Użytkownik</a>
            <h1 class="text-xl sm:text-2xl font-semibold text-accent mt-1">Trening</h1>
            <p class="text-text-secondary text-sm mt-1">{{ $user->player?->name ?? 'Bez nicku' }} · {{ $user->email }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Ukończone ćwiczenia</p>
            <p class="text-xl font-semibold text-text">{{ $training['completedExerciseCount'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Rekordy</p>
            <p class="text-xl font-semibold text-text">{{ count($training['records']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Odznaki</p>
            <p class="text-xl font-semibold text-text">{{ count($training['badges']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-text-secondary text-xs mb-1">Medale</p>
            <p class="text-xl font-semibold text-text">{{ count($training['medals']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="card p-5">
            <h2 class="text-accent font-semibold mb-4">Rekordy</h2>
            @forelse($training['records'] as $row)
                <div class="flex flex-wrap justify-between gap-2 text-sm border-b border-border/40 py-2">
                    <span class="text-text">{{ $row['name'] }}</span>
                    <span class="text-text-secondary">
                        {{ $row['score'] }}
                        @if(!empty($row['achievedAt']))
                            · {{ \Illuminate\Support\Carbon::parse($row['achievedAt'])->format('Y-m-d') }}
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-text-muted text-sm">Brak rekordów.</p>
            @endforelse
        </div>

        <div class="card p-5">
            <h2 class="text-accent font-semibold mb-4">Odznaki i medale</h2>
            <p class="text-text-secondary text-xs mb-2">Odznaki (na sucho do numeru 13)</p>
            @foreach($training['badges'] as $badge)
                <div class="flex flex-wrap justify-between gap-2 text-sm border-b border-border/40 py-2">
                    <span class="text-text">{{ $badge['name'] }}</span>
                    <span class="text-text-secondary">{{ $badge['levelLabel'] ?? '—' }}</span>
                </div>
            @endforeach
            <p class="text-text-secondary text-xs mt-4 mb-2">Medale eventów</p>
            @forelse($training['medals'] as $medal)
                <div class="flex flex-wrap justify-between gap-2 text-sm border-b border-border/40 py-2">
                    <span class="text-text">{{ $medal['eventName'] ?? $medal['name'] ?? 'Event' }}</span>
                    <span class="text-text-secondary">
                        {{ $medal['color'] ?? '—' }}
                        @if(!empty($medal['awardedAt']))
                            · {{ \Illuminate\Support\Carbon::parse($medal['awardedAt'])->format('Y-m-d') }}
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-text-muted text-sm">Brak medali.</p>
            @endforelse
        </div>
    </div>

    <div class="card p-5">
        <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
            <h2 class="text-accent font-semibold">Historia ukończonych</h2>
            <form method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-text-secondary text-xs mb-1">Rodzaj</label>
                    <select name="kinds" class="input text-sm">
                        <option value="">Wszystkie</option>
                        @foreach($kindOptions as $value => $label)
                            <option value="{{ $value }}" @selected(in_array($value, $selectedKinds, true) || ($filters['kinds'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-text-secondary text-xs mb-1">Zakres</label>
                    <select name="days" class="input text-sm">
                        <option value="">Całość</option>
                        @foreach([7, 30, 90] as $days)
                            <option value="{{ $days }}" @selected((string) ($filters['days'] ?? '') === (string) $days)>{{ $days }} dni</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-mini">Filtruj</button>
            </form>
        </div>

        @forelse($training['history']['items'] as $row)
            <div class="border-b border-border/40 py-3">
                <div class="flex flex-wrap justify-between gap-2 text-sm">
                    <div>
                        <p class="text-text font-medium">{{ $row['name'] }}</p>
                        <p class="text-text-muted text-xs">
                            {{ $row['kindLabel'] ?? $row['kind'] }}
                            · {{ isset($row['completedAt']) ? \Illuminate\Support\Carbon::parse($row['completedAt'])->format('Y-m-d') : '—' }}
                        </p>
                    </div>
                    <span class="text-text font-medium">{{ $row['score'] ?? '—' }}</span>
                </div>
                @if(!empty($row['stats']) && !($row['stats'] instanceof \stdClass && (array) $row['stats'] === []))
                    <pre class="mt-2 text-xs text-text-secondary overflow-x-auto whitespace-pre-wrap">{{ json_encode($row['stats'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                @endif
            </div>
        @empty
            <p class="text-text-muted text-sm">Brak ukończonych sesji treningowych.</p>
        @endforelse

        @if($training['history']['hasMore'])
            <p class="text-text-muted text-xs mt-3">Pokazano pierwsze 20 pozycji. Zawęź filtr daty lub rodzaju, żeby zobaczyć starsze.</p>
        @endif
    </div>
</div>
@endsection
