import Pusher from 'pusher-js';

const PHASE_EVENTS = [
	'groups.matrix.updated',
	'.groups.matrix.updated',
	'playoff.bracket.updated',
	'.playoff.bracket.updated',
	'tournament.finished',
	'.tournament.finished',
];

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

function statusFromPayload(payload) {
	const data = normalizePayload(payload);
	if (!data) {
		return null;
	}
	if (typeof data.tournamentStatus === 'string' && data.tournamentStatus !== '') {
		return data.tournamentStatus;
	}
	if (data.status === 'finished') {
		return 'finished';
	}

	return null;
}

/** Pełnoekranowa tablica: dopasowanie do ekranu i przełączenie grup → drabinka. */
export function registerTournamentBoard(Alpine) {
	Alpine.data('tournamentBoard', (config) => ({
		fitted: false,
		reloading: false,
		pusher: null,
		pollTimer: null,
		fitTimer: null,
		observer: null,

		init() {
			this.fit();
			this.onResize = () => this.scheduleFit();
			this.onLoad = () => this.fit();
			window.addEventListener('resize', this.onResize);
			window.addEventListener('load', this.onLoad);
			if (document.fonts?.ready) {
				document.fonts.ready.then(() => this.fit());
			}
			const scale = this.$el.querySelector('[data-board-scale]');
			if (scale && typeof MutationObserver !== 'undefined') {
				this.observer = new MutationObserver(() => this.scheduleFit());
				this.observer.observe(scale, { childList: true, subtree: true, characterData: true });
			}
			if (config.status === 'finished') {
				return;
			}
			this.connect();
			if (!this.pollTimer) {
				this.pollTimer = setInterval(() => this.checkStatus(), 8000);
			}
		},

		destroy() {
			window.removeEventListener('resize', this.onResize);
			window.removeEventListener('load', this.onLoad);
			if (this.fitTimer) {
				clearTimeout(this.fitTimer);
				this.fitTimer = null;
			}
			if (this.pollTimer) {
				clearInterval(this.pollTimer);
				this.pollTimer = null;
			}
			if (this.observer) {
				this.observer.disconnect();
				this.observer = null;
			}
			if (this.pusher) {
				this.pusher.unsubscribe(config.channel);
				this.pusher.disconnect();
				this.pusher = null;
			}
		},

		scheduleFit() {
			if (this.fitTimer) {
				clearTimeout(this.fitTimer);
			}
			this.fitTimer = setTimeout(() => this.fit(), 150);
		},

		fit() {
			const stage = this.$el.querySelector('[data-board-stage]');
			const scale = this.$el.querySelector('[data-board-scale]');
			if (!stage || !scale) {
				this.fitted = true;
				return;
			}
			document.documentElement.style.fontSize = '';
			scale.style.zoom = '';
			scale.style.transform = 'none';
			const naturalW = scale.offsetWidth;
			const naturalH = scale.offsetHeight;
			const bounds = stage.getBoundingClientRect();
			const availW = Math.max(1, bounds.width - 12);
			const availH = Math.max(1, bounds.height - 12);
			if (naturalW < 1 || naturalH < 1 || bounds.width < 1 || bounds.height < 1) {
				this.fitted = true;
				return;
			}
			let zoom = Math.max(0.28, Math.min(availW / naturalW, availH / naturalH, 3));
			const apply = (value) => {
				zoom = Math.round(value * 100) / 100;
				scale.style.transformOrigin = 'center center';
				scale.style.transform = `scale(${zoom})`;
			};
			apply(zoom);
			for (let i = 0; i < 4; i++) {
				const rect = scale.getBoundingClientRect();
				if (rect.width <= bounds.width - 2 && rect.height <= bounds.height - 2) {
					break;
				}
				const fix = Math.min(availW / rect.width, availH / rect.height);
				apply(Math.max(0.28, zoom * fix));
			}
			this.fitted = true;
		},

		connect() {
			if (!config.reverb?.key || !config.channel) {
				return;
			}
			const useTls = config.reverb.scheme === 'https';
			this.pusher = new Pusher(config.reverb.key, {
				cluster: 'mt1',
				wsHost: config.reverb.host,
				wsPort: config.reverb.port,
				wssPort: config.reverb.port,
				forceTLS: useTls,
				disableStats: true,
				enabledTransports: ['ws', 'wss'],
			});
			const channel = this.pusher.subscribe(config.channel);
			PHASE_EVENTS.forEach((eventName) => {
				channel.bind(eventName, (payload) => {
					const next = statusFromPayload(payload);
					if (next) {
						this.maybeReload(next);
						return;
					}
					void this.checkStatus();
				});
			});
		},

		async checkStatus() {
			if (!config.statusUrl || this.reloading) {
				return;
			}
			try {
				const res = await fetch(config.statusUrl, {
					headers: { Accept: 'application/json' },
					credentials: 'same-origin',
				});
				if (!res.ok) {
					return;
				}
				const data = await res.json();
				this.maybeReload(data.tournamentStatus);
			} catch {
				// Kolejna próba przy następnym evencie albo pollu.
			}
		},

		maybeReload(next) {
			if (!next || next === config.status || this.reloading) {
				return;
			}
			this.reloading = true;
			window.location.reload();
		},
	}));
}
