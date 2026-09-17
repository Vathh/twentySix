import Pusher from 'pusher-js';

const FINISHED_EVENTS = ['tournament.finished', '.tournament.finished'];

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
	return payload && typeof payload === 'object' ? payload : null;
}

function payloadLooksFinished(payload) {
	const data = normalizePayload(payload);
	if (!data) {
		return false;
	}

	return data.status === 'finished' || data.tournamentStatus === 'finished';
}

/** Odświeża stronę szczegółów po evencie WS `tournament.finished` (ten sam kanał co grupy/drabinka). */
export function registerTournamentPageLive(Alpine) {
	Alpine.data('tournamentPageLive', (config) => ({
		cancelOpen: !!config.cancelOpen,
		pusher: null,
		reloading: false,

		init() {
			if (this.pusher) {
				return;
			}
			this.connectWebSocket(config);
		},

		destroy() {
			if (this.pusher) {
				this.pusher.unsubscribe(config.channel);
				this.pusher.disconnect();
				this.pusher = null;
			}
		},

		reloadPage() {
			if (this.reloading) {
				return;
			}
			this.reloading = true;
			document.querySelectorAll('[data-cancel-play]').forEach((el) => el.remove());
			window.location.reload();
		},

		connectWebSocket(cfg) {
			if (!cfg.reverb?.key || !cfg.channel) {
				return;
			}

			const useTls = cfg.reverb.scheme === 'https';
			this.pusher = new Pusher(cfg.reverb.key, {
				cluster: 'mt1',
				wsHost: cfg.reverb.host,
				wsPort: cfg.reverb.port,
				wssPort: cfg.reverb.port,
				forceTLS: useTls,
				disableStats: true,
				enabledTransports: ['ws', 'wss'],
			});

			const channel = this.pusher.subscribe(cfg.channel);

			FINISHED_EVENTS.forEach((eventName) => {
				channel.bind(eventName, (payload) => {
					if (payloadLooksFinished(payload)) {
						this.reloadPage();
					}
				});
			});
		},
	}));
}
