@php
    use App\Support\Tournament\PlayoffRoundLabel;

    $consolationPlayoffGames = $consolationPlayoffGames ?? [];
    $roundKeys = array_keys($playoffGames);
    $isDe = collect($roundKeys)->contains(
        fn ($k) => preg_match('/^W\d+$/', $k) || preg_match('/^L\d+$/', $k) || in_array($k, ['GF', 'GF2'], true)
    );
    $hasConsolation = $consolationPlayoffGames !== [];

    $sortDeRoundKeys = static function (array $keys, string $prefix): array {
        return collect($keys)
            ->filter(fn ($k) => preg_match('/^'.$prefix.'\d+$/', $k))
            ->sortBy(fn ($k) => (int) substr($k, 1))
            ->values()
            ->all();
    };

    $buildRounds = static function (array $keys, array $gamesByRound): array {
        $rounds = [];
        foreach ($keys as $key) {
            if (! isset($gamesByRound[$key]) || count($gamesByRound[$key]) === 0) {
                continue;
            }
            $games = collect($gamesByRound[$key])
                ->sortBy(fn ($game) => $game->slot ?? $game->id ?? 0)
                ->values()
                ->all();
            $rounds[] = [
                'key' => $key,
                'label' => PlayoffRoundLabel::label($key),
                'games' => $games,
            ];
        }

        return $rounds;
    };

    $seRoundOrder = ['SIXTYFOUR', 'THIRTYTWO', 'SIXTEEN', 'EIGHT', 'QUARTER', 'SEMI', 'THIRD', 'FINAL'];
    $playoffLiveEnabled = $tournament->status === \App\Enums\TournamentStatus::PLAYOFF;
@endphp

<div
    class="mt-12 mb-16"
    @if($playoffLiveEnabled)
        x-data="tournamentPlayoffLive(@js([
            'channel' => 'tournament.'.$tournament->id,
            'snapshotUrl' => route('tournaments.playoff-live', $tournament->id),
            'urls' => [
                'showBase' => url('/games/playoff'),
                'liveBase' => url('/games/playoff'),
            ],
            'reverb' => \App\Support\Broadcasting\ReverbClientConfig::forWeb(),
        ]))"
    @endif
>
    <div class="flex flex-wrap items-center justify-center gap-3 mb-8">
        <h2 class="text-center page-title tracking-wide mb-0">
            {{ $bracketHeading ?? 'Playoff' }}
        </h2>
        @if($playoffLiveEnabled)
            <span
                class="px-2 py-0.5 rounded text-xs font-semibold bg-accent/25 text-accent"
                x-text="connectionLabel()"
            >Łączenie…</span>
        @endif
    </div>

    @if($isDe)
        {{-- Double elimination: WB / LB / Grand Final --}}
        <div class="space-y-12">
            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Drabinka wygranych</h3>
                @include('tournaments.partials.bracket-tree', [
                    'rounds' => $buildRounds($sortDeRoundKeys($roundKeys, 'W'), $playoffGames),
                ])
            </div>

            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Drabinka przegranych</h3>
                @include('tournaments.partials.bracket-tree', [
                    'rounds' => $buildRounds($sortDeRoundKeys($roundKeys, 'L'), $playoffGames),
                ])
            </div>

            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Grand Final</h3>
                @include('tournaments.partials.bracket-tree', [
                    'rounds' => $buildRounds(['GF', 'GF2'], $playoffGames),
                ])
            </div>
        </div>
    @elseif($hasConsolation)
        <div class="space-y-12">
            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Drabinka główna</h3>
                @include('tournaments.partials.se-bracket', [
                    'gamesByRound' => $playoffGames,
                    'buildRounds' => $buildRounds,
                    'seRoundOrder' => $seRoundOrder,
                ])
            </div>
            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Drabinka pocieszenia</h3>
                @include('tournaments.partials.se-bracket', [
                    'gamesByRound' => $consolationPlayoffGames,
                    'buildRounds' => $buildRounds,
                    'seRoundOrder' => $seRoundOrder,
                ])
            </div>
        </div>
    @else
        @include('tournaments.partials.se-bracket', [
            'gamesByRound' => $playoffGames,
            'buildRounds' => $buildRounds,
            'seRoundOrder' => $seRoundOrder,
        ])
    @endif
</div>
