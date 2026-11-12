<div
    class="mb-6"
    x-data="gameLiveViewer(@js([
        'channel' => $broadcastChannel,
        'stateUrl' => $liveStateUrl,
        'reverb' => $reverb,
        'enablePoll' => false,
        'redirectOnFinish' => false,
        'reloadOnFinish' => true,
    ]))"
    x-init="init()"
>
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="bg-bg-elevated rounded-xl p-4 text-center border border-border">
            <p class="text-xs text-text-muted mb-1">Pozostało</p>
            <p class="text-4xl font-bold text-text score-num" x-text="player1?.remaining ?? '—'">—</p>
        </div>
        <div class="bg-bg-elevated rounded-xl p-4 text-center border border-border">
            <p class="text-xs text-text-muted mb-1">Pozostało</p>
            <p class="text-4xl font-bold text-text score-num" x-text="player2?.remaining ?? '—'">—</p>
        </div>
    </div>
    <div class="bg-bg-deep rounded-lg border border-border p-4">
        <h2 class="text-sm font-semibold text-accent mb-3" x-text="currentLegLabel">Bieżący leg</h2>
        <template x-if="visits.length === 0">
            <p class="text-text-muted text-sm">Brak wizyt w tym legu.</p>
        </template>
        <div class="grid md:grid-cols-2 gap-4" x-show="visits.length > 0">
            <div>
                <h3 class="text-sm font-semibold text-text mb-2">{{ $player1->name }}</h3>
                <table class="w-full text-sm text-text-secondary">
                    <thead>
                        <tr class="text-text-muted text-xs border-b border-border">
                            <th class="text-left py-2 pr-2">#</th>
                            <th class="text-center py-2 px-2">Pkt</th>
                            <th class="text-center py-2 px-2">Zostało</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="visit in visitsForPlayer(player1?.playerId)" :key="visit.id">
                            <tr class="border-b border-border/40" :class="visit.bust ? 'opacity-60' : ''">
                                <td class="py-2 pr-2 score-num" x-text="visit.visitNumber"></td>
                                <td class="text-center py-2 px-2 score-num" x-text="visit.bust ? 'Bust' : visit.score"></td>
                                <td class="text-center py-2 px-2 score-num" x-text="visit.remainingAfter"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-text mb-2">{{ $player2->name }}</h3>
                <table class="w-full text-sm text-text-secondary">
                    <thead>
                        <tr class="text-text-muted text-xs border-b border-border">
                            <th class="text-left py-2 pr-2">#</th>
                            <th class="text-center py-2 px-2">Pkt</th>
                            <th class="text-center py-2 px-2">Zostało</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="visit in visitsForPlayer(player2?.playerId)" :key="visit.id">
                            <tr class="border-b border-border/40" :class="visit.bust ? 'opacity-60' : ''">
                                <td class="py-2 pr-2 score-num" x-text="visit.visitNumber"></td>
                                <td class="text-center py-2 px-2 score-num" x-text="visit.bust ? 'Bust' : visit.score"></td>
                                <td class="text-center py-2 px-2 score-num" x-text="visit.remainingAfter"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
