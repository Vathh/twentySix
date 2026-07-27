import Pusher from 'pusher-js';

const MATRIX_EVENTS = ['groups.matrix.updated', '.groups.matrix.updated'];

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

function scoreForRow(game, rowPlayerId) {
	const rowId = Number(rowPlayerId);
	if (rowId === Number(game.player1Id)) {
		return `${game.player1Score} - ${game.player2Score}`;
	}
	return `${game.player2Score} - ${game.player1Score}`;
}

function hrefForGame(game, urls) {
	if (game.status === 'in_progress') {
		return `${urls.liveBase}/${game.id}/live`;
	}
	return `${urls.showBase}/${game.id}`;
}

function cellClassForStatus(game) {
	if (game.status === 'scheduled') {
		return 'text-text-muted hover:text-accent hover:underline';
	}
	return 'text-accent hover:underline';
}

/** Rejestruje Alpine przed Alpine.start(). */
export function registerTournamentGroupsLive(Alpine) {
	Alpine.data('tournamentGroupsLive', (config) => ({
		connection: 'connecting',
		pusher: null,
		pollTimer: null,

		init() {
			this.connectWebSocket(config);
			this.pollTimer = setInterval(() => this.fetchSnapshot(), 30000);
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

			MATRIX_EVENTS.forEach((eventName) => {
				channel.bind(eventName, (payload) => {
					const next = normalizePayload(payload);
					if (next) {
						this.applyEvent(next);
						this.connection = 'live';
					}
				});
			});

			this.pusher.connection.bind('disconnected', () => {
				if (this.connection === 'live') {
					this.connection = 'reconnecting';
				}
			});
		},

		applyEvent(payload) {
			if (payload.game) {
				this.applyGame(payload.game);
			}
			if (payload.includeStandings && Array.isArray(payload.standings)) {
				this.applyStandings(payload.groupNumber, payload.standings);
			}
			if (payload.includeStandings && payload.playoffHighlight) {
				this.applyPlayoffHighlight(payload.groupNumber, payload.playoffHighlight);
			}
		},

		applyGame(game) {
			const root = this.$el;
			const cells = root.querySelectorAll(`[data-group-game-id="${game.id}"]`);
			cells.forEach((td) => {
				const rowPlayerId = td.getAttribute('data-row-player-id');
				const link = td.querySelector('a[data-group-game-link]');
				if (!link) {
					return;
				}
				if (game.status === 'scheduled') {
					link.textContent = '—';
					link.setAttribute('title', 'Ustaw wynik / walkover');
				} else {
					link.textContent = scoreForRow(game, rowPlayerId);
					if (game.status === 'in_progress') {
						link.setAttribute('title', 'Podgląd na żywo');
					} else {
						link.removeAttribute('title');
					}
				}
				link.setAttribute('href', hrefForGame(game, config.urls));
				link.className = cellClassForStatus(game);
			});
		},

		applyStandings(groupNumber, standings) {
			const root = this.$el;
			standings.forEach((row) => {
				const map = {
					won: row.gamesWon,
					lost: row.gamesLost,
					diff: row.matchUnitsDifference,
					points: row.points,
					place: row.place,
				};
				Object.entries(map).forEach(([key, value]) => {
					const el = root.querySelector(
						`[data-group-standing="${groupNumber}"][data-player-id="${row.playerId}"][data-standing-field="${key}"]`,
					);
					if (el) {
						el.textContent = String(value);
					}
				});
			});
		},

		applyPlayoffHighlight(groupNumber, highlight) {
			const root = this.$el;
			const groupEl = root.querySelector(`[data-group-number="${groupNumber}"]`);
			if (!groupEl) {
				return;
			}

			const complete = !!highlight.complete;
			const advanceCount = Number(highlight.advanceCount ?? 0);
			const advancing = new Set(
				(highlight.advancingPlayerIds ?? []).map((id) => Number(id)),
			);

			const hint = groupEl.querySelector('[data-group-advance-hint]');
			if (hint) {
				if (complete && advanceCount > 0) {
					hint.classList.remove('hidden');
					const label = hint.querySelector('[data-group-advance-label]');
					if (label) {
						const word = advanceCount === 1 ? 'pozycja' : 'pozycje';
						label.textContent = `Awans do playoff: ${advanceCount} ${word}`;
					}
				} else {
					hint.classList.add('hidden');
				}
			}

			groupEl.querySelectorAll('tr[data-group-row-player-id]').forEach((tr) => {
				const playerId = Number(tr.getAttribute('data-group-row-player-id'));
				const advances = complete && advancing.has(playerId);
				tr.classList.toggle('bg-success-muted', advances);
				tr.classList.toggle('hover:bg-success-muted/80', advances);
				tr.classList.toggle('border-l-2', advances);
				tr.classList.toggle('border-l-success', advances);
				tr.classList.toggle('hover:bg-bg-elevated-hover', !advances);

				const badge = tr.querySelector('[data-playoff-badge]');
				if (badge) {
					badge.classList.toggle('hidden', !advances);
				}
			});
		},

		async fetchSnapshot() {
			if (!config.snapshotUrl) {
				return;
			}
			try {
				const res = await fetch(config.snapshotUrl, {
					headers: { Accept: 'application/json' },
				});
				if (!res.ok) {
					return;
				}
				const data = await res.json();
				(data.games ?? []).forEach((game) => this.applyGame(game));
				Object.entries(data.standingsByGroup ?? {}).forEach(([groupNumber, rows]) => {
					this.applyStandings(Number(groupNumber), rows);
				});
				Object.entries(data.playoffHighlights ?? {}).forEach(([groupNumber, highlight]) => {
					this.applyPlayoffHighlight(Number(groupNumber), highlight);
				});
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
