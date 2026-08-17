import Pusher from 'pusher-js';

const FFA_STATE_EVENTS = ['ffa.state.updated', '.ffa.state.updated'];

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

/** Publiczny podgląd live FFA (N graczy) — WS + poll. */
export function registerFfaLiveViewer(Alpine) {
    Alpine.data('ffaLiveViewer', (config) => ({
        state: config.initialState ?? null,
        tab: 'counter',
        connection: 'connecting',
        pollTimer: null,
        pusher: null,
        redirecting: false,

        init() {
            this.connectWebSocket(config);
            // Backup jak H2H: WS jest źródłem prawdy, poll tylko awaryjnie.
            this.pollTimer = setInterval(() => this.fetchState(), 30000);
            this.$watch(
                () => this.state?.game?.status ?? this.state?.session?.status,
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
            if (this.redirecting) {
                return;
            }
            this.redirecting = true;
            this.destroy();
            if (config.showUrl) {
                window.location.assign(config.showUrl);
                return;
            }
            window.location.assign('/');
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

            FFA_STATE_EVENTS.forEach((eventName) => {
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
                    const body = await res.json().catch(() => ({}));
                    if (body?.showUrl) {
                        config.showUrl = body.showUrl;
                    }
                    this.redirectToShow();
                    return;
                }
                if (res.ok) {
                    this.state = await res.json();
                    if (this.connection === 'offline' || this.connection === 'error') {
                        this.connection = 'offline';
                    }
                }
            } catch {
                // WS jest primary
            }
        },

        get isLive() {
            const status = this.state?.game?.status ?? this.state?.session?.status;
            return status === 'in_progress';
        },

        get isFinished() {
            const status = this.state?.game?.status ?? this.state?.session?.status;
            return status === 'finished';
        },

        get isCricket() {
            return String(this.state?.session?.gameType ?? '').toLowerCase() === 'cricket';
        },

        get isBob27() {
            return String(this.state?.session?.gameType ?? '').toLowerCase() === 'bob27';
        },

        get isAtc() {
            return String(this.state?.session?.gameType ?? '').toLowerCase() === 'atc';
        },

        get isCatch40() {
            return String(this.state?.session?.gameType ?? '').toLowerCase() === 'catch40';
        },

        get isCricket56() {
            return String(this.state?.session?.gameType ?? '').toLowerCase() === 'cricket56';
        },

        get currentTargetLabel() {
            return this.state?.session?.currentTargetLabel
                ?? this.state?.turn?.currentTargetLabel
                ?? '—';
        },

        get players() {
            return this.state?.players ?? [];
        },

        get currentPlayerIndex() {
            return Number(
                this.state?.turn?.currentPlayerIndex
                ?? this.state?.session?.currentPlayerIndex
                ?? -1,
            );
        },

        get matchFormat() {
            return this.state?.game?.matchFormat
                ?? this.state?.session?.matchFormat
                ?? null;
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
            return player.setsWon ?? player.legsWon ?? 0;
        },

        get visits() {
            return this.state?.visits ?? [];
        },

        get currentLegLabel() {
            const leg = this.state?.currentLeg;
            if (!leg) {
                return 'Brak otwartego lega';
            }
            const setNumber = Number(
                this.state?.session?.currentSetNumber
                ?? this.state?.game?.currentSetNumber
                ?? 1,
            );
            if (this.isSingleSetFormat()) {
                return `Leg ${leg.legNumber}`;
            }
            return `Set ${setNumber} · Leg ${leg.legNumber}`;
        },

        visitsForPlayer(playerId) {
            const id = Number(playerId);
            return (this.visits ?? []).filter((v) => Number(v.playerId) === id);
        },

        isCurrentTurn(player, index) {
            if (this.currentPlayerIndex >= 0) {
                return Number(index) === this.currentPlayerIndex;
            }
            return Number(player?.orderIndex) === this.currentPlayerIndex;
        },

        formatAverage(value) {
            if (value == null || Number.isNaN(Number(value))) {
                return '—';
            }
            return Number(value).toFixed(2);
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
