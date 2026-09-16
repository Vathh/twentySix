{{-- Przegląd: historia meczów --}}
<section>
    <h2 class="text-base font-bold text-accent mb-2.5">Ostatnie mecze</h2>
    <div class="history-panel">
        <p class="history-empty" x-show="gameHistory.items.length === 0">Brak meczów w historii.</p>

        <div class="history-table-wrap" x-show="gameHistory.items.length > 0">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Typ</th>
                        <th>Przeciwnik</th>
                        <th>Wynik</th>
                        <th>Turniej</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(m, i) in gameHistory.items" :key="i">
                        <tr
                            :class="gameUrl(m) ? 'history-row--link' : ''"
                            :tabindex="gameUrl(m) ? 0 : null"
                            :role="gameUrl(m) ? 'link' : null"
                            :aria-label="gameUrl(m) ? ('Szczegóły meczu: ' + (m.opponents || '')) : null"
                            @click="openGame(m)"
                            @keydown.enter.prevent="openGame(m)"
                            @keydown.space.prevent="openGame(m)"
                        >
                            <td class="history-cell-date">
                                <span class="history-date" x-text="historyDate(m.date_formatted)"></span>
                                <span class="history-time" x-text="historyTime(m.date_formatted)"></span>
                            </td>
                            <td>
                                <span class="history-type" :class="'history-type--' + typeTone(m.type)" x-text="typeLabel(m.type)"></span>
                            </td>
                            <td class="history-opponents" x-text="m.opponents || '–'"></td>
                            <td>
                                <div class="history-outcome">
                                    <span
                                        class="history-result"
                                        :class="{
                                            'history-result--win': m.result === 'wygrana',
                                            'history-result--loss': m.result === 'porażka',
                                        }"
                                        x-text="resultLabel(m.result)"
                                    ></span>
                                    <span class="history-score" x-show="formatScore(m.score)" x-text="formatScore(m.score)"></span>
                                </div>
                            </td>
                            <td class="history-event" x-text="m.tournament_name || '–'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="history-more" x-show="gameHistory.hasMore">
            <button type="button"
                    @click="loadMoreGames()"
                    :disabled="gameHistory.loading"
                    class="btn btn-mini disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-text="gameHistory.loading ? 'Ładowanie…' : 'Załaduj więcej'"></span>
            </button>
        </div>
    </div>
</section>
