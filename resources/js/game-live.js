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

document.addEventListener('alpine:init', () => {
    Alpine.data('gameLiveViewer', (config) => ({
        state: config.initialState ?? null,
        tab: 'counter',
        connection: 'connecting',
        pollTimer: null,
        pusher: null,

        init() {
            this.connectWebSocket(config);
            this.pollTimer = setInterval(() => this.fetchState(), 30000);
        },

        destroy() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }
            if (this.pusher) {
                this.pusher.unsubscribe(config.channel);
                this.pusher.disconnect();
            }
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
});
