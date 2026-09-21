import Pusher from 'pusher-js';
import { formatAverage as formatAverageValue } from './formatAverage.js';
import { animateScoreDown } from './tickingScore.js';

const GAME_STATE_EVENTS = ['game.state', '.game.state'];
const GAME_CANCELLED_EVENTS = ['game.cancelled', '.game.cancelled'];

function visitHighlightTier(visit) {
    if (!visit || visit.bust) {
        return null;
    }
    const score = Number(visit.score);
    if (score === 180) {
        return '180';
    }
    if (score >= 170) {
        return '170';
    }
    if (score >= 140) {
        return '140';
    }

    return null;
}

function dartShotLabel(dartsInLeg) {
    const n = Number(dartsInLeg);
    if (!Number.isInteger(n) || n < 1) {
        return '';
    }

    return `${n}. lotka`;
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

/** Rejestruje komponent Alpine — musi być wywołane przed Alpine.start(). */
export function registerGameLiveViewer(Alpine) {
    Alpine.data('gameLiveViewer', (config) => ({
        state: config.initialState ?? null,
        tab: 'counter',
        connection: 'connecting',
        pollTimer: null,
        pusher: null,
        redirecting: false,
        redirectOnFinish: config.redirectOnFinish !== false,
        previewDemo: Boolean(config.previewDemo),
        overlayFx: config.overlayFx === true,
        flashCallout: null,
        flashCalloutTimer: null,
        remainingShown: [null, null],
        remainingTickCancel: [null, null],

        init() {
            this.connectWebSocket(config);
            this.pollTimer = setInterval(() => this.fetchState(), 30000);
            this.snapRemainingFromState();
            this.$watch(
                () => this.state?.game?.status,
                (status) => {
                    if (status === 'finished') {
                        this.redirectToShow();
                    }
                },
            );
            this.$watch(
                () => this.lastVisitSignature,
                () => this.onLastVisitChanged(),
            );
            this.$watch(
                () => this.remainingSignature,
                () => this.tickRemainingFromState(),
            );
            if (this.isFinished) {
                this.redirectToShow();
            }
        },

        destroy() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
            if (this.flashCalloutTimer) {
                clearTimeout(this.flashCalloutTimer);
                this.flashCalloutTimer = null;
            }
            this.cancelRemainingTicks();
            if (this.pusher) {
                this.pusher.unsubscribe(config.channel);
                this.pusher.disconnect();
                this.pusher = null;
            }
        },

        redirectToShow() {
            if (!this.redirectOnFinish || this.redirecting || !config.showUrl) {
                return;
            }
            this.redirecting = true;
            this.destroy();
            window.location.assign(config.showUrl);
        },

        connectWebSocket(cfg) {
            if (!cfg.reverb?.key || !cfg.channel) {
                this.connection = 'offline';
                return;
            }

            const useTls = cfg.reverb.scheme === 'https';
            this.pusher = new Pusher(cfg.reverb.key, {
                cluster: 'reverb',
                wsHost: cfg.reverb.host,
                wsPort: cfg.reverb.port,
                wssPort: cfg.reverb.port,
                forceTLS: useTls,
                disableStats: true,
                enabledTransports: ['ws', 'wss'],
            });

            const channel = this.pusher.subscribe(cfg.channel);

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
                    }
                });
            });
            GAME_CANCELLED_EVENTS.forEach((eventName) => {
                channel.bind(eventName, () => {
                    this.redirectToShow();
                });
            });

            this.pusher.connection.bind('disconnected', () => {
                if (this.connection === 'live') {
                    this.connection = 'reconnecting';
                }
            });

            this.pusher.connection.bind('connected', () => {
                if (this.connection !== 'live') {
                    this.connection = 'connecting';
                }
            });
        },

        async fetchState() {
            if (!config.stateUrl) {
                return;
            }
            try {
                const res = await fetch(config.stateUrl, {
                    headers: { Accept: 'application/json' },
                });
                if (res.status === 410) {
                    if (!this.redirectOnFinish) {
                        return;
                    }
                    this.redirectToShow();
                    return;
                }
                if (res.status === 409) {
                    this.redirectToShow();
                    return;
                }
                if (res.ok) {
                    this.state = await res.json();
                }
            } catch {
                // ignore — WebSocket is primary
            }
        },

        get isLive() {
            return this.state?.game?.status === 'in_progress';
        },

        get isFinished() {
            if (this.previewDemo) {
                return false;
            }

            return this.state?.game?.status === 'finished';
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

        get matchFormat() {
            return this.state?.game?.matchFormat ?? null;
        },

        isSingleSetFormat() {
            const sets = Number(this.matchFormat?.setsToWinMatch ?? 1);
            return sets <= 1;
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
            if (!player) {
                return 0;
            }

            return player.legsWonInSet ?? player.legsWon ?? 0;
        },

        scoreToWinDisplay() {
            if (this.isSingleSetFormat()) {
                return Number(this.state?.game?.matchFormat?.legsToWinSet ?? 2);
            }

            return Number(this.state?.game?.matchFormat?.setsToWinMatch ?? 2);
        },

        scoreUnitDisplay() {
            return this.isSingleSetFormat() ? 'legi' : 'sety';
        },

        get visits() {
            return this.state?.visits ?? [];
        },

        get currentSetNumber() {
            return Number(this.state?.game?.currentSetNumber ?? 1);
        },

        get currentLegLabel() {
            const leg = this.state?.currentLeg;
            if (!leg) {
                return 'Brak otwartego lega';
            }
            if (this.isSingleSetFormat()) {
                return `Leg ${leg.legNumber}`;
            }

            return `Set ${this.currentSetNumber} · Leg ${leg.legNumber}`;
        },

        get currentSetLegsLabel() {
            return `${this.legsInSet(this.player1)}:${this.legsInSet(this.player2)}`;
        },

        visitsForPlayer(playerId) {
            const id = Number(playerId);
            return (this.visits ?? []).filter((v) => Number(v.playerId) === id);
        },

        playerName(playerId) {
            const p = this.players.find((x) => Number(x.playerId) === Number(playerId));
            return p?.name ?? '—';
        },

        formatAverage(value) {
            return formatAverageValue(value);
        },

        formatPercent(value) {
            if (value == null) {
                return '—';
            }
            return `${value}%`;
        },

        connectionLabel() {
            return {
                connecting: 'Łączenie…',
                live: 'Na żywo',
                reconnecting: 'Wznowienie połączenia…',
                error: 'Błąd połączenia',
                offline: 'Tylko odświeżanie',
            }[this.connection] ?? this.connection;
        },

        get currentPlayerIndex() {
            return Number(this.state?.turn?.currentPlayerIndex ?? 0);
        },

        isThrowing(index) {
            if (this.previewDemo) {
                return Number(index) === 1;
            }

            return !this.isFinished && this.currentPlayerIndex === Number(index);
        },

        get legOpenerIndex() {
            return Number(this.state?.turn?.legOpenerIndex ?? this.state?.legOpenerIndex ?? 0);
        },

        isLegOpener(index) {
            if (this.previewDemo) {
                return Number(index) === 0;
            }

            if (this.isFinished) {
                return false;
            }

            return this.legOpenerIndex === Number(index);
        },

        lastVisitForPlayer(playerId) {
            const list = this.visitsForPlayer(playerId);
            if (list.length === 0) {
                return null;
            }

            return list[list.length - 1];
        },

        lastVisitLabel(playerId) {
            const visit = this.lastVisitForPlayer(playerId);
            if (!visit) {
                return '';
            }
            if (visit.bust) {
                return 'BUST';
            }

            return String(visit.score);
        },

        lastVisitIsBust(playerId) {
            return Boolean(this.lastVisitForPlayer(playerId)?.bust);
        },

        lastVisitIs180(playerId) {
            return this.lastVisitTier(playerId) === '180';
        },

        lastVisitTier(playerId) {
            return visitHighlightTier(this.lastVisitForPlayer(playerId));
        },

        lastVisitIsCheckout(playerId) {
            return Boolean(this.lastVisitForPlayer(playerId)?.closedLeg);
        },

        get latestVisit() {
            const list = this.visits;
            if (list.length === 0) {
                return null;
            }

            return list[list.length - 1];
        },

        get highlightVisit() {
            return this.state?.lastEvent ?? this.latestVisit;
        },

        get lastVisitSignature() {
            const visit = this.overlayFx ? this.highlightVisit : this.latestVisit;
            if (!visit) {
                return '';
            }

            return [
                visit.id,
                visit.score,
                visit.bust,
                visit.closedLeg,
                visit.dartsInVisit,
                visit.legDarts,
            ].join('-');
        },

        onLastVisitChanged() {
            if (!this.overlayFx) {
                return;
            }
            const visit = this.highlightVisit;
            if (!visit || visit.bust) {
                return;
            }
            if (visit.closedLeg) {
                this.showCallout({
                    type: 'shot',
                    darts: Number(visit.legDarts || visit.dartsInVisit || 0),
                    score: Number(visit.score),
                }, 2800);
                return;
            }
            const tier = visitHighlightTier(visit);
            if (!tier) {
                return;
            }
            const duration = {
                140: 1600,
                170: 2000,
                180: 2400,
            }[tier];
            this.showCallout({
                type: 'high',
                tier,
                score: Number(visit.score),
            }, duration);
        },

        showCallout(callout, durationMs) {
            this.flashCallout = callout;
            if (this.flashCalloutTimer) {
                clearTimeout(this.flashCalloutTimer);
            }
            this.flashCalloutTimer = setTimeout(() => {
                this.flashCallout = null;
            }, durationMs);
        },

        get calloutClass() {
            const callout = this.flashCallout;
            if (!callout) {
                return {};
            }

            return {
                'is-shot': callout.type === 'shot',
                'is-180': callout.type === 'high' && callout.tier === '180',
                'is-170': callout.type === 'high' && callout.tier === '170',
                'is-140': callout.type === 'high' && callout.tier === '140',
            };
        },

        get calloutMainText() {
            const callout = this.flashCallout;
            if (!callout) {
                return '';
            }
            if (callout.type === 'shot') {
                return dartShotLabel(callout.darts) || 'GAME SHOT';
            }

            return String(callout.score ?? '');
        },

        get calloutSubText() {
            const callout = this.flashCallout;
            if (!callout || callout.type !== 'shot') {
                return '';
            }

            return callout.score ? String(callout.score) : '';
        },

        remainingTarget(player, index) {
            if (this.isFinished) {
                return '—';
            }
            if (player?.remaining == null) {
                return '—';
            }

            return player.remaining;
        },

        remainingDisplay(player, index) {
            const shown = this.remainingShown[Number(index)];

            return shown == null ? this.remainingTarget(player, index) : shown;
        },

        get remainingSignature() {
            return `${this.remainingTarget(this.player1, 0)}|${this.remainingTarget(this.player2, 1)}`;
        },

        snapRemainingFromState() {
            this.cancelRemainingTicks();
            this.remainingShown = [
                this.remainingTarget(this.player1, 0),
                this.remainingTarget(this.player2, 1),
            ];
        },

        tickRemainingFromState() {
            this.animateRemainingSlot(0, this.player1);
            this.animateRemainingSlot(1, this.player2);
        },

        animateRemainingSlot(index, player) {
            const to = this.remainingTarget(player, index);
            const from = this.remainingShown[index] ?? to;
            if (from === to) {
                return;
            }
            if (this.remainingTickCancel[index]) {
                this.remainingTickCancel[index]();
            }
            const cancelList = [...this.remainingTickCancel];
            cancelList[index] = animateScoreDown(from, to, (value) => {
                const next = [...this.remainingShown];
                next[index] = value;
                this.remainingShown = next;
            });
            this.remainingTickCancel = cancelList;
        },

        cancelRemainingTicks() {
            this.remainingTickCancel.forEach((cancel) => {
                if (typeof cancel === 'function') {
                    cancel();
                }
            });
            this.remainingTickCancel = [null, null];
        },

        get overlayPrimaryLeft() {
            return this.matchScore(this.player1);
        },

        get overlayPrimaryRight() {
            return this.matchScore(this.player2);
        },

        get overlayPrimaryUnit() {
            return this.isSingleSetFormat() ? 'LEGI' : 'SETY';
        },

        get overlayLegsLeft() {
            return this.legsInSet(this.player1);
        },

        get overlayLegsRight() {
            return this.legsInSet(this.player2);
        },

        get overlayLegLabel() {
            const leg = this.state?.currentLeg;
            if (this.isFinished) {
                return 'Koniec meczu';
            }
            if (!leg) {
                return '—';
            }
            if (this.isSingleSetFormat()) {
                return `Leg ${leg.legNumber}`;
            }

            return `Set ${this.currentSetNumber} · Leg ${leg.legNumber}`;
        },
    }));
}
