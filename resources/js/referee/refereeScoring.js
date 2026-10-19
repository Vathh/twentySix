import {
    clearRefereeSession,
    refereeLoginUrl,
    requireRefereeSessionOrRedirect,
} from './session.js';
import {
    newClientVisitId,
    refereeFetch,
    RefereeApiError,
    scoringBaseUrl,
} from './api.js';
import { buildH2hLegVisitRows } from './legVisitRows.js';
import {
    bindRefereeLegId,
    clearRefereeOutbox,
    commandAlreadyApplied,
    dequeueRefereeOutbox,
    enqueueRefereeOutbox,
    isLegAlreadyOpenMessage,
    isRetryableRefereeError,
    loadRefereeOutbox,
    patchRefereeState,
    refereeOutboxKey,
    rejectionNotice,
    replayRefereeEntries,
} from './refereeOutbox.js';

/**
 * Pytanie „czy kończy leg tym wynikiem?”.
 * false — po zatwierdzeniu checkoutu od razu pytanie o liczbę lotek.
 * true — z powrotem pokazuje modal Checkout?.
 */
const CHECKOUT_LEG_CONFIRMATION_ENABLED = false;

function hasMatchProgress(state) {
    if (!state) {
        return false;
    }
    if ((state.visits?.length ?? 0) > 0) {
        return true;
    }
    if ((state.legs?.length ?? 0) > 0) {
        return true;
    }
    if (
        (state.game?.player1LegsWon ?? 0) + (state.game?.player2LegsWon ?? 0) >
        0
    ) {
        return true;
    }
    if ((state.players ?? []).some((p) => (p.legsWon ?? 0) > 0)) {
        return true;
    }
    const legNumber =
        state.currentLeg?.legNumber
        ?? state.turn?.legNumber
        ?? 0;
    return Number(legNumber) > 1;
}

export function registerRefereeScoring(Alpine) {
    Alpine.data('refereeScoring', (config) => ({
        session: null,
        state: null,
        input: '',
        busy: false,
        error: '',
        syncPending: false,
        flushInFlight: false,
        flushQueued: false,
        flushRetryMs: 3000,
        flushTimer: null,
        leaseLost: false,
        onOnline: null,
        checkoutOpen: false,
        checkoutDartsOpen: false,
        pendingCheckoutScore: null,
        leaving: false,
        openerOpen: false,
        openerChosen: false,
        chosenOpenerIndex: null,
        switchOpenerOpen: false,
        pendingSwitchIndex: null,
        dismissedLossKey: null,
        cancelled: false,
        legVisitsHeight: 240,

        init() {
            this.session = requireRefereeSessionOrRedirect();
            if (!this.session) {
                return;
            }
            this.renewLease();
            this.leaseTimer = setInterval(() => this.renewLease(), 20000);
            this.onOnline = () => {
                this.flushOutbox();
            };
            window.addEventListener('online', this.onOnline);
            this.loadState().then(() => this.flushOutbox());
        },

        destroy() {
            if (this.leaseTimer) {
                clearInterval(this.leaseTimer);
                this.leaseTimer = null;
            }
            if (this.onOnline) {
                window.removeEventListener('online', this.onOnline);
                this.onOnline = null;
            }
            this.clearFlushRetry();
        },

        outboxKey() {
            return refereeOutboxKey(config.gameType, config.gameId);
        },

        refreshSyncPending() {
            this.syncPending = loadRefereeOutbox(this.outboxKey()).length > 0;
        },

        get baseUrl() {
            return scoringBaseUrl(config.gameType, config.gameId);
        },

        get players() {
            return this.state?.players ?? [];
        },

        get player1() {
            return this.players[0] ?? null;
        },

        get player2() {
            return this.players[1] ?? null;
        },

        get hasProgress() {
            return hasMatchProgress(this.state);
        },

        get turnIndex() {
            if (!this.hasProgress && this.chosenOpenerIndex != null) {
                return this.chosenOpenerIndex;
            }
            if (!this.hasProgress && !this.openerChosen) {
                return -1;
            }
            const idx = this.state?.turn?.currentPlayerIndex;
            return typeof idx === 'number' ? idx : 0;
        },

        get currentPlayer() {
            return this.players[this.turnIndex] ?? null;
        },

        get canSwitchOpener() {
            return this.openerChosen
                && !this.hasProgress
                && !this.isFinished
                && !this.isCancelled
                && !this.leaseLost
                && !this.bullOffRequired
                && !this.openerOpen
                && !this.busy
                && !this.lossThresholdOpen;
        },

        get pendingSwitchName() {
            const player = this.players[this.pendingSwitchIndex];
            return player?.name ?? 'tego zawodnika';
        },

        get startingScore() {
            return (
                this.state?.game?.matchFormat?.startingScore ??
                this.state?.game?.startingScore ??
                501
            );
        },

        get isFinished() {
            return this.state?.game?.status === 'finished';
        },

        get isCancelled() {
            return this.cancelled === true;
        },

        get inputLocked() {
            return this.cancelled || this.leaseLost;
        },

        get matchFormat() {
            return this.state?.game?.matchFormat ?? null;
        },

        isSingleSetFormat() {
            return Number(this.matchFormat?.setsToWinMatch ?? 1) <= 1;
        },

        formatLabel() {
            const format = this.matchFormat;
            if (!format) {
                return '';
            }
            const legs = Number(format.legsToWinSet ?? 1);
            const sets = Number(format.setsToWinMatch ?? 1);
            const score = format.startingScore ?? 501;
            if (sets <= 1) {
                if (format.winMode === 'best_of') {
                    return `${score} · best of ${format.winLength}`;
                }
                return `${score} · do ${legs} legów`;
            }
            return `${score} · ${sets} sety · ${legs} legi/set`;
        },

        matchScore(player) {
            if (!player) {
                return 0;
            }
            if (this.isSingleSetFormat()) {
                return player.legsWonInSet ?? player.legsWon ?? 0;
            }
            return player.setsWon ?? 0;
        },

        legsInSet(player) {
            return player?.legsWonInSet ?? player?.legsWon ?? 0;
        },

        remaining(player) {
            return player?.remaining ?? this.startingScore;
        },

        get legVisitRows() {
            return buildH2hLegVisitRows(
                this.state?.visits ?? [],
                this.player1?.playerId,
                this.player2?.playerId,
            );
        },

        beginLegVisitsResize(event) {
            if (event.button != null && event.button !== 0) {
                return;
            }
            const startY = event.clientY;
            const startHeight = this.legVisitsHeight;
            const previousUserSelect = document.body.style.userSelect;
            document.body.style.userSelect = 'none';
            const move = (pointerEvent) => {
                const next = startHeight + (pointerEvent.clientY - startY);
                this.legVisitsHeight = Math.max(140, Math.min(560, Math.round(next)));
            };
            const end = () => {
                document.body.style.userSelect = previousUserSelect;
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', end);
                window.removeEventListener('pointercancel', end);
            };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', end);
            window.addEventListener('pointercancel', end);
        },

        get bullOffRequired() {
            return Boolean(this.state?.meta?.bullOffRequired) && !this.lossThresholdOpen;
        },

        get lossThresholdText() {
            const close = this.state?.meta?.lastLegClose;
            if (close?.reason !== 'loss_threshold') {
                return '';
            }
            const key = `${close.legId ?? ''}:${close.winnerId ?? ''}`;
            if (this.dismissedLossKey === key) {
                return '';
            }
            const loser = this.players.find((p) => p.playerId === close.loserId);
            const winner = this.players.find((p) => p.playerId === close.winnerId);
            return `${loser?.name ?? 'Zawodnik'} przegrywa lega (próg przegranej). Wygrywa ${winner?.name ?? 'rywal'}.`;
        },

        get lossThresholdOpen() {
            return this.lossThresholdText !== '';
        },

        dismissLossThreshold() {
            const close = this.state?.meta?.lastLegClose;
            this.dismissedLossKey = `${close?.legId ?? ''}:${close?.winnerId ?? ''}`;
        },

        inputValue() {
            if (this.input === '') {
                return null;
            }
            const n = Number(this.input);
            return Number.isFinite(n) ? n : null;
        },

        async handleUnauthorized() {
            clearRefereeSession();
            window.location.replace(refereeLoginUrl());
        },

        async api(path, opts = {}) {
            try {
                return await refereeFetch(`${this.baseUrl}${path}`, {
                    ...opts,
                    token: this.session.token,
                });
            } catch (e) {
                if (e instanceof RefereeApiError && e.status === 401) {
                    await this.handleUnauthorized();
                    throw e;
                }
                throw e;
            }
        },

        markCancelled() {
            if (this.cancelled) {
                return;
            }
            this.cancelled = true;
            this.busy = false;
            this.openerOpen = false;
            this.switchOpenerOpen = false;
            this.pendingSwitchIndex = null;
            this.error = '';
        },

        async renewLease() {
            if (!this.session || this.leaving || this.isFinished || this.inputLocked) {
                return;
            }
            try {
                const data = await refereeFetch('/api/game/heartbeat', {
                    method: 'POST',
                    token: this.session.token,
                    body: { gameId: config.gameId, type: config.gameType },
                });
                if (data?.status === 'finished') {
                    if (this.state?.game) {
                        this.state = {
                            ...this.state,
                            game: { ...this.state.game, status: 'finished' },
                        };
                    }
                }
            } catch (e) {
                if (!(e instanceof RefereeApiError)) {
                    return;
                }
                if (e.data?.reason === 'cancelled') {
                    clearRefereeOutbox(this.outboxKey());
                    this.refreshSyncPending();
                    this.markCancelled();
                    return;
                }
                if (e.data?.reason === 'stolen' || e.status === 409 || e.status === 403) {
                    clearRefereeOutbox(this.outboxKey());
                    this.refreshSyncPending();
                    this.markStolen(e.message);
                }
            }
        },

        markStolen(message) {
            if (this.leaseLost) {
                return;
            }
            this.leaseLost = true;
            this.busy = false;
            this.openerOpen = false;
            this.switchOpenerOpen = false;
            this.pendingSwitchIndex = null;
            this.error = message || 'Mecz jest sędziowany na innym urządzeniu.';
        },

        async loadState({ quiet = false } = {}) {
            if (this.inputLocked) {
                return;
            }
            try {
                const data = await this.api('/scoring/state');
                this.state = data;
                this.error = '';
                this.maybeAskOpener();
            } catch (e) {
                if (e instanceof RefereeApiError && e.status === 409) {
                    this.markCancelled();
                    return;
                }
                if (!quiet) {
                    this.error = e.message || 'Nie udało się wczytać stanu meczu.';
                }
            }
        },

        maybeAskOpener() {
            if (this.isFinished || this.cancelled || this.hasProgress) {
                this.switchOpenerOpen = false;
                this.pendingSwitchIndex = null;
            }
            if (this.isFinished || this.cancelled) {
                this.openerOpen = false;
                return;
            }
            if (this.hasProgress) {
                this.openerChosen = true;
                this.openerOpen = false;
                return;
            }
            if (this.openerChosen) {
                this.openerOpen = false;
                return;
            }
            if ((this.players?.length ?? 0) < 2) {
                return;
            }
            this.openerOpen = true;
        },

        askSwitchOpener(index) {
            const idx = Number(index);
            if (!this.canSwitchOpener || idx === this.turnIndex) {
                return;
            }
            if (!Number.isInteger(idx) || idx < 0 || idx > 1) {
                return;
            }
            this.pendingSwitchIndex = idx;
            this.switchOpenerOpen = true;
        },

        confirmSwitchOpener() {
            if (!this.switchOpenerOpen || this.pendingSwitchIndex == null) {
                return;
            }
            this.selectOpener(this.pendingSwitchIndex);
            this.input = '';
            this.switchOpenerOpen = false;
            this.pendingSwitchIndex = null;
        },

        cancelSwitchOpener() {
            this.switchOpenerOpen = false;
            this.pendingSwitchIndex = null;
        },

        selectOpener(index) {
            const idx = Number(index);
            if (!Number.isInteger(idx) || idx < 0 || idx > 1) {
                return;
            }
            this.chosenOpenerIndex = idx;
            this.openerChosen = true;
            this.openerOpen = false;
        },

        async sendOutboxEntry(entry) {
            if (entry.op === 'startLeg') {
                return this.api('/legs', {
                    method: 'POST',
                    body: entry.payload,
                });
            }
            if (entry.op === 'recordVisit') {
                return this.api(`/legs/${entry.legId}/visits`, {
                    method: 'POST',
                    body: entry.payload,
                });
            }
            if (entry.op === 'closeLeg') {
                return this.api(`/legs/${entry.legId}/close`, {
                    method: 'POST',
                    body: entry.payload,
                });
            }
            if (entry.op === 'undoVisit') {
                return this.api(`/legs/${entry.legId}/visits/undo`, {
                    method: 'POST',
                });
            }
            return null;
        },

        clearFlushRetry() {
            if (this.flushTimer) {
                clearTimeout(this.flushTimer);
                this.flushTimer = null;
            }
        },

        scheduleFlushRetry() {
            if (this.flushTimer) {
                return;
            }
            const delay = this.flushRetryMs;
            this.flushTimer = setTimeout(() => {
                this.flushTimer = null;
                this.flushRetryMs = 8000;
                void this.flushOutbox();
            }, delay);
        },

        applyAckedState(state, tail) {
            if (!state) {
                return;
            }
            this.state = replayRefereeEntries(state, tail);
            this.maybeAskOpener();
        },

        async recoverRejected(key, entry, error) {
            let snapshot = null;
            try {
                snapshot = await this.api('/scoring/state');
            } catch (fetchErr) {
                if (fetchErr instanceof RefereeApiError && fetchErr.status === 409) {
                    clearRefereeOutbox(key);
                    this.refreshSyncPending();
                    this.clearFlushRetry();
                    this.markCancelled();
                    return { continue: false, state: null };
                }
                if (
                    fetchErr instanceof RefereeApiError
                    && (fetchErr.data?.reason === 'stolen' || fetchErr.status === 403)
                ) {
                    clearRefereeOutbox(key);
                    this.refreshSyncPending();
                    this.clearFlushRetry();
                    this.markStolen(fetchErr.message);
                    return { continue: false, state: null };
                }
            }
            if (snapshot?.game?.status === 'finished') {
                clearRefereeOutbox(key);
                this.state = snapshot;
                this.refreshSyncPending();
                this.clearFlushRetry();
                this.maybeAskOpener();
                return { continue: false, state: snapshot };
            }
            if (
                snapshot
                && commandAlreadyApplied(entry, snapshot, {
                    legAlreadyOpen: isLegAlreadyOpenMessage(error),
                })
            ) {
                if (entry.op === 'startLeg' && snapshot.currentLeg?.id) {
                    dequeueRefereeOutbox(key);
                    bindRefereeLegId(key, snapshot.currentLeg.id);
                } else {
                    dequeueRefereeOutbox(key);
                }
                this.applyAckedState(snapshot, loadRefereeOutbox(key));
                return { continue: true, state: snapshot };
            }
            const pending = loadRefereeOutbox(key);
            const droppedTail = Math.max(0, pending.length - 1);
            clearRefereeOutbox(key);
            this.refreshSyncPending();
            this.clearFlushRetry();
            if (snapshot) {
                this.state = snapshot;
                this.maybeAskOpener();
            }
            this.error = rejectionNotice(droppedTail);
            return { continue: false, state: snapshot };
        },

        async flushOutboxPass() {
            const key = this.outboxKey();
            let remaining = loadRefereeOutbox(key);
            let last = null;
            while (remaining.length > 0) {
                let entry = remaining[0];
                try {
                    if (entry.legId == null && entry.op !== 'startLeg') {
                        this.refreshSyncPending();
                        return { retryable: true, state: last };
                    }
                    last = await this.sendOutboxEntry(entry);
                    if (entry.op === 'startLeg' && !last?.currentLeg?.id) {
                        this.refreshSyncPending();
                        return { retryable: true, state: last };
                    }
                    remaining = dequeueRefereeOutbox(key);
                    if (entry.op === 'startLeg') {
                        remaining = bindRefereeLegId(key, last.currentLeg.id);
                    }
                    if (last?.game?.status === 'finished') {
                        clearRefereeOutbox(key);
                        this.state = last;
                        this.refreshSyncPending();
                        this.flushRetryMs = 3000;
                        this.clearFlushRetry();
                        this.maybeAskOpener();
                        return { retryable: false, state: last };
                    }
                    this.applyAckedState(last, remaining);
                } catch (e) {
                    if (e instanceof RefereeApiError && e.data?.reason === 'cancelled') {
                        clearRefereeOutbox(key);
                        this.refreshSyncPending();
                        this.clearFlushRetry();
                        this.markCancelled();
                        return { retryable: false, state: null };
                    }
                    if (
                        e instanceof RefereeApiError
                        && (e.data?.reason === 'stolen' || e.status === 403)
                    ) {
                        clearRefereeOutbox(key);
                        this.refreshSyncPending();
                        this.clearFlushRetry();
                        this.markStolen(e.message);
                        return { retryable: false, state: null };
                    }
                    if (e instanceof RefereeApiError && e.status === 409) {
                        clearRefereeOutbox(key);
                        this.refreshSyncPending();
                        this.clearFlushRetry();
                        this.markCancelled();
                        return { retryable: false, state: null };
                    }
                    if (e instanceof RefereeApiError && e.status === 422) {
                        const recovered = await this.recoverRejected(key, entry, e);
                        if (recovered.continue) {
                            remaining = loadRefereeOutbox(key);
                            last = recovered.state ?? last;
                            continue;
                        }
                        return { retryable: false, state: recovered.state ?? null };
                    }
                    if (isRetryableRefereeError(e)) {
                        this.refreshSyncPending();
                        return { retryable: true, state: last };
                    }
                    throw e;
                }
            }
            this.refreshSyncPending();
            this.flushRetryMs = 3000;
            this.clearFlushRetry();
            return { retryable: false, state: last };
        },

        async flushOutbox() {
            if (this.inputLocked) {
                return null;
            }
            if (this.flushInFlight) {
                this.flushQueued = true;
                return null;
            }
            this.flushInFlight = true;
            let last = null;
            try {
                do {
                    this.flushQueued = false;
                    const pass = await this.flushOutboxPass();
                    last = pass.state ?? last;
                    if (pass.retryable) {
                        this.scheduleFlushRetry();
                        break;
                    }
                } while (this.flushQueued);
                return last;
            } catch (e) {
                this.error = e.message || 'Nie udało się zapisać wizyty.';
                return last;
            } finally {
                this.flushInFlight = false;
            }
        },

        async queueCommands(entries, { optimistic = true } = {}) {
            const key = this.outboxKey();
            entries.forEach((entry) => enqueueRefereeOutbox(key, entry));
            if (optimistic) {
                let next = this.state;
                entries.forEach((entry) => {
                    next = patchRefereeState(next, entry);
                });
                this.state = next;
            }
            void this.flushOutbox();
            return this.state;
        },

        openLegId() {
            if (this.state?.currentLeg?.id && this.state.currentLeg.open !== false) {
                return this.state.currentLeg.id;
            }
            return null;
        },

        legOpenCommands(legId) {
            if (legId) {
                return [];
            }
            const pending = loadRefereeOutbox(this.outboxKey());
            if (pending.some((entry) => entry.op === 'startLeg')) {
                return [];
            }
            return [{
                op: 'startLeg',
                payload: {
                    player1DoubleTracked: false,
                    player2DoubleTracked: false,
                },
            }];
        },

        pressDigit(d) {
            if (
                this.busy
                || this.isFinished
                || this.inputLocked
                || this.openerOpen
                || !this.openerChosen
                || this.checkoutOpen
                || this.checkoutDartsOpen
                || this.switchOpenerOpen
                || this.bullOffRequired
                || this.lossThresholdOpen
            ) {
                return;
            }
            if (this.input.length >= 3) {
                return;
            }
            this.input = `${this.input}${d}`;
        },

        clearInput() {
            this.input = '';
        },

        backspace() {
            this.input = this.input.slice(0, -1);
        },

        handleWindowKey(event) {
            if (this.busy || this.isFinished || this.inputLocked) {
                return;
            }

            if (this.bullOffRequired || this.lossThresholdOpen) {
                event.preventDefault();
                return;
            }

            if (this.checkoutOpen) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    this.confirmCheckout();
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.cancelCheckout();
                }
                return;
            }

            if (this.checkoutDartsOpen) {
                if (event.key === '1' || event.key === '2' || event.key === '3') {
                    event.preventDefault();
                    this.finishCheckout(Number(event.key));
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.cancelCheckout();
                }
                return;
            }

            if (this.switchOpenerOpen) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    this.confirmSwitchOpener();
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.cancelSwitchOpener();
                }
                return;
            }

            if (this.openerOpen || !this.openerChosen) {
                return;
            }

            if (event.key >= '0' && event.key <= '9') {
                this.pressDigit(event.key);
            }
            if (event.key === 'Backspace') {
                event.preventDefault();
                this.backspace();
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                this.submitVisit();
            }
            if (event.key === 'Escape') {
                this.clearInput();
            }
        },

        async ensureLegId() {
            if (this.state?.currentLeg?.id && this.state.currentLeg.open !== false) {
                return this.state.currentLeg.id;
            }
            let state;
            try {
                state = await this.api('/legs', {
                    method: 'POST',
                    body: {
                        player1DoubleTracked: false,
                        player2DoubleTracked: false,
                    },
                });
            } catch (e) {
                const msg = e.message || '';
                if (msg.includes('otwarty') || msg.includes('już')) {
                    state = await this.api('/scoring/state');
                } else {
                    throw e;
                }
            }
            this.state = state;
            const legId = state?.currentLeg?.id;
            if (!legId) {
                throw new Error('Brak otwartego lega');
            }
            return legId;
        },

        async submitVisit() {
            if (this.busy || this.isFinished || this.inputLocked || this.openerOpen || !this.openerChosen || this.switchOpenerOpen || this.bullOffRequired || this.lossThresholdOpen) {
                return;
            }
            const score = this.inputValue();
            if (score == null || score < 0 || score > 180) {
                this.error = 'Podaj wynik wizyty (0–180).';
                return;
            }

            const player = this.currentPlayer;
            if (!player?.playerId) {
                this.error = 'Brak aktywnego gracza.';
                return;
            }

            const remainingBefore = this.remaining(player);
            if (score === remainingBefore) {
                this.pendingCheckoutScore = score;
                if (CHECKOUT_LEG_CONFIRMATION_ENABLED) {
                    this.checkoutOpen = true;
                } else {
                    this.checkoutDartsOpen = true;
                }
                return;
            }

            this.error = '';
            try {
                const legId = this.openLegId();
                const bust = score > remainingBefore;
                const visitScore = bust ? 0 : score;
                const remainingAfter = bust
                    ? remainingBefore
                    : remainingBefore - score;

                const clientVisitId = newClientVisitId();
                await this.queueCommands([
                    ...this.legOpenCommands(legId),
                    {
                        op: 'recordVisit',
                        legId,
                        clientVisitId,
                        payload: {
                            playerId: player.playerId,
                            score: visitScore,
                            remainingBefore,
                            remainingAfter,
                            dartsInVisit: 3,
                            closedLeg: false,
                            bust,
                            clientVisitId,
                        },
                    },
                ]);
                this.input = '';
            } catch (e) {
                this.error = e.message || 'Nie udało się zapisać wizyty.';
                await this.loadState({ quiet: true });
            }
        },

        cancelCheckout() {
            this.checkoutOpen = false;
            this.checkoutDartsOpen = false;
            this.pendingCheckoutScore = null;
        },

        confirmCheckout() {
            if (!this.checkoutOpen) {
                return;
            }
            this.checkoutOpen = false;
            this.checkoutDartsOpen = true;
        },

        async finishCheckout(dartsInVisit) {
            if (this.busy || !this.checkoutDartsOpen) {
                return;
            }
            const score = this.pendingCheckoutScore;
            const player = this.currentPlayer;
            if (score == null || !player?.playerId) {
                this.cancelCheckout();
                return;
            }

            this.error = '';
            try {
                const remainingBefore = this.remaining(player);
                const legId = this.openLegId();
                const clientVisitId = newClientVisitId();

                const playersPayload = this.players.map((p) => ({
                    playerId: p.playerId,
                    doubleTracked: false,
                    doubleAttempts: null,
                    doubleSuccesses: null,
                    legAverage: null,
                    firstNineAverage: null,
                    highestVisit: null,
                    highestFinish: null,
                    dartsThrown: null,
                    checkoutDart: p.playerId === player.playerId ? dartsInVisit : null,
                }));

                await this.queueCommands([
                    ...this.legOpenCommands(legId),
                    {
                        op: 'recordVisit',
                        legId,
                        clientVisitId,
                        payload: {
                            playerId: player.playerId,
                            score,
                            remainingBefore,
                            remainingAfter: 0,
                            dartsInVisit,
                            closedLeg: true,
                            bust: false,
                            clientVisitId,
                        },
                    },
                    {
                        op: 'closeLeg',
                        legId,
                        payload: {
                            winnerId: player.playerId,
                            players: playersPayload,
                        },
                    },
                ]);
                this.input = '';
                this.cancelCheckout();
            } catch (e) {
                this.error = e.message || 'Nie udało się zamknąć lega.';
                this.cancelCheckout();
                await this.loadState({ quiet: true });
            }
        },

        async confirmBullOff(index) {
            const player = this.players[index];
            if (this.busy || !player?.playerId || !this.bullOffRequired) {
                return;
            }
            this.error = '';
            try {
                const legId = this.openLegId();
                const playersPayload = this.players.map((p) => ({
                    playerId: p.playerId,
                    doubleTracked: false,
                    doubleAttempts: null,
                    doubleSuccesses: null,
                    legAverage: null,
                    firstNineAverage: null,
                    highestVisit: null,
                    highestFinish: null,
                    dartsThrown: null,
                    checkoutDart: null,
                }));
                await this.queueCommands([
                    ...this.legOpenCommands(legId),
                    {
                        op: 'closeLeg',
                        legId,
                        payload: {
                            winnerId: player.playerId,
                            players: playersPayload,
                            reason: 'bull_off',
                        },
                    },
                ]);
                this.input = '';
            } catch (e) {
                this.error = e.message || 'Nie udało się zamknąć lega.';
                await this.loadState({ quiet: true });
            }
        },

        async undoFromBullOff() {
            if (this.busy || !this.bullOffRequired) {
                return;
            }
            const legId = this.resolveUndoLegId();
            if (!legId) {
                this.error = 'Brak wizyty do cofnięcia.';
                return;
            }
            this.error = '';
            try {
                await this.queueCommands([{ op: 'undoVisit', legId }]);
                this.input = '';
            } catch (e) {
                this.error = e.message || 'Nie udało się cofnąć wizyty.';
                await this.loadState({ quiet: true });
            }
        },

        async undo() {
            if (this.busy || this.isFinished || this.inputLocked || this.openerOpen || !this.openerChosen) {
                return;
            }
            const legId = this.resolveUndoLegId();
            if (!legId) {
                this.error = 'Brak lega do cofnięcia.';
                return;
            }
            const closedLegUndo = this.wouldUndoClosedLeg();
            const msg = closedLegUndo
                ? 'To cofnie ostatnią wizytę i ponownie otworzy zakończony leg. Kontynuować?'
                : 'Cofnąć ostatnią wizytę?';
            if (!window.confirm(msg)) {
                return;
            }
            this.error = '';
            try {
                await this.queueCommands([{ op: 'undoVisit', legId }]);
                this.input = '';
            } catch (e) {
                this.error = e.message || 'Nie udało się cofnąć wizyty.';
                await this.loadState({ quiet: true });
            }
        },

        resolveUndoLegId() {
            const state = this.state;
            if (!state) {
                return null;
            }
            if (state.currentLeg?.id != null) {
                return state.currentLeg.id;
            }
            const finishedLegs = state.legs ?? [];
            if (finishedLegs.length === 0) {
                return null;
            }
            const lastFinished = finishedLegs.reduce((latest, leg) =>
                (leg.legNumber ?? 0) >= (latest.legNumber ?? 0) ? leg : latest,
            );
            return lastFinished.id ?? null;
        },

        wouldUndoClosedLeg() {
            const state = this.state;
            if (!state) {
                return false;
            }
            const currentLeg = state.currentLeg ?? null;
            const visits = state.visits ?? [];
            const finishedLegs = state.legs ?? [];
            const legNumber = state.turn?.legNumber ?? currentLeg?.legNumber ?? 0;

            if (currentLeg?.open === true && visits.length > 0) {
                return false;
            }
            if (currentLeg == null && finishedLegs.length > 0) {
                return true;
            }
            if (currentLeg?.open === false) {
                return true;
            }
            if (currentLeg?.open === true && visits.length === 0) {
                if (finishedLegs.length > 0 || legNumber > 1) {
                    return true;
                }
            }
            return false;
        },

        async leave() {
            if (this.leaving) {
                return;
            }
            if (this.isFinished || this.cancelled || this.leaseLost) {
                clearRefereeOutbox(this.outboxKey());
                window.location.assign(config.gamesUrl);
                return;
            }
            if (!window.confirm('Opuścić sędziowanie? Inne urządzenie będzie mogło kontynuować ten mecz od ostatniej wizyty.')) {
                return;
            }
            this.leaving = true;
            clearRefereeOutbox(this.outboxKey());
            try {
                await refereeFetch('/api/game/release', {
                    method: 'POST',
                    token: this.session.token,
                    body: {
                        gameId: config.gameId,
                        type: config.gameType,
                    },
                });
            } catch {
                // leave anyway — release may fail if visits already recorded
            }
            window.location.assign(config.gamesUrl);
        },

        goToGames() {
            window.location.assign(config.gamesUrl);
        },
    }));
}
