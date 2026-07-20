<div class="overflow-x-auto rounded-lg p-4  bg-darker-bg border-border mt-10">
    <p class="text-center mb-3">Wyniki</p>
    <table class="border-collapse text-sm text-text-primary min-w-full">
        <thead>
        <tr class="bg-dark-bg text-text-muted hover:bg-thead-hover transition">
            <th class="px-2 py-2 text-center w-16">Miejsce</th>
            <th class="px-3 py-2 text-left">Zawodnik</th>
            @if($showPointsColumn ?? false)
                <th class="px-2 py-2 text-center">Punkty</th>
            @endif
            <th class="px-2 py-2 text-center">Etap</th>
        </tr>
        </thead>

        <tbody class="divide-y divide-border">
        @forelse($results as $index => $result)
            <tr class="hover:bg-row-hover transition">
                <td class="px-2 py-2 text-center tabular-nums">
                    @if($index === 0 || ($result['place'] ?? null) !== ($results[$index - 1]['place'] ?? null))
                        {{ $result['place'] ?? '—' }}
                    @endif
                </td>
                <td class="px-3 py-2 font-medium text-text-primary whitespace-nowrap">
                    {{ $result['player']->name }}
                </td>
                @if($showPointsColumn ?? false)
                    <td class="px-2 py-2 text-center tabular-nums">{{ $result['points'] ?? '—' }}</td>
                @endif
                <td class="px-2 py-2 text-center flex-wrap">{{ $result['stage']?->label() ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ ($showPointsColumn ?? false) ? 4 : 3 }}"
                    class="px-3 py-4 text-center text-text-muted">
                    Brak wyników — pojawią się po odpadnięciu zawodników z turnieju.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
