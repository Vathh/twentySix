@if(!empty($headToHead))
    @php
        $fmtAvg = static fn ($value) => \App\Support\AverageFormat::display($value);
        $streakName = ($headToHead['streak']['side'] ?? null) === 'player1' ? $player1->name : $player2->name;
    @endphp
    <section class="h2h mb-8" aria-label="H2H">
        <h2 class="section-title">H2H</h2>

        @if($headToHead['meetings'] === 0)
            <p class="text-text-muted">Pierwsze spotkanie tych zawodników.</p>
        @else
            @php
                $winsTotal = $headToHead['player1Wins'] + $headToHead['player2Wins'];
                $leftShare = $winsTotal > 0 ? ($headToHead['player1Wins'] / $winsTotal) * 100 : 50;
                $rightShare = 100 - $leftShare;
                $avg1 = $headToHead['player1Average'];
                $avg2 = $headToHead['player2Average'];
                $avg1Leads = $avg1 !== null && ($avg2 === null || (float) $avg1 > (float) $avg2);
                $avg2Leads = $avg2 !== null && ($avg1 === null || (float) $avg2 > (float) $avg1);
            @endphp
            <div class="h2h-card">
                <div class="h2h-record">
                    <p class="h2h-name h2h-name-left">{{ $player1->name }}</p>
                    <span></span>
                    <p class="h2h-name h2h-name-right">{{ $player2->name }}</p>
                    <div
                        class="h2h-bar"
                        role="img"
                        aria-label="Bilans wygranych {{ $headToHead['player1Wins'] }} do {{ $headToHead['player2Wins'] }}"
                    >
                        @if($leftShare > 0)
                            <span class="h2h-bar-left" style="width: {{ $leftShare }}%"></span>
                        @endif
                        @if($rightShare > 0)
                            <span class="h2h-bar-right" style="width: {{ $rightShare }}%"></span>
                        @endif
                    </div>
                    <p @class(['h2h-wins', 'h2h-val-left', 'score-num', 'h2h-lead' => $headToHead['player1Wins'] > $headToHead['player2Wins']])>{{ $headToHead['player1Wins'] }}</p>
                    <p class="h2h-label">wygrane</p>
                    <p @class(['h2h-wins', 'h2h-val-right', 'score-num', 'h2h-lead' => $headToHead['player2Wins'] > $headToHead['player1Wins']])>{{ $headToHead['player2Wins'] }}</p>
                    <p @class(['h2h-legs', 'h2h-val-left', 'score-num', 'h2h-lead' => $headToHead['player1Legs'] > $headToHead['player2Legs']])>{{ $headToHead['player1Legs'] }}</p>
                    <p class="h2h-label">legi</p>
                    <p @class(['h2h-legs', 'h2h-val-right', 'score-num', 'h2h-lead' => $headToHead['player2Legs'] > $headToHead['player1Legs']])>{{ $headToHead['player2Legs'] }}</p>
                    <p @class(['h2h-avg', 'h2h-val-left', 'score-num', 'h2h-lead' => $avg1Leads])>{{ $fmtAvg($avg1) }}</p>
                    <p class="h2h-label">średnia</p>
                    <p @class(['h2h-avg', 'h2h-val-right', 'score-num', 'h2h-lead' => $avg2Leads])>{{ $fmtAvg($avg2) }}</p>
                </div>

                @if($headToHead['streak'] || $headToHead['firstMeetingAt'])
                    <p class="h2h-meta">
                        @if($headToHead['streak'])
                            <span>{{ $streakName }} ma serię {{ $headToHead['streak']['label'] }}</span>
                        @endif
                        @if($headToHead['streak'] && $headToHead['firstMeetingAt'])
                            <span class="h2h-meta-sep" aria-hidden="true">·</span>
                        @endif
                        @if($headToHead['firstMeetingAt'])
                            <span>Pierwsze spotkanie: {{ $headToHead['firstMeetingAt'] }}</span>
                        @endif
                    </p>
                @endif

                @if($headToHead['recent'] === [])
                    <p class="h2h-empty">Brak wcześniejszych spotkań poza tym meczem.</p>
                @else
                    <h3 class="h2h-list-title">Ostatnie spotkania</h3>
                    <div class="h2h-list">
                        @foreach($headToHead['recent'] as $meeting)
                            <a href="{{ $meeting['url'] }}" class="h2h-match">
                                <span class="h2h-date">{{ $meeting['dateFormatted'] ?? '—' }}</span>
                                <span class="h2h-center score-num">
                                    <span class="h2h-cell-avg">{{ $fmtAvg($meeting['player1Average']) }}</span>
                                    <span @class(['h2h-cell-score', 'h2h-score-win' => $meeting['winnerSide'] === 'player1'])>{{ $meeting['player1Score'] }}</span>
                                    <span class="h2h-score-sep">:</span>
                                    <span @class(['h2h-cell-score', 'h2h-score-win' => $meeting['winnerSide'] === 'player2'])>{{ $meeting['player2Score'] }}</span>
                                    <span class="h2h-cell-avg h2h-cell-avg-right">{{ $fmtAvg($meeting['player2Average']) }}</span>
                                </span>
                                <span class="h2h-place">{{ $meeting['placeLabel'] }}</span>
                            </a>
                        @endforeach
                    </div>
                    <div
                        x-data="headToHeadRecent(@js([
                            'url' => route('games.head-to-head', ['type' => $kind, 'id' => $gameId]),
                            'offset' => count($headToHead['recent']),
                            'hasMore' => (bool) ($headToHead['recentHasMore'] ?? false),
                        ]))"
                    >
                        <template x-for="meeting in extra" x-bind:key="meeting.kind + '-' + meeting.id">
                            <a class="h2h-match" x-bind:href="meeting.url">
                                <span class="h2h-date" x-text="meeting.dateFormatted || '—'"></span>
                                <span class="h2h-center score-num">
                                    <span class="h2h-cell-avg" x-text="formatAverage(meeting.player1Average)"></span>
                                    <span class="h2h-cell-score" x-bind:class="meeting.winnerSide === 'player1' ? 'h2h-score-win' : ''" x-text="meeting.player1Score"></span>
                                    <span class="h2h-score-sep">:</span>
                                    <span class="h2h-cell-score" x-bind:class="meeting.winnerSide === 'player2' ? 'h2h-score-win' : ''" x-text="meeting.player2Score"></span>
                                    <span class="h2h-cell-avg h2h-cell-avg-right" x-text="formatAverage(meeting.player2Average)"></span>
                                </span>
                                <span class="h2h-place" x-text="meeting.placeLabel"></span>
                            </a>
                        </template>
                        <p class="h2h-more-error" x-show="error" x-cloak x-text="error"></p>
                        @if($headToHead['recentHasMore'] ?? false)
                            <button
                                type="button"
                                class="h2h-more"
                                x-show="hasMore"
                                x-on:click="loadMore()"
                                x-bind:disabled="loading"
                            >
                                <span x-text="loading ? 'Wczytywanie…' : 'Załaduj więcej'">Załaduj więcej</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </section>
@endif
