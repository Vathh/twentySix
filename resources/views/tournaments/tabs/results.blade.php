<div class="table-wrap mt-10">
    <p class="text-center mb-3 text-text-secondary">Wyniki</p>
    <table class="table-surface">
        <thead>
        <tr>
            <th class="px-2 py-2 text-center w-16">Miejsce</th>
            <th class="px-3 py-2 text-left">Zawodnik</th>
            @if($showPointsColumn ?? false)
                <th class="px-2 py-2 text-center">Punkty</th>
            @endif
            @if($showStageColumn ?? true)
                <th class="px-2 py-2 text-center">Etap</th>
            @endif
        </tr>
        </thead>

        <tbody class="divide-y divide-border">
        @php
            $colCount = 2
                + (($showPointsColumn ?? false) ? 1 : 0)
                + (($showStageColumn ?? true) ? 1 : 0);
        @endphp
        @forelse($results as $index => $result)
            <tr class="hover:bg-bg-elevated-hover transition">
                <td class="px-2 py-2 text-center tabular-nums">
                    @if($index === 0 || ($result['place'] ?? null) !== ($results[$index - 1]['place'] ?? null))
                        {{ $result['place'] ?? '—' }}
                    @endif
                </td>
                <td class="px-3 py-2 font-medium text-text whitespace-nowrap">
                    @if($result['player']->userId)
                        <a href="{{ route('players.show', $result['player']->id) }}" class="text-text hover:text-accent hover:underline transition-colors">
                            {{ $result['player']->name }}
                        </a>
                    @else
                        {{ $result['player']->name }}
                    @endif
                </td>
                @if($showPointsColumn ?? false)
                    <td class="px-2 py-2 text-center tabular-nums">{{ $result['points'] ?? '—' }}</td>
                @endif
                @if($showStageColumn ?? true)
                    <td class="px-2 py-2 text-center flex-wrap">{{ $result['stage']?->label() ?? '—' }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colCount }}"
                    class="px-3 py-4 text-center text-text-muted">
                    Brak wyników — pojawią się po odpadnięciu zawodników z turnieju.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
