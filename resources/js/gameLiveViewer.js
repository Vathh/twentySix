import Pusher from 'pusher-js';

const GAME_STATE_EVENTS = ['game.state', '.game.state'];

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
            if (this.isFinished) {
                this.redirectToShow();
            }
        },

        destroy() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
            if (this.pusher) {
                this.pusher.unsubscribe(config.channel);
                this.pusher.disconnect();
                this.pusher = null;
            }
        },

        redirectToShow() {
            if (this.redirecting || !config.showUrl) {
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

        get currentLegLabel() {
            const leg = this.state?.currentLeg;
            if (!leg) {
                return 'Brak otwartego lega';
            }
            return `Leg ${leg.legNumber}`;
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
            if (value == null || Number.isNaN(Number(value))) {
                return '—';
            }
            return Number(value).toFixed(2);
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
    }));
}
