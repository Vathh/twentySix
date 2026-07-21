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
                    <td class="text-center py-1.5">{{ $stat->leg_average ?? '—' }}</td>
                    <td class="text-center py-1.5">{{ $stat->first_nine_average ?? $stat->leg_average ?? '—' }}</td>
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
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <h4 class="text-sm font-semibold text-accent mb-2">{{ $player1->name }}</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-text">
                        <thead>
                        <tr class="text-text-muted border-b border-border">
                            <th class="text-left py-1 font-medium">#</th>
                            <th class="text-center font-medium">Pkt</th>
                            <th class="text-center font-medium">Zostało</th>
                        </tr>
                        </thead>
                        <tbody class="text-text-secondary">
                        @foreach($block['visits']->where('player_id', $player1->id) as $visit)
                            <tr class="border-b border-border/50 {{ $visit->bust ? 'opacity-60' : '' }}">
                                <td class="py-1.5">{{ $visit->visit_number }}</td>
                                <td class="text-center py-1.5">{{ $visit->bust ? 'Bust' : $visit->score }}</td>
                                <td class="text-center py-1.5">{{ $visit->remaining_after }}</td>
                            </tr>
                        @endforeach
                        @if($block['visits']->where('player_id', $player1->id)->isEmpty())
                            <tr>
                                <td colspan="3" class="py-2 text-text-muted text-center">Brak wizyt</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-accent mb-2">{{ $player2->name }}</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-text">
                        <thead>
                        <tr class="text-text-muted border-b border-border">
                            <th class="text-left py-1 font-medium">#</th>
                            <th class="text-center font-medium">Pkt</th>
                            <th class="text-center font-medium">Zostało</th>
                        </tr>
                        </thead>
                        <tbody class="text-text-secondary">
                        @foreach($block['visits']->where('player_id', $player2->id) as $visit)
                            <tr class="border-b border-border/50 {{ $visit->bust ? 'opacity-60' : '' }}">
                                <td class="py-1.5">{{ $visit->visit_number }}</td>
                                <td class="text-center py-1.5">{{ $visit->bust ? 'Bust' : $visit->score }}</td>
                                <td class="text-center py-1.5">{{ $visit->remaining_after }}</td>
                            </tr>
                        @endforeach
                        @if($block['visits']->where('player_id', $player2->id)->isEmpty())
                            <tr>
                                <td colspan="3" class="py-2 text-text-muted text-center">Brak wizyt</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <p class="text-text-muted text-sm">Brak zapisanych wizyt.</p>
    @endif
</div>
