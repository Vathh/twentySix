import Pusher from 'pusher-js';
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

const GAME_STATE_EVENTS = ['game.state', '.game.state'];
const GAME_CANCELLED_EVENTS = ['game.cancelled', '.game.cancelled'];

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

function normalizePayload(payload) {
    if (payload == null) {
        return null;
    }
    if (typeof payload === 'string') {
        try {
            payload = JSON.parse(payload);
        } catch {
            return null;
        }
    }
    if (payload && typeof payload === 'object' && payload.state) {
        return payload.state;
    }
    return payload;
}

export function registerRefereeScoring(Alpine) {
    Alpine.data('refereeScoring', (config) => ({
        session: null,
        state: null,
        input: '',
        busy: false,
        error: '',
        connection: 'offline',
        pollTimer: null,
        pusher: null,
        checkoutOpen: false,
        checkoutDartsOpen: false,
        pendingCheckoutScore: null,
        leaving: false,
        openerOpen: false,
        openerChosen: false,
        chosenOpenerIndex: null,
        dismissedLossKey: null,
        cancelled: false,

        init() {
            this.session = requireRefereeSessionOrRedirect();
            if (!this.session) {
                return;
            }
            this.loadState().then(() => {
                this.connectWebSocket();
                this.pollTimer = setInterval(() => this.loadState({ quiet: true }), 20000);
            });
        },

        destroy() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
            if (this.pusher) {
                try {
                    this.pusher.unsubscribe(config.channel);
                    this.pusher.disconnect();
                } catch {
                    // ignore
                }
                this.pusher = null;
            }
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

        get matchFormat() {
            return this.state?.game?.matchFormat ?? null;
        },

        isSingleSetFormat() {
            return Number(this.matchFormat?.setsToWinMatch ?? 1) <= 1;
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
            this.error = '';
        },

        async loadState({ quiet = false } = {}) {
            if (this.cancelled) {
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

        selectOpener(index) {
            const idx = Number(index);
            if (!Number.isInteger(idx) || idx < 0 || idx > 1) {
                return;
            }
            this.chosenOpenerIndex = idx;
            this.openerChosen = true;
            this.openerOpen = false;
        },

        connectWebSocket() {
            if (!config.reverb?.key || !config.channel) {
                this.connection = 'offline';
                return;
            }
            const useTls = config.reverb.scheme === 'https';
            this.connection = 'connecting';
            this.pusher = new Pusher(config.reverb.key, {
                cluster: 'reverb',
                wsHost: config.reverb.host,
                wsPort: config.reverb.port,
                wssPort: config.reverb.port,
                forceTLS: useTls,
                disableStats: true,
                enabledTransports: ['ws', 'wss'],
            });
            const channel = this.pusher.subscribe(config.channel);
            channel.bind('pusher:subscription_succeeded', () => {
                this.connection = 'live';
            });
            channel.bind('pusher:subscription_error', () => {
                this.connection = 'error';
            });
            GAME_STATE_EVENTS.forEach((eventName) => {
                channel.bind(eventName, (payload) => {
                    const next = normalizePayload(payload);
                    if (next) {
                        this.state = next;
                        this.connection = 'live';
                        this.maybeAskOpener();
                    }
                });
            });
            GAME_CANCELLED_EVENTS.forEach((eventName) => {
                channel.bind(eventName, () => {
                    this.markCancelled();
                    this.connection = 'live';
                });
            });
        },

        pressDigit(d) {
            if (
                this.busy
                || this.isFinished
                || this.cancelled
                || this.openerOpen
                || !this.openerChosen
                || this.checkoutOpen
                || this.checkoutDartsOpen
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
            if (this.busy || this.isFinished || this.cancelled) {
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
            if (this.busy || this.isFinished || this.cancelled || this.openerOpen || !this.openerChosen || this.bullOffRequired || this.lossThresholdOpen) {
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
                this.checkoutOpen = true;
                return;
            }

            this.busy = true;
            this.error = '';
            try {
                const legId = await this.ensureLegId();
                const bust = score > remainingBefore;
                const visitScore = bust ? 0 : score;
                const remainingAfter = bust
                    ? remainingBefore
                    : remainingBefore - score;

                const state = await this.api(`/legs/${legId}/visits`, {
                    method: 'POST',
                    body: {
                        playerId: player.playerId,
                        score: visitScore,
                        remainingBefore,
                        remainingAfter,
                        dartsInVisit: 3,
                        closedLeg: false,
                        bust,
                        clientVisitId: newClientVisitId(),
                    },
                });
                this.state = state;
                this.input = '';
                if (
                    state?.meta?.lastLegClose?.reason === 'loss_threshold'
                    && state?.game?.status !== 'finished'
                    && !state?.currentLeg?.id
                ) {
                    try {
                        await this.ensureLegId();
                    } catch {
                        // next leg starts on first visit
                    }
                }
            } catch (e) {
                this.error = e.message || 'Nie udało się zapisać wizyty.';
                await this.loadState({ quiet: true });
            } finally {
                this.busy = false;
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

            this.busy = true;
            this.error = '';
            try {
                const remainingBefore = this.remaining(player);
                const legId = await this.ensureLegId();
                const clientVisitId = newClientVisitId();

                await this.api(`/legs/${legId}/visits`, {
                    method: 'POST',
                    body: {
                        playerId: player.playerId,
                        score,
                        remainingBefore,
                        remainingAfter: 0,
                        dartsInVisit,
                        closedLeg: true,
                        bust: false,
                        clientVisitId,
                    },
                });

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

                const state = await this.api(`/legs/${legId}/close`, {
                    method: 'POST',
                    body: {
                        winnerId: player.playerId,
                        players: playersPayload,
                    },
                });
                this.state = state;
                this.input = '';
                this.cancelCheckout();

                if (state?.game?.status !== 'finished' && !state?.currentLeg?.id) {
                    try {
                        await this.ensureLegId();
                    } catch {
                        // next leg starts on first visit
                    }
                }
            } catch (e) {
                this.error = e.message || 'Nie udało się zamknąć lega.';
                this.cancelCheckout();
                await this.loadState({ quiet: true });
            } finally {
                this.busy = false;
            }
        },

        async confirmBullOff(index) {
            const player = this.players[index];
            if (this.busy || !player?.playerId || !this.bullOffRequired) {
                return;
            }
            this.busy = true;
            this.error = '';
            try {
                const legId = await this.ensureLegId();
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
                const state = await this.api(`/legs/${legId}/close`, {
                    method: 'POST',
                    body: {
                        winnerId: player.playerId,
                        players: playersPayload,
                        reason: 'bull_off',
                    },
                });
                this.state = state;
                this.input = '';
                if (state?.game?.status !== 'finished' && !state?.currentLeg?.id) {
                    try {
                        await this.ensureLegId();
                    } catch {
                        // next leg starts on first visit
                    }
                }
            } catch (e) {
                this.error = e.message || 'Nie udało się zamknąć lega.';
                await this.loadState({ quiet: true });
            } finally {
                this.busy = false;
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
            this.busy = true;
            this.error = '';
            try {
                const state = await this.api(`/legs/${legId}/visits/undo`, {
                    method: 'POST',
                });
                this.state = state;
                this.input = '';
            } catch (e) {
                this.error = e.message || 'Nie udało się cofnąć wizyty.';
                await this.loadState({ quiet: true });
            } finally {
                this.busy = false;
            }
        },

        async undo() {
            if (this.busy || this.isFinished || this.cancelled || this.openerOpen || !this.openerChosen) {
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
            this.busy = true;
            this.error = '';
            try {
                const state = await this.api(`/legs/${legId}/visits/undo`, {
                    method: 'POST',
                });
                this.state = state;
                this.input = '';
            } catch (e) {
                this.error = e.message || 'Nie udało się cofnąć wizyty.';
                await this.loadState({ quiet: true });
            } finally {
                this.busy = false;
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
            if (this.isFinished || this.cancelled) {
                window.location.assign(config.gamesUrl);
                return;
            }
            if (!window.confirm('Czy na pewno chcesz opuścić mecz?')) {
                return;
            }
            this.leaving = true;
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
