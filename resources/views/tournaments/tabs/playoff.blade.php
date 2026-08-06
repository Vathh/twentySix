@php
    $roundKeys = array_keys($playoffGames);
    $isDe = collect($roundKeys)->contains(
        fn ($k) => preg_match('/^W\d+$/', $k) || preg_match('/^L\d+$/', $k) || in_array($k, ['GF', 'GF2'], true)
    );
@endphp

<div class="mt-12 mb-16">
    <h2 class="text-center page-title mb-8 tracking-wide">
        Playoff
    </h2>

    @if($isDe)
        {{-- Double elimination: dwie drabinki + GF --}}
        <div class="space-y-12">
            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Drabinka wygranych</h3>
                <div class="flex gap-10 overflow-x-auto pb-6">
                    @foreach($playoffGames as $round => $games)
                        @continue(! preg_match('/^W\d+$/', $round))
                        <div class="flex flex-col min-w-[220px]">
                            <p class="text-center text-sm text-text-muted mb-2">{{ \App\Support\Tournament\PlayoffRoundLabel::label($round) }}</p>
                            <div class="flex flex-col gap-4">
                                @foreach($games as $game)
                                    @include('tournaments.partials.bracket-game', ['game' => $game])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Drabinka przegranych</h3>
                <div class="flex gap-10 overflow-x-auto pb-6">
                    @foreach($playoffGames as $round => $games)
                        @continue(! preg_match('/^L\d+$/', $round))
                        <div class="flex flex-col min-w-[220px]">
                            <p class="text-center text-sm text-text-muted mb-2">{{ \App\Support\Tournament\PlayoffRoundLabel::label($round) }}</p>
                            <div class="flex flex-col gap-4">
                                @foreach($games as $game)
                                    @include('tournaments.partials.bracket-game', ['game' => $game])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="text-center text-lg font-semibold text-accent mb-4">Grand Final</h3>
                <div class="flex gap-10 overflow-x-auto pb-6 justify-center">
                    @foreach(['GF', 'GF2'] as $round)
                        @isset($playoffGames[$round])
                            <div class="flex flex-col min-w-[240px]">
                                <p class="text-center text-sm text-text-muted mb-2">{{ \App\Support\Tournament\PlayoffRoundLabel::label($round) }}</p>
                                @foreach($playoffGames[$round] as $game)
                                    @include('tournaments.partials.bracket-game', ['game' => $game])
                                @endforeach
                            </div>
                        @endisset
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="flex gap-10 overflow-x-auto pb-6">

            @if(isset($playoffGames['SIXTYFOUR']))
                <div class="flex flex-col min-w-[220px]">
                    <p class="text-center text-sm text-text-muted mb-2">1/64 finału</p>
                    <div class="flex flex-col gap-4">
                        @foreach($playoffGames['SIXTYFOUR'] as $game)
                            @include('tournaments.partials.bracket-game', ['game' => $game])
                        @endforeach
                    </div>
                </div>
            @endif

            @if(isset($playoffGames['THIRTYTWO']))
                <div class="flex flex-col min-w-[220px]">
                    <p class="text-center text-sm text-text-muted mb-2">1/32 finału</p>
                    <div class="flex flex-col gap-4">
                        @foreach($playoffGames['THIRTYTWO'] as $game)
                            @include('tournaments.partials.bracket-game', ['game' => $game])
                        @endforeach
                    </div>
                </div>
            @endif

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

            @if(isset($playoffGames['SEMI']))
                <div class="flex flex-col min-w-[220px]">
                    <p class="text-center text-sm text-text-muted mb-2">Półfinały</p>
                    <div class="flex flex-col gap-72 justify-center flex-1">
                        @foreach($playoffGames['SEMI'] as $game)
                            @include('tournaments.partials.bracket-game', ['game' => $game])
                        @endforeach
                    </div>
                </div>
            @endif

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
    @endif
</div>
