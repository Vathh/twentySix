import Pusher from 'pusher-js';
import { formatAverage } from './formatAverage.js';

const BRACKET_EVENTS = ['playoff.bracket.updated', '.playoff.bracket.updated'];
const CARD_BASE = 'bracket-game-card';
const CARD_CLICKABLE = 'is-link';

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

function hrefForGame(game, urls) {
	if (!game?.id) {
		return null;
	}
	if (game.status === 'in_progress') {
		return `${urls.liveBase}/${game.id}/live`;
	}
	if (game.status === 'finished') {
		return `${urls.showBase}/${game.id}`;
	}
	if (game.status === 'scheduled' && game.player1Id && game.player2Id) {
		return `${urls.showBase}/${game.id}`;
	}

	return null;
}

function averageText(value) {
	if (value == null || value === '') {
		return null;
	}
	const formatted = formatAverage(value, '');
	return formatted === '' ? null : formatted;
}

function setAverageEl(el, value) {
	if (!el) {
		return;
	}
	const text = averageText(value);
	el.textContent = text ?? '';
	el.hidden = text == null;
}

function formatScore(game, score) {
	if (game.hideScores) {
		return '';
	}
	if (game.status === 'in_progress' || game.status === 'finished') {
		return String(score ?? 0);
	}

	return score != null ? String(score) : '';
}

function applyRowWinner(row, isWinner) {
	row.classList.toggle('text-accent', isWinner);
	row.classList.toggle('font-semibold', isWinner);
}

function applyCardLink(card, url) {
	if (url) {
		card.setAttribute('href', url);
		card.className = `${CARD_BASE} ${CARD_CLICKABLE}`;
		card.removeAttribute('aria-disabled');
		card.removeAttribute('tabindex');
		card.style.pointerEvents = '';
	} else {
		card.setAttribute('href', '#');
		card.className = CARD_BASE;
		card.setAttribute('aria-disabled', 'true');
		card.setAttribute('tabindex', '-1');
		card.style.pointerEvents = 'none';
	}
}

/** Rejestruje Alpine przed Alpine.start(). */
export function registerTournamentPlayoffLive(Alpine) {
	Alpine.data('tournamentPlayoffLive', (config) => ({
		connection: 'connecting',
		pusher: null,
		pollTimer: null,

		init() {
			if (this.pusher) {
				return;
			}
			this.connectWebSocket(config);
			void this.fetchSnapshot();
			if (!this.pollTimer) {
				this.pollTimer = setInterval(() => this.fetchSnapshot(), 30000);
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

		connectWebSocket(cfg) {
			if (!cfg.reverb?.key || !cfg.channel) {
				this.connection = 'offline';
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

			this.pusher.connection.bind('connected', () => {
				this.connection = 'live';
			});
			this.pusher.connection.bind('disconnected', () => {
				if (this.connection === 'live') {
					this.connection = 'reconnecting';
				}
			});
			this.pusher.connection.bind('failed', () => {
				this.connection = 'offline';
			});
			this.pusher.connection.bind('unavailable', () => {
				this.connection = 'offline';
			});

			const channel = this.pusher.subscribe(cfg.channel);

			channel.bind('pusher:subscription_succeeded', () => {
				this.connection = 'live';
				void this.fetchSnapshot();
			});
			channel.bind('pusher:subscription_error', () => {
				this.connection = 'error';
			});

			BRACKET_EVENTS.forEach((eventName) => {
				channel.bind(eventName, (payload) => {
					const next = normalizePayload(payload);
					if (next) {
						this.applyEvent(next);
						this.connection = 'live';
					}
				});
			});
		},

		applyEvent(payload) {
			(payload.games ?? []).forEach((game) => this.applyGame(game));
		},

		applyGame(game) {
			const root = this.$el.querySelector(`[data-playoff-game-id="${game.id}"]`);
			if (!root) {
				return;
			}

			const name1 = root.querySelector('[data-playoff-p1-name]');
			const name2 = root.querySelector('[data-playoff-p2-name]');
			const score1 = root.querySelector('[data-playoff-p1-score]');
			const score2 = root.querySelector('[data-playoff-p2-score]');
			const row1 = root.querySelector('[data-playoff-p1-row]');
			const row2 = root.querySelector('[data-playoff-p2-row]');
			const card = root.querySelector('[data-playoff-game-card]');

			if (name1) {
				name1.textContent = game.player1Name || '—';
			}
			if (name2) {
				name2.textContent = game.player2Name || '—';
			}
			if (score1) {
				score1.textContent = formatScore(game, game.player1Score);
			}
			if (score2) {
				score2.textContent = formatScore(game, game.player2Score);
			}
			setAverageEl(root.querySelector('[data-playoff-p1-average]'), game.player1Average);
			setAverageEl(root.querySelector('[data-playoff-p2-average]'), game.player2Average);
			if (row1) {
				applyRowWinner(
					row1,
					!game.hideScores
						&& game.winnerId != null
						&& Number(game.winnerId) === Number(game.player1Id),
				);
			}
			if (row2) {
				applyRowWinner(
					row2,
					!game.hideScores
						&& game.winnerId != null
						&& Number(game.winnerId) === Number(game.player2Id),
				);
			}
			if (card) {
				applyCardLink(card, hrefForGame(game, config.urls));
			}
		},

		async fetchSnapshot() {
			if (!config.snapshotUrl) {
				return;
			}
			try {
				const res = await fetch(config.snapshotUrl, {
					headers: { Accept: 'application/json' },
					credentials: 'same-origin',
				});
				if (!res.ok) {
					return;
				}
				const data = await res.json();
				(data.games ?? []).forEach((game) => this.applyGame(game));
			} catch {
				// WebSocket is primary
			}
		},

		connectionLabel() {
			return {
				connecting: 'Łączenie…',
				live: 'Na żywo',
				reconnecting: 'Wznowienie…',
				error: 'Błąd połączenia',
				offline: 'Odświeżanie co 30 s',
			}[this.connection] ?? this.connection;
		},
	}));
}
