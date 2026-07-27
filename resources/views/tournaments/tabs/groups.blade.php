@php
    $groupsLiveEnabled = $tournament->status === \App\Enums\TournamentStatus::GROUP;
@endphp
<div
    @if($groupsLiveEnabled)
        x-data="tournamentGroupsLive(@js([
            'channel' => 'tournament.'.$tournament->id,
            'snapshotUrl' => route('tournaments.groups-live', $tournament->id),
            'urls' => [
                'showBase' => url('/games/group'),
                'liveBase' => url('/games/group'),
            ],
            'reverb' => [
                'key' => (string) config('broadcasting.connections.reverb.key'),
                'host' => (string) env('REVERB_HOST', '127.0.0.1'),
                'port' => (int) env('REVERB_PORT', 8080),
                'scheme' => (string) env('REVERB_SCHEME', 'http'),
            ],
        ]))"
        x-init="init()"
    @endif
>
    <div class="flex flex-wrap items-center justify-center gap-3 mb-2 mt-4">
        <h2 class="text-center page-title tracking-wide mb-0">
            Grupy
        </h2>
        @if($groupsLiveEnabled)
            <span
                class="px-2 py-0.5 rounded text-xs font-semibold bg-accent/25 text-accent"
                x-text="connectionLabel()"
            >Łączenie…</span>
        @endif
    </div>

@foreach($groupNumbers as $number)
    @php
        $highlight = $groupPlayoffHighlights[$number] ?? null;
        $groupComplete = (bool) ($highlight['complete'] ?? false);
        $advanceCount = (int) ($highlight['advanceCount'] ?? 0);
        $advancingIds = $highlight['advancingPlayerIds'] ?? [];
    @endphp
    <div class="rounded-lg p-4 bg-bg-deep border border-border mt-10" data-group-number="{{ $number }}">
        <p class="text-center mb-3">Grupa {{ $number }}</p>
        <p
            @class([
                'text-center text-xs text-text-muted mb-3',
                'hidden' => ! ($groupComplete && $advanceCount > 0),
            ])
            data-group-advance-hint
        >
            <span data-group-advance-label>
                Awans do playoff: {{ $advanceCount }}
                {{ $advanceCount === 1 ? 'pozycja' : 'pozycje' }}
            </span>
            <span class="text-accent">· wyróżnione wiersze</span>
        </p>
        <div class="overflow-x-auto -mx-1 px-1">
            <table class="table-surface">
                <thead>
                <tr>
                    <th class="px-3 py-2 text-left">Zawodnik</th>
                    @foreach($players[$number] as $player)
                        <th class="px-2 py-2 text-center">{{ $player->name }}</th>
                    @endforeach
                    <th class="px-2 py-2 text-center">W</th>
                    <th class="px-2 py-2 text-center">L</th>
                    <th class="px-2 py-2 text-center">Wynik</th>
                    <th class="px-2 py-2 text-center">Pkt</th>
                    <th class="px-2 py-2 text-center">Pozycja</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-border">
                @foreach($players[$number] as $rowPlayer)
                    @php
                        $advances = $groupComplete
                            && in_array((int) $rowPlayer->id, array_map('intval', $advancingIds), true);
                    @endphp
                    <tr
                        data-group-row-player-id="{{ $rowPlayer->id }}"
                        class="transition {{ $advances
                            ? 'bg-success-muted hover:bg-success-muted/80 border-l-2 border-l-success'
                            : 'hover:bg-bg-elevated-hover' }}"
                    >
                        <td class="px-3 py-2 font-medium text-text whitespace-nowrap">
                            @if($rowPlayer->userId)
                                <a href="{{ route('players.show', $rowPlayer->id) }}" class="text-text hover:text-accent hover:underline transition-colors">
                                    {{ $rowPlayer->name }}
                                </a>
                            @else
                                {{ $rowPlayer->name }}
                            @endif
                            <span
                                data-playoff-badge
                                class="ml-2 inline-block align-middle text-[10px] uppercase tracking-wide font-semibold text-accent border border-success/40 rounded px-1.5 py-0.5 {{ $advances ? '' : 'hidden' }}"
                                title="Awans do playoff"
                            >Playoff</span>
                        </td>

                        @foreach($players[$number] as $columnPlayer)
                            @if($rowPlayer->id === $columnPlayer->id)
                                <td class="px-2 py-2 text-center {{ $advances ? 'bg-success-muted/70' : 'bg-bg' }} text-text-muted">
                                    X
                                </td>
                            @else
                                @php($cellGame = $games[$number][$rowPlayer->id][$columnPlayer->id])
                                @php
                                    $isFinished = $cellGame->isFinished();
                                    $isLive = $cellGame->status === \App\Enums\GameStatus::IN_PROGRESS;
                                    $rowIsP1 = $rowPlayer->id === $cellGame->player1->id;
                                    $scoreText = $isFinished || $isLive
                                        ? ($rowIsP1
                                            ? $cellGame->player1Score.' - '.$cellGame->player2Score
                                            : $cellGame->player2Score.' - '.$cellGame->player1Score)
                                        : '—';
                                    $href = $isLive
                                        ? route('games.live', ['type' => 'group', 'id' => $cellGame->id])
                                        : route('games.show', ['type' => 'group', 'id' => $cellGame->id]);
                                    $linkClass = ($isFinished || $isLive)
                                        ? 'text-accent hover:underline'
                                        : 'text-text-muted hover:text-accent hover:underline';
                                    $title = $isLive ? 'Podgląd na żywo' : ($isFinished ? null : 'Ustaw wynik / walkover');
                                @endphp
                                <td
                                    class="px-2 py-2 text-center {{ (! $isFinished && ! $isLive && $advances) ? 'bg-success-muted/70' : '' }}"
                                    data-group-game-id="{{ $cellGame->id }}"
                                    data-row-player-id="{{ $rowPlayer->id }}"
                                >
                                    <a
                                        href="{{ $href }}"
                                        data-group-game-link
                                        class="{{ $linkClass }}"
                                        @if($title) title="{{ $title }}" @endif
                                    >{{ $scoreText }}</a>
                                </td>
                            @endif
                        @endforeach

                        <td class="px-2 py-2 text-center" data-group-standing="{{ $number }}" data-player-id="{{ $rowPlayer->id }}" data-standing-field="won">{{ $groupStandings[$number][$rowPlayer->id]->gamesWon }}</td>
                        <td class="px-2 py-2 text-center" data-group-standing="{{ $number }}" data-player-id="{{ $rowPlayer->id }}" data-standing-field="lost">{{ $groupStandings[$number][$rowPlayer->id]->gamesLost }}</td>
                        <td class="px-2 py-2 text-center" data-group-standing="{{ $number }}" data-player-id="{{ $rowPlayer->id }}" data-standing-field="diff">{{ $groupStandings[$number][$rowPlayer->id]->matchUnitsDifference }}</td>
                        <td class="px-2 py-2 text-center" data-group-standing="{{ $number }}" data-player-id="{{ $rowPlayer->id }}" data-standing-field="points">{{ $groupStandings[$number][$rowPlayer->id]->points }}</td>
                        <td class="px-2 py-2 text-center font-semibold text-accent" data-group-standing="{{ $number }}" data-player-id="{{ $rowPlayer->id }}" data-standing-field="place">{{ $groupStandings[$number][$rowPlayer->id]->place }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach
</div>
