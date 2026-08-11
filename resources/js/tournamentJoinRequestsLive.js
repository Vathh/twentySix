import Pusher from 'pusher-js';

const JOIN_EVENTS = ['join.requests.updated', '.join.requests.updated'];
const ROSTER_EVENTS = ['start.roster.updated', '.start.roster.updated'];

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
		createGuestUrl: '',
		addGuestUrl: '',
		flash: null,
		flashTimer: null,
		busyKey: null,
		invitationPipeline: [],
		inviteBusyKey: null,
		relatedGuests: [],
		guestName: '',
		guestBusy: false,

		init(config = {}) {
			this.participants = Array.isArray(config.participants) ? [...config.participants] : [];
			this.participantCount = Number(config.participantCount ?? this.participants.length) || 0;
			this.minPlayers = Number(config.minPlayers ?? 4) || 4;
			this.canManage = config.canManage !== false;
			this.csrfToken = config.csrfToken || this.csrfToken || '';
			this.inviteUrl = config.inviteUrl || this.inviteUrl || '';
			this.createGuestUrl = config.createGuestUrl || this.createGuestUrl || '';
			this.addGuestUrl = config.addGuestUrl || this.addGuestUrl || '';
			if (Array.isArray(config.invitationPipeline)) {
				this.invitationPipeline = [...config.invitationPipeline];
			}
			if (Array.isArray(config.relatedGuests)) {
				this.relatedGuests = config.relatedGuests.map((g) => ({ ...g }));
			}
		},

		applyParticipants(payload) {
			if (!payload || !Array.isArray(payload.participants)) {
				return;
			}
			this.participants = payload.participants;
			this.participantCount =
				Number(payload.participantCount ?? payload.participants.length) || 0;
			this.syncRelatedGuestsFromParticipants();
			window.dispatchEvent(
				new CustomEvent('tournament-participant-count', {
					detail: { participantCount: this.participantCount },
				}),
			);
		},

		syncRelatedGuestsFromParticipants() {
			if (!Array.isArray(this.relatedGuests) || this.relatedGuests.length === 0) {
				return;
			}
			const ids = new Set(
				this.participants
					.filter((p) => p.kind === 'guest')
					.map((p) => Number(p.playerId)),
			);
			this.relatedGuests = this.relatedGuests.map((g) => ({
				...g,
				inTournament: ids.has(Number(g.playerId)),
			}));
		},

		applyInvitationPipeline(payload) {
			if (!payload || !Array.isArray(payload.invitationPipeline)) {
				return;
			}
			this.invitationPipeline = payload.invitationPipeline;
		},

		applyRoster(payload) {
			this.applyParticipants(payload);
			this.applyInvitationPipeline(payload);
			if (payload && payload.minPlayers != null) {
				this.minPlayers = Number(payload.minPlayers) || this.minPlayers;
			}
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
				if (Array.isArray(data.invitationPipeline)) {
					this.applyInvitationPipeline(data);
				}
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

		async createGuest() {
			const name = String(this.guestName || '').trim();
			if (!this.createGuestUrl || !name || this.guestBusy) {
				if (!name) {
					this.showFlash('Podaj imię gościa.', 'error');
				}
				return;
			}
			this.guestBusy = true;
			try {
				const res = await fetch(toSameOriginUrl(this.createGuestUrl), {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': this.csrfToken,
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify({ name }),
				});
				const data = await res.json().catch(() => ({}));
				if (!res.ok) {
					const validation =
						data.errors?.name?.[0] || data.message || 'Nie udało się dodać gościa.';
					this.showFlash(validation, 'error');
					return;
				}
				this.applyRoster(data);
				this.guestName = '';
				this.showFlash(data.message || 'Gość dodany do turnieju', 'success');
			} catch {
				this.showFlash('Błąd sieci — spróbuj ponownie.', 'error');
			} finally {
				this.guestBusy = false;
			}
		},

		async addRelatedGuest(guest) {
			if (!guest?.playerId || !this.addGuestUrl || this.busyKey) {
				return;
			}
			const key = `related-${guest.playerId}`;
			this.busyKey = key;
			try {
				const res = await fetch(toSameOriginUrl(this.addGuestUrl), {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': this.csrfToken,
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify({ player_id: guest.playerId }),
				});
				const data = await res.json().catch(() => ({}));
				if (!res.ok) {
					this.showFlash(data.message || 'Nie udało się dodać gościa.', 'error');
					return;
				}
				this.applyRoster(data);
				this.showFlash(data.message || 'Gość dodany do turnieju', 'success');
			} catch {
				this.showFlash('Błąd sieci — spróbuj ponownie.', 'error');
			} finally {
				this.busyKey = null;
			}
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
				createGuestUrl: config.createGuestUrl,
				addGuestUrl: config.addGuestUrl,
				invitationPipeline: config.invitationPipeline,
				relatedGuests: config.relatedGuests,
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

		applyLivePayload(payload) {
			const next = normalizePayload(payload);
			if (!next) {
				return;
			}
			if (Array.isArray(next.requests)) {
				this.requests = next.requests;
			}
			this.$store.tournamentStartLive.applyRoster(next);
			this.connection = 'live';
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
					this.applyLivePayload(payload);
				});
			});

			ROSTER_EVENTS.forEach((eventName) => {
				channel.bind(eventName, (payload) => {
					this.applyLivePayload(payload);
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
				this.applyLivePayload(data);
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
				this.$store.tournamentStartLive.applyRoster(data);
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
