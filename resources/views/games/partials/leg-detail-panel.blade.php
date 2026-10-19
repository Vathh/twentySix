@php
    /** @var array{leg: object, visits: mixed, playerStats: mixed, legInSetNumber?: int} $block */
    $legLabel = $block['legInSetNumber'] ?? $block['leg']->leg_number;
@endphp
<div
    class="bg-bg-deep rounded-lg p-4 border border-border text-text"
    role="tabpanel"
    x-show="{{ $showExpression }}"
    x-cloak
>
    <h3 class="font-semibold text-accent mb-2">
        Leg {{ $legLabel }}
        @if($block['leg']->finished_at)
            <span class="text-text font-normal">— zwycięzca: {{ $block['leg']->winner_id === $player1->id ? $player1->name : $player2->name }}</span>
        @else
            <span class="text-accent text-sm font-normal">(w trakcie)</span>
        @endif
    </h3>

    @if($block['playerStats']->isNotEmpty())
        <table class="w-full text-xs mb-3 text-text">
            <thead>
            <tr class="text-text-muted">
                <th class="text-left py-1 font-medium">Gracz</th>
                <th class="text-center font-medium">Śr. leg</th>
                <th class="text-center font-medium">Śr. 9 lotek</th>
                <th class="text-center font-medium">Max wiz.</th>
                <th class="text-center font-medium">Double</th>
            </tr>
            </thead>
            <tbody class="text-text-secondary">
            @foreach($block['playerStats'] as $stat)
                <tr class="border-b border-border/40">
                    <td class="py-1.5">{{ $stat->player->name ?? $stat->player_id }}</td>
                    <td class="text-center py-1.5">{{ \App\Support\AverageFormat::display($stat->leg_average) }}</td>
                    <td class="text-center py-1.5">{{ \App\Support\AverageFormat::display($stat->first_nine_average ?? $stat->leg_average) }}</td>
                    <td class="text-center py-1.5">{{ $stat->highest_visit ?? '—' }}</td>
                    <td class="text-center py-1.5">
                        @if($stat->double_tracked && $stat->double_attempts)
                            {{ $stat->double_successes }}/{{ $stat->double_attempts }}
                        @else
                            <span class="text-text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($block['visits']->isNotEmpty())
        @php
            $visitRows = \App\Support\GameScoring\H2hLegVisitRows::fromVisits(
                $block['visits'],
                (int) $player1->id,
                (int) $player2->id,
            );
        @endphp
        <div class="referee-leg-visits referee-leg-visits-static">
            <div class="referee-leg-visits-names">
                <span class="truncate">{{ $player1->name }}</span>
                <span class="truncate">{{ $player2->name }}</span>
            </div>
            <div class="referee-leg-visits-body">
                <table>
                    <thead>
                    <tr>
                        <th>Rzucone</th>
                        <th>Pozostało</th>
                        <th class="is-darts">Lotki</th>
                        <th>Pozostało</th>
                        <th>Rzucone</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($visitRows as $row)
                        <tr>
                            <td @class(['is-bust' => $row['leftBust']])>{{ $row['leftThrown'] }}</td>
                            <td>{{ $row['leftRemaining'] }}</td>
                            <td class="is-darts">{{ $row['darts'] }}</td>
                            <td>{{ $row['rightRemaining'] }}</td>
                            <td @class(['is-bust' => $row['rightBust']])>{{ $row['rightThrown'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Brak podejść w tym legu</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <p class="text-text-muted text-sm">Brak zapisanych wizyt.</p>
    @endif
</div>
