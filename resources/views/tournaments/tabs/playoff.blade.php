<div class="mt-12 mb-16">
    <h2 class="text-center text-2xl font-bold text-light-green mb-8 tracking-wide">
        Playoff
    </h2>

    <div class="flex gap-10 overflow-x-auto pb-6">

        {{-- 1/16 FINAŁU --}}
        @if(isset($playoffGames['SIXTEEN']))
            <div class="flex flex-col min-w-[220px]">
                <p class="text-center text-sm text-text-muted mb-2">1/16 finału</p>
                <div class="flex flex-col gap-4">
                    @foreach($playoffGames['SIXTEEN'] as $game)
                        @include('tournaments.partials.bracket-game', ['game' => $game])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 1/8 FINAŁU --}}
        @if(isset($playoffGames['EIGHT']))
            <div class="flex flex-col min-w-[220px]">
                <p class="text-center text-sm text-text-muted mb-2">1/8 finału</p>
                <div class="flex flex-col gap-4">
                    @foreach($playoffGames['EIGHT'] as $game)
                        @include('tournaments.partials.bracket-game', ['game' => $game])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ĆWIERĆFINAŁY --}}
        @if(isset($playoffGames['QUARTER']))
            <div class="flex flex-col min-w-[220px]">
                <p class="text-center text-sm text-text-muted mb-2">Ćwierćfinały</p>
                <div class="flex flex-col gap-28 justify-center flex-1">
                    @foreach($playoffGames['QUARTER'] as $game)
                        @include('tournaments.partials.bracket-game', ['game' => $game])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- PÓŁFINAŁY --}}
        @if(isset($playoffGames['SEMI']))
            <div class="flex flex-col min-w-[220px]">
                <p class="text-center text-sm text-text-muted mb-2">Półfinały</p>
                <div class="flex flex-col gap-74 justify-center flex-1">
                    @foreach($playoffGames['SEMI'] as $game)
                        @include('tournaments.partials.bracket-game', ['game' => $game])
                    @endforeach
                </div>
            </div>
        @endif

        {{-- FINAŁ --}}
        @if(isset($playoffGames['FINAL']))
            <div class="flex flex-col gap-6 min-w-[240px] justify-center">
                <p class="text-center text-sm text-text-muted mb-2">Finał</p>
                @foreach($playoffGames['FINAL'] as $game)
                    @include('tournaments.partials.bracket-game', ['game' => $game])
                @endforeach

                @if(isset($playoffGames['THIRD']))
                    <p class="text-center text-sm text-text-muted mb-2">
                        Mecz o 3. miejsce
                    </p>

                    @foreach($playoffGames['THIRD'] as $game)
                        @include('tournaments.partials.bracket-game', ['game' => $game])
                    @endforeach
                @endif
            </div>
        @endif
    </div>
</div>
