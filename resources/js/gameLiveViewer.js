import Pusher from 'pusher-js';
import { formatAverage as formatAverageValue } from './formatAverage.js';

const GAME_STATE_EVENTS = ['game.state', '.game.state'];
const CHECKOUT_BOGEYS = [159, 162, 163, 165, 166, 168, 169];

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
        flash180: false,
        flash180Timer: null,

        init() {
            this.connectWebSocket(config);
            this.pollTimer = setInterval(() => this.fetchState(), 30000);
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
            if (this.isFinished) {
                this.redirectToShow();
            }
        },

        destroy() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
            if (this.flash180Timer) {
                clearTimeout(this.flash180Timer);
                this.flash180Timer = null;
            }
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
            const visit = this.lastVisitForPlayer(playerId);

            return Boolean(visit && !visit.bust && Number(visit.score) === 180);
        },

        dartsInCurrentLeg(playerId) {
            if (this.previewDemo) {
                if (this.player1 && Number(playerId) === Number(this.player1.playerId)) {
                    return 9;
                }
                if (this.player2 && Number(playerId) === Number(this.player2.playerId)) {
                    return 6;
                }
            }

            return this.visitsForPlayer(playerId).reduce(
                (total, visit) => total + Number(visit.dartsInVisit ?? 0),
                0,
            );
        },

        get latestVisit() {
            const list = this.visits;
            if (list.length === 0) {
                return null;
            }

            return list[list.length - 1];
        },

        get lastVisitSignature() {
            const visit = this.latestVisit;
            if (!visit) {
                return '';
            }

            return `${visit.id}-${visit.score}-${visit.bust}`;
        },

        onLastVisitChanged() {
            const visit = this.latestVisit;
            if (!visit || visit.bust || Number(visit.score) !== 180) {
                return;
            }
            this.flash180 = true;
            if (this.flash180Timer) {
                clearTimeout(this.flash180Timer);
            }
            this.flash180Timer = setTimeout(() => {
                this.flash180 = false;
            }, 2200);
        },

        remainingDisplay(player, index) {
            if (this.isFinished) {
                const score1 = this.matchScore(this.player1);
                const score2 = this.matchScore(this.player2);
                if (score1 === score2) {
                    return '—';
                }
                const leader = score1 > score2 ? 0 : 1;

                return Number(index) === leader ? 0 : '—';
            }
            if (player?.remaining == null) {
                return '—';
            }

            return player.remaining;
        },

        isCheckoutRemaining(remaining) {
            const value = Number(remaining);
            if (!Number.isFinite(value) || value < 2 || value > 170) {
                return false;
            }

            return !CHECKOUT_BOGEYS.includes(value);
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
