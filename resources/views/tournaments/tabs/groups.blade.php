<h2 class="text-center text-2xl font-bold text-light-green mb-8 tracking-wide mt-4">
    Grupy
</h2>
@foreach($groupNumbers as $number)
    @php
        $highlight = $groupPlayoffHighlights[$number] ?? null;
        $groupComplete = (bool) ($highlight['complete'] ?? false);
        $advanceCount = (int) ($highlight['advanceCount'] ?? 0);
        $advancingIds = $highlight['advancingPlayerIds'] ?? [];
    @endphp
    <div class="overflow-x-auto rounded-lg p-4  bg-darker-bg border-border mt-10">
        <p class="text-center mb-1">Grupa {{ $number }}</p>
        @if($groupComplete && $advanceCount > 0)
            <p class="text-center text-xs text-text-muted mb-3">
                Awans do playoff: {{ $advanceCount }}
                {{ $advanceCount === 1 ? 'miejsce' : 'miejsca' }}
                <span class="text-light-green">· wyróżnione wiersze</span>
            </p>
        @else
            <div class="mb-3"></div>
        @endif
        <table class="border-collapse text-sm text-text-primary min-w-full">
            <thead>
            <tr class="bg-dark-bg text-text-muted hover:bg-thead-hover transition">
                <th class="px-3 py-2 text-left">Zawodnik</th>
                @foreach($players[$number] as $player)
                    <th class="px-2 py-2 text-center">{{ $player->name }}</th>
                @endforeach
                <th class="px-2 py-2 text-center">W</th>
                <th class="px-2 py-2 text-center">L</th>
                <th class="px-2 py-2 text-center">Wynik</th>
                <th class="px-2 py-2 text-center">Pkt</th>
                <th class="px-2 py-2 text-center">Miejsce</th>
            </tr>
            </thead>

            <tbody class="divide-y divide-border">
            @foreach($players[$number] as $rowPlayer)
                @php
                    $advances = $groupComplete
                        && in_array((int) $rowPlayer->id, array_map('intval', $advancingIds), true);
                @endphp
                <tr class="transition {{ $advances
                    ? 'bg-light-green/15 hover:bg-light-green/25 border-l-2 border-l-light-green'
                    : 'hover:bg-row-hover' }}">
                    <td class="px-3 py-2 font-medium text-text-primary whitespace-nowrap">
                        {{ $rowPlayer->name }}
                        @if($advances)
                            <span
                                class="ml-2 inline-block align-middle text-[10px] uppercase tracking-wide font-semibold text-light-green border border-light-green/40 rounded px-1.5 py-0.5"
                                title="Awans do playoff"
                            >Playoff</span>
                        @endif
                    </td>

                    @foreach($players[$number] as $columnPlayer)
                        @if($rowPlayer->id === $columnPlayer->id)
                            <td class="px-2 py-2 text-center {{ $advances ? 'bg-light-green/10' : 'bg-dark-bg' }} text-text-muted">
                                X
                            </td>
                        @else
                            @if($games[$number][$rowPlayer->id][$columnPlayer->id]->isFinished())
                                @php($cellGame = $games[$number][$rowPlayer->id][$columnPlayer->id])
                                @if($rowPlayer->id === $cellGame->player1->id)
                                    <td class="px-2 py-2 text-center">
                                        <a href="{{ route('games.show', ['type' => 'group', 'id' => $cellGame->id]) }}" class="text-light-green hover:underline">
                                            {{ $cellGame->player1Score }}
                                            -
                                            {{ $cellGame->player2Score }}
                                        </a>
                                    </td>
                                @else
                                    <td class="px-2 py-2 text-center">
                                        <a href="{{ route('games.show', ['type' => 'group', 'id' => $cellGame->id]) }}" class="text-light-green hover:underline">
                                            {{ $cellGame->player2Score }}
                                            -
                                            {{ $cellGame->player1Score }}
                                        </a>
                                    </td>
                                @endif
                            @elseif($games[$number][$rowPlayer->id][$columnPlayer->id]->status === \App\Enums\GameStatus::IN_PROGRESS)
                                @php($cellGame = $games[$number][$rowPlayer->id][$columnPlayer->id])
                                @if($rowPlayer->id === $cellGame->player1->id)
                                    <td class="px-2 py-2 text-center">
                                        <a href="{{ route('games.live', ['type' => 'group', 'id' => $cellGame->id]) }}" class="text-light-orange hover:underline" title="Podgląd na żywo">
                                            {{ $cellGame->player1Score }}
                                            -
                                            {{ $cellGame->player2Score }}
                                        </a>
                                    </td>
                                @else
                                    <td class="px-2 py-2 text-center">
                                        <a href="{{ route('games.live', ['type' => 'group', 'id' => $cellGame->id]) }}" class="text-light-orange hover:underline" title="Podgląd na żywo">
                                            {{ $cellGame->player2Score }}
                                            -
                                            {{ $cellGame->player1Score }}
                                        </a>
                                    </td>
                                @endif
                            @else
                                <td class="px-2 py-2 text-center {{ $advances ? 'bg-light-green/10' : 'bg-dark-bg' }} text-text-muted">
                                    -
                                </td>
                            @endif
                        @endif
                    @endforeach

                    <td class="px-2 py-2 text-center">{{ $groupStandings[$number][$rowPlayer->id]->gamesWon }}</td>
                    <td class="px-2 py-2 text-center">{{ $groupStandings[$number][$rowPlayer->id]->gamesLost }}</td>
                    <td class="px-2 py-2 text-center">{{ $groupStandings[$number][$rowPlayer->id]->matchUnitsDifference }}</td>
                    <td class="px-2 py-2 text-center">{{ $groupStandings[$number][$rowPlayer->id]->points }}</td>
                    <td class="px-2 py-2 text-center font-semibold text-light-green">{{ $groupStandings[$number][$rowPlayer->id]->place }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach
