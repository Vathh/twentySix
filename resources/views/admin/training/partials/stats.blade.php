@php
    $daily = $stats['daily'] ?? [];
    $maxDaily = max(1, ...array_map(fn ($row) => (int) ($row['completed'] ?? 0), $daily ?: [['completed' => 0]]));
@endphp
<div class="card p-5 mb-6 space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h2 class="text-accent font-semibold">Statystyki</h2>
        <form method="GET" class="flex flex-wrap gap-2 items-end">
            <div>
                <label class="block text-text-secondary text-xs mb-1">Od</label>
                <input type="date" name="from" value="{{ $stats['from'] ?? '' }}" class="input text-sm">
            </div>
            <div>
                <label class="block text-text-secondary text-xs mb-1">Do</label>
                <input type="date" name="to" value="{{ $stats['to'] ?? '' }}" class="input text-sm">
            </div>
            @if(($stats['kind'] ?? null) !== 'event')
                <div>
                    <label class="block text-text-secondary text-xs mb-1">Min. ukończeń</label>
                    <input type="number" min="1" name="minCompletions" value="{{ request('minCompletions') }}" class="input text-sm w-24">
                </div>
                <div>
                    <label class="block text-text-secondary text-xs mb-1">Sort listy</label>
                    <select name="sort" class="input text-sm">
                        <option value="completions" @selected(request('sort', 'completions') === 'completions')>Ukończenia</option>
                        <option value="name" @selected(request('sort') === 'name')>Nazwa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-text-secondary text-xs mb-1">Kierunek</label>
                    <select name="dir" class="input text-sm">
                        <option value="desc" @selected(request('dir', 'desc') === 'desc')>Malejąco</option>
                        <option value="asc" @selected(request('dir') === 'asc')>Rosnąco</option>
                    </select>
                </div>
            @endif
            <button type="submit" class="btn btn-mini">Zastosuj</button>
        </form>
    </div>

    @if(($stats['kind'] ?? null) === 'event')
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-lg border border-border/60 p-3">
                <p class="text-text-secondary text-xs">Ukończone próby</p>
                <p class="text-lg font-semibold text-text">{{ $stats['totals']['completed'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-border/60 p-3">
                <p class="text-text-secondary text-xs">Unikalni uczestnicy</p>
                <p class="text-lg font-semibold text-text">{{ $stats['totals']['uniqueCompletedUsers'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-border/60 p-3">
                <p class="text-text-secondary text-xs">W toku</p>
                <p class="text-lg font-semibold text-text">{{ $stats['totals']['inProgress'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-border/60 p-3">
                <p class="text-text-secondary text-xs">Unieważnione</p>
                <p class="text-lg font-semibold text-text">{{ $stats['totals']['voided'] ?? 0 }}</p>
            </div>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-lg border border-border/60 p-3">
                <p class="text-text-secondary text-xs">Ukończone sesje</p>
                <p class="text-lg font-semibold text-text">{{ $stats['totals']['completed'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-border/60 p-3">
                <p class="text-text-secondary text-xs">Unikalni użytkownicy</p>
                <p class="text-lg font-semibold text-text">{{ $stats['totals']['uniqueUsers'] ?? 0 }}</p>
            </div>
        </div>
    @endif

    <div>
        <h3 class="text-sm font-semibold text-text mb-3">Ukończenia na dzień</h3>
        @if($daily === [])
            <p class="text-text-muted text-sm">Brak danych w zakresie.</p>
        @else
            <div class="flex items-end gap-1 h-32 overflow-x-auto pb-1">
                @foreach($daily as $row)
                    @php
                        $height = (int) round(((int) $row['completed'] / $maxDaily) * 100);
                    @endphp
                    <div class="flex flex-col items-center justify-end min-w-[10px] flex-1 h-full" title="{{ $row['date'] }}: {{ $row['completed'] }}">
                        <span class="text-[10px] text-text-muted mb-1">{{ $row['completed'] }}</span>
                        <div class="w-full bg-accent/80 rounded-t" style="height: {{ max($height, $row['completed'] > 0 ? 4 : 0) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-[10px] text-text-muted mt-1">
                <span>{{ $stats['from'] ?? '' }}</span>
                <span>{{ $stats['to'] ?? '' }}</span>
            </div>
        @endif
    </div>

    @if(($stats['kind'] ?? null) !== 'event')
        <div>
            <h3 class="text-sm font-semibold text-text mb-3">Użytkownicy</h3>
            @forelse(($stats['users'] ?? []) as $row)
                <div class="flex flex-wrap justify-between gap-2 text-sm border-b border-border/40 py-2">
                    <a href="{{ route('admin.users.training', $row['userId']) }}" class="text-accent hover:underline">{{ $row['name'] }}</a>
                    <span class="text-text-secondary">{{ $row['completions'] }} ukończeń</span>
                </div>
            @empty
                <p class="text-text-muted text-sm">Brak ukończeń.</p>
            @endforelse
        </div>
    @endif

    @if(($stats['kind'] ?? null) === 'challenge')
        <div>
            <h3 class="text-sm font-semibold text-text mb-3">Ukończone próby</h3>
            @forelse(($stats['attempts'] ?? []) as $row)
                <div class="border-b border-border/40 py-3 text-sm">
                    <div class="flex flex-wrap justify-between gap-2">
                        <span class="text-text">{{ $row['name'] }}</span>
                        <span class="text-text-secondary">{{ $row['score'] ?? '—' }} · {{ $row['completedAt'] ?? '—' }}</span>
                    </div>
                    @if(!empty($row['stats']))
                        <pre class="mt-2 text-xs text-text-secondary overflow-x-auto whitespace-pre-wrap">{{ json_encode($row['stats'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                    @endif
                </div>
            @empty
                <p class="text-text-muted text-sm">Brak ukończonych prób.</p>
            @endforelse
        </div>
    @endif

    @if(($stats['kind'] ?? null) === 'event')
        <div>
            <h3 class="text-sm font-semibold text-text mb-3">Uczestnicy (ukończone)</h3>
            @forelse(($stats['participants'] ?? []) as $row)
                <div class="border-b border-border/40 py-3 text-sm">
                    <div class="flex flex-wrap justify-between gap-2">
                        <a href="{{ route('admin.users.training', $row['userId']) }}" class="text-accent hover:underline">{{ $row['name'] }}</a>
                        <span class="text-text-secondary">{{ $row['score'] ?? '—' }} · {{ $row['completedAt'] ?? '—' }}</span>
                    </div>
                    @if(!empty($row['stats']))
                        <pre class="mt-2 text-xs text-text-secondary overflow-x-auto whitespace-pre-wrap">{{ json_encode($row['stats'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                    @endif
                </div>
            @empty
                <p class="text-text-muted text-sm">Brak ukończonych prób.</p>
            @endforelse
        </div>
    @endif
</div>
