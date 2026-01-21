<div class="mt-12 mb-16">
    <h2 class="text-center text-2xl font-bold text-light-green mb-8 tracking-wide">
        Playoff
    </h2>

    <div class="flex gap-10 overflow-x-auto">

        {{-- 1/8 FINAŁU --}}
        @if(isset($playoffGames['EIGHT']))
            <div class="flex flex-col gap-4 min-w-[220px]">
                <p class="text-center text-sm text-text-muted mb-2">1/8 finału</p>

                @foreach($playoffGames['EIGHT'] as $game)
                    @include('tournaments.partials.bracket-game', ['game' => $game])
                @endforeach
            </div>
        @endif

        {{-- ĆWIERĆFINAŁY --}}
        @if(isset($playoffGames['QUARTER']))
            <div class="flex flex-col gap-8 min-w-[220px] justify-center">
                <p class="text-center text-sm text-text-muted mb-2">Ćwierćfinały</p>

                @foreach($playoffGames['QUARTER'] as $game)
                    @include('tournaments.partials.bracket-game', ['game' => $game])
                @endforeach
            </div>
        @endif

        {{-- PÓŁFINAŁY --}}
        @if(isset($playoffGames['SEMI']))
            <div class="flex flex-col gap-16 min-w-[220px] justify-center">
                <p class="text-center text-sm text-text-muted mb-2">Półfinały</p>

                @foreach($playoffGames['SEMI'] as $game)
                    @include('tournaments.partials.bracket-game', ['game' => $game])
                @endforeach
            </div>
        @endif

        {{-- FINAŁ --}}
        @if(isset($playoffGames['FINAL']))
            <div class="flex flex-col gap-6 min-w-[240px] justify-center">
                <p class="text-center text-sm text-text-muted mb-2">Finał</p>

                @foreach($playoffGames['FINAL'] as $game)
                    @include('tournaments.partials.bracket-game', ['game' => $game])
                @endforeach

                {{-- MECZ O 3. MIEJSCE --}}
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
