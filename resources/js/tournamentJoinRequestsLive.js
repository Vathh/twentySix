import Pusher from 'pusher-js';

const JOIN_EVENTS = ['join.requests.updated', '.join.requests.updated'];

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

/** Ścieżka same-origin — WS może przynieść absolutny URL z Hosta API (telefon ≠ przeglądarka admina). */
function toSameOriginUrl(url) {
	const raw = String(url ?? '').trim();
	if (!raw) return '';
	if (raw.startsWith('/')) return raw;
	try {
		const parsed = new URL(raw, window.location.origin);
		return parsed.pathname + parsed.search;
	} catch {
		return raw;
	}
}

/** Rejestruje Alpine przed Alpine.start(). */
export function registerTournamentJoinRequestsLive(Alpine) {
	Alpine.store('tournamentStartLive', {
		participants: [],
		participantCount: 0,
		minPlayers: 4,
		canManage: true,
		csrfToken: '',
		inviteUrl: '',
		flash: null,
		flashTimer: null,
		busyKey: null,
		invitationPipeline: [],
		inviteBusyKey: null,

		init(config = {}) {
			this.participants = Array.isArray(config.participants) ? [...config.participants] : [];
			this.participantCount = Number(config.participantCount ?? this.participants.length) || 0;
			this.minPlayers = Number(config.minPlayers ?? 4) || 4;
			this.canManage = config.canManage !== false;
			this.csrfToken = config.csrfToken || this.csrfToken || '';
			this.inviteUrl = config.inviteUrl || this.inviteUrl || '';
			if (Array.isArray(config.invitationPipeline)) {
				this.invitationPipeline = [...config.invitationPipeline];
			}
		},

		applyParticipants(payload) {
			if (!payload || !Array.isArray(payload.participants)) {
				return;
			}
			this.participants = payload.participants;
			this.participantCount =
				Number(payload.participantCount ?? payload.participants.length) || 0;
			window.dispatchEvent(
				new CustomEvent('tournament-participant-count', {
					detail: { participantCount: this.participantCount },
				}),
			);
		},

		applyInvitationPipeline(payload) {
			if (!payload || !Array.isArray(payload.invitationPipeline)) {
				return;
			}
			this.invitationPipeline = payload.invitationPipeline;
		},

		showFlash(message, type = 'success') {
			if (this.flashTimer) {
				clearTimeout(this.flashTimer);
			}
			this.flash = { message, type };
			this.flashTimer = setTimeout(() => {
				this.flash = null;
				this.flashTimer = null;
			}, 3500);
		},

		participantKey(p) {
			return `${p?.kind || ''}-${p?.invitationId || p?.playerId || ''}`;
		},

		async removeParticipant(p) {
			if (!p?.removeUrl || this.busyKey) return;
			const key = this.participantKey(p);
			this.busyKey = key;
			try {
				const isGuest = p.kind === 'guest';
				const res = await fetch(toSameOriginUrl(p.removeUrl), {
					method: isGuest ? 'DELETE' : 'POST',
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': this.csrfToken,
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify(isGuest ? { player_id: p.playerId } : {}),
				});
				const data = await res.json().catch(() => ({}));
				if (!res.ok) {
					this.showFlash(data.message || 'Nie udało się usunąć uczestnika.', 'error');
					return;
				}
				this.applyParticipants(data);
				this.showFlash(
					data.message || (isGuest ? 'Gość usunięty z turnieju' : 'Uczestnik usunięty z turnieju'),
					'success',
				);
			} catch {
				this.showFlash('Błąd sieci — spróbuj ponownie.', 'error');
			} finally {
				this.busyKey = null;
			}
		},

		async sendInvite(userId, busyKey = null) {
			if (!this.inviteUrl || !userId || this.inviteBusyKey) {
				return { ok: false };
			}
			this.inviteBusyKey = busyKey ?? String(userId);
			try {
				const res = await fetch(toSameOriginUrl(this.inviteUrl), {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': this.csrfToken,
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify({ user_id: userId }),
				});
				const data = await res.json().catch(() => ({}));
				if (!res.ok) {
					this.showFlash(data.message || 'Nie udało się wysłać zaproszenia.', 'error');
					return { ok: false };
				}
				this.applyInvitationPipeline(data);
				this.showFlash(data.message || 'Zaproszenie wysłane', 'success');
				return { ok: true, data };
			} catch {
				this.showFlash('Błąd sieci — spróbuj ponownie.', 'error');
				return { ok: false };
			} finally {
				this.inviteBusyKey = null;
			}
		},

		async cancelInvitation(inv) {
			if (!inv?.cancelUrl || this.inviteBusyKey) return;
			this.inviteBusyKey = `cancel-${inv.id}`;
			try {
				const res = await fetch(toSameOriginUrl(inv.cancelUrl), {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': this.csrfToken,
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify({}),
				});
				const data = await res.json().catch(() => ({}));
				if (!res.ok) {
					this.showFlash(data.message || 'Nie udało się anulować zaproszenia.', 'error');
					return;
				}
				this.applyInvitationPipeline(data);
				this.showFlash(data.message || 'Zaproszenie anulowane', 'success');
			} catch {
				this.showFlash('Błąd sieci — spróbuj ponownie.', 'error');
			} finally {
				this.inviteBusyKey = null;
			}
		},

		async reinvite(inv) {
			if (!inv?.userId) return;
			await this.sendInvite(inv.userId, `reinvite-${inv.id}`);
		},
	});

	Alpine.data('tournamentUserSearch', (config) => ({
		query: '',
		results: [],
		searched: false,
		loading: false,
		searchUrl: config.searchUrl || '',
		csrfToken: config.csrfToken || '',

		async search() {
			const q = String(this.query || '').trim();
			if (q.length < 2) {
				this.$store.tournamentStartLive.showFlash(
					'Wpisz co najmniej 2 znaki, aby wyszukać użytkowników.',
					'error',
				);
				return;
			}
			if (!this.searchUrl || this.loading) return;

			this.loading = true;
			try {
				const url = new URL(toSameOriginUrl(this.searchUrl), window.location.origin);
				url.searchParams.set('q', q);
				const res = await fetch(url.pathname + url.search, {
					headers: { Accept: 'application/json' },
					credentials: 'same-origin',
				});
				const data = await res.json().catch(() => ({}));
				if (!res.ok) {
					this.$store.tournamentStartLive.showFlash(
						data.message || 'Nie udało się wyszukać.',
						'error',
					);
					return;
				}
				this.results = Array.isArray(data.users) ? data.users : [];
				this.searched = true;
			} catch {
				this.$store.tournamentStartLive.showFlash('Błąd sieci — spróbuj ponownie.', 'error');
			} finally {
				this.loading = false;
			}
		},

		async invite(user) {
			if (!user?.id) return;
			const result = await this.$store.tournamentStartLive.sendInvite(user.id, `search-${user.id}`);
			if (result.ok) {
				this.results = this.results.filter((u) => u.id !== user.id);
			}
		},
	}));

	Alpine.data('tournamentJoinRequestsLive', (config) => ({
		connection: 'connecting',
		pusher: null,
		pollTimer: null,
		requests: Array.isArray(config.initialRequests) ? [...config.initialRequests] : [],
		csrfToken: config.csrfToken || '',
		busyId: null,

		init() {
			this.$store.tournamentStartLive.init({
				participants: config.initialParticipants,
				participantCount: config.participantCount,
				minPlayers: config.minPlayers,
				canManage: config.canManage,
				csrfToken: config.csrfToken,
				inviteUrl: config.inviteUrl,
				invitationPipeline: config.invitationPipeline,
			});
			this.connectWebSocket(config);
			this.pollTimer = setInterval(() => this.fetchSnapshot(), 20000);
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

		connectionLabel() {
			if (this.connection === 'live') return 'Na żywo';
			if (this.connection === 'reconnecting') return 'Ponowne łączenie…';
			if (this.connection === 'error' || this.connection === 'offline') return 'Offline';
			return 'Łączenie…';
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

			JOIN_EVENTS.forEach((eventName) => {
				channel.bind(eventName, (payload) => {
					const next = normalizePayload(payload);
					if (next && Array.isArray(next.requests)) {
						this.requests = next.requests;
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

		async fetchSnapshot() {
			if (!config.snapshotUrl) return;
			try {
				const res = await fetch(config.snapshotUrl, {
					headers: { Accept: 'application/json' },
					credentials: 'same-origin',
				});
				if (!res.ok) return;
				const data = await res.json();
				if (Array.isArray(data.requests)) {
					this.requests = data.requests;
				}
			} catch {
				// ignore poll errors
			}
		},

		async resolveRequest(req, action) {
			if (!req || this.busyId) return;
			const url = toSameOriginUrl(
				action === 'approve' ? req.approveUrl : req.rejectUrl,
			);
			if (!url) return;

			this.busyId = req.id;
			try {
				const res = await fetch(url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': this.csrfToken,
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify({}),
				});
				const data = await res.json().catch(() => ({}));
				if (!res.ok) {
					this.$store.tournamentStartLive.showFlash(
						data.message || 'Nie udało się wykonać akcji.',
						'error',
					);
					return;
				}
				if (Array.isArray(data.requests)) {
					this.requests = data.requests;
				} else {
					this.requests = this.requests.filter((r) => r.id !== req.id);
				}
				this.$store.tournamentStartLive.applyParticipants(data);
				this.$store.tournamentStartLive.showFlash(
					data.message || (action === 'approve' ? 'Dołączono' : 'Odrzucono'),
					'success',
				);
			} catch {
				this.$store.tournamentStartLive.showFlash(
					'Błąd sieci — spróbuj ponownie.',
					'error',
				);
			} finally {
				this.busyId = null;
			}
		},
	}));
}
