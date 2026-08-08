@php
    use App\Support\Tournament\PlayoffRoundLabel;

    $roundKeys = array_keys($playoffGames);
    $isDe = collect($roundKeys)->contains(
        fn ($k) => preg_match('/^W\d+$/', $k) || preg_match('/^L\d+$/', $k) || in_array($k, ['GF', 'GF2'], true)
    );

    $sortDeRoundKeys = static function (array $keys, string $prefix): array {
        return collect($keys)
            ->filter(fn ($k) => preg_match('/^'.$prefix.'\d+$/', $k))
            ->sortBy(fn ($k) => (int) substr($k, 1))
            ->values()
            ->all();
    };

    $buildRounds = static function (array $keys) use ($playoffGames): array {
        $rounds = [];
        foreach ($keys as $key) {
            if (! isset($playoffGames[$key]) || count($playoffGames[$key]) === 0) {
                continue;
            }
            $games = collect($playoffGames[$key])
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

    $seRoundOrder = ['SIXTYFOUR', 'THIRTYTWO', 'SIXTEEN', 'EIGHT', 'QUARTER', 'SEMI', 'FINAL'];
@endphp

<div class="mt-12 mb-16">
    <h2 class="text-center page-title mb-8 tracking-wide">
        {{ $bracketHeading ?? 'Playoff' }}
    </h2>

    @if($isDe)
        {{-- Double elimination: WB / LB / Grand Final --}}
        <div class="space-y-12">
            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Drabinka wygranych</h3>
                @include('tournaments.partials.bracket-tree', [
                    'rounds' => $buildRounds($sortDeRoundKeys($roundKeys, 'W')),
                ])
            </div>

            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Drabinka przegranych</h3>
                @include('tournaments.partials.bracket-tree', [
                    'rounds' => $buildRounds($sortDeRoundKeys($roundKeys, 'L')),
                ])
            </div>

            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Grand Final</h3>
                @include('tournaments.partials.bracket-tree', [
                    'rounds' => $buildRounds(['GF', 'GF2']),
                ])
            </div>
        </div>
    @else
        @php
            $seRounds = $buildRounds($seRoundOrder);
            $thirdGames = $playoffGames['THIRD'] ?? null;
        @endphp

        <div class="flex items-stretch gap-8 sm:gap-10 overflow-x-auto pb-6">
            @foreach($seRounds as $round)
                <div @class([
                    'bracket-round flex flex-col',
                    'min-w-[240px]' => $round['key'] === 'FINAL',
                    'min-w-[220px]' => $round['key'] !== 'FINAL',
                ])>
                    <p class="text-center text-sm text-text-muted mb-2 shrink-0 h-6 leading-6">
                        {{ $round['label'] }}
                    </p>
                    <div class="bracket-round-slots flex flex-col flex-1">
                        @foreach($round['games'] as $game)
                            <div class="bracket-slot flex flex-1 items-center py-1.5">
                                <div class="w-full">
                                    @include('tournaments.partials.bracket-game', ['game' => $game])
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($round['key'] === 'FINAL' && $thirdGames)
                        <div class="pt-8 shrink-0">
                            <p class="text-center text-sm text-text-muted mb-2">
                                Mecz o 3. miejsce
                            </p>
                            @foreach($thirdGames as $game)
                                @include('tournaments.partials.bracket-game', ['game' => $game])
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
