@php
    $groupCount = $groupNumbers->count();
    $boardCols = match (true) {
        $groupCount <= 1 => 1,
        $groupCount === 2 => 2,
        $groupCount === 3 => 3,
        $groupCount === 4 => 2,
        $groupCount <= 6 => 3,
        $groupCount <= 12 => 4,
        default => 5,
    };
    $columnLabel = static function (string $name): string {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $label = $parts === [] ? '' : (count($parts) > 1 ? $parts[array_key_last($parts)] : $parts[0]);
        if (mb_strlen($label) > 9) {
            return mb_substr($label, 0, 8).'…';
        }

        return $label;
    };
@endphp

@if($groupCount === 0)
    <p class="board-waiting">Grupy pojawią się po starcie turnieju.</p>
@else
    <div class="board-groups" style="--board-cols: {{ $boardCols }}">
        @foreach($groupNumbers as $number)
            @php
                $highlight = $groupPlayoffHighlights[$number] ?? null;
                $groupComplete = (bool) ($highlight['complete'] ?? false);
                $advanceCount = (int) ($highlight['advanceCount'] ?? 0);
                $advancingIds = $highlight['advancingPlayerIds'] ?? [];
                $roster = collect($players[$number] ?? [])
                    ->sortBy(fn ($player) => sprintf(
                        '%05d-%s',
                        (int) ($groupStandings[$number][$player->id]->place ?? 999),
                        $player->name,
                    ))
                    ->values()
                    ->all();
            @endphp
            <section class="board-group" data-group-number="{{ $number }}">
                <h2 class="board-group-title">Grupa {{ $number }}</h2>
                <p
                    @class([
                        'board-advance',
                        'hidden' => ! ($groupComplete && $advanceCount > 0),
                    ])
                    data-group-advance-hint
                >
                    <span data-group-advance-label>
                        Awans: {{ $advanceCount }}
                        {{ $advanceCount === 1 ? 'pozycja' : 'pozycje' }}
                    </span>
                </p>
                <table class="board-table">
                    <thead>
                    <tr>
                        <th class="is-name">Zawodnik</th>
                        @foreach($roster as $player)
                            <th title="{{ $player->name }}">{{ $columnLabel($player->name) }}</th>
                        @endforeach
                        <th title="Punkty">Pkt</th>
                        <th title="Miejsce">Poz.</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($roster as $rowPlayer)
                        @php
                            $advances = $groupComplete
                                && in_array((int) $rowPlayer->id, array_map('intval', $advancingIds), true);
                            $standing = $groupStandings[$number][$rowPlayer->id] ?? null;
                        @endphp
                        <tr
                            data-group-row-player-id="{{ $rowPlayer->id }}"
                            class="{{ $advances ? 'bg-success-muted border-l-2 border-l-success' : '' }}"
                        >
                            <td class="is-name" title="{{ $rowPlayer->name }}">
                                {{ $rowPlayer->name }}
                                <span data-playoff-badge aria-hidden="true" @if(! $advances) hidden @endif>Playoff</span>
                            </td>
                            @foreach($roster as $columnPlayer)
                                @if($rowPlayer->id === $columnPlayer->id)
                                    <td class="is-diagonal">·</td>
                                @else
                                    @php
                                        $cellGame = $games[$number][$rowPlayer->id][$columnPlayer->id] ?? null;
                                    @endphp
                                    @if($cellGame === null)
                                        <td>—</td>
                                    @else
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
                                        @endphp
                                        <td
                                            class="{{ $isLive ? 'group-cell-live' : '' }}"
                                            data-group-game-id="{{ $cellGame->id }}"
                                            data-row-player-id="{{ $rowPlayer->id }}"
                                            @if($cellGame->sequence) data-sequence="{{ $cellGame->sequence }}" @endif
                                        >
                                            <a
                                                href="{{ $href }}"
                                                data-group-game-link
                                                @if($cellGame->sequence && ! $isFinished && ! $isLive)
                                                    class="inline-flex justify-center hover:opacity-80"
                                                    title="Mecz {{ $cellGame->sequence }}"
                                                @elseif($isLive)
                                                    class="inline-flex flex-col items-center leading-tight text-accent hover:underline"
                                                    title="Podgląd na żywo"
                                                @elseif($isFinished)
                                                    class="inline-flex flex-col items-center leading-tight text-accent hover:underline"
                                                @else
                                                    class="text-text-muted hover:text-accent hover:underline"
                                                    title="Ustaw wynik / walkower"
                                                @endif
                                            >@if($cellGame->sequence && ! $isFinished && ! $isLive)<span class="sequence-badge">{{ $cellGame->sequence }}</span>@else<span class="inline-flex items-center justify-center gap-1">@if($isLive)<span class="group-live-dot" aria-hidden="true"></span>@endif<span>{{ $scoreText }}</span></span>@endif</a>
                                        </td>
                                    @endif
                                @endif
                            @endforeach
                            <td data-group-standing="{{ $number }}" data-player-id="{{ $rowPlayer->id }}" data-standing-field="points">{{ $standing?->points ?? 0 }}</td>
                            <td class="is-place" data-group-standing="{{ $number }}" data-player-id="{{ $rowPlayer->id }}" data-standing-field="place">{{ $standing?->place ?? '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>
@endif
