/** Pula powiązanych użytkowników: wyszukiwanie, dodawanie i usuwanie bez przeładowania strony. */
export function registerRelatedUserSearch(Alpine) {
	Alpine.data('relatedUserSearch', (config) => ({
		query: '',
		results: [],
		searched: false,
		loading: false,
		busyKey: null,
		minChars: config.minChars ?? 5,
		searchUrl: config.searchUrl ?? '',
		addUrl: config.addUrl ?? '',
		removeUrl: config.removeUrl ?? '',
		cancelUrlTemplate: config.cancelUrlTemplate ?? '',
		addLabel: config.addLabel ?? 'Dodaj',
		csrfToken: config.csrfToken ?? '',
		related: Array.isArray(config.related) ? [...config.related] : [],
		pending: Array.isArray(config.pending) ? [...config.pending] : [],

		notify(text, type = 'error') {
			window.dispatchEvent(new CustomEvent('app-toast', {
				detail: { type, text },
			}));
		},

		sortByName(list) {
			return [...list].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'pl'));
		},

		cancelUrl(invitationId) {
			return String(this.cancelUrlTemplate).replace('__ID__', String(invitationId));
		},

		async search() {
			const query = String(this.query || '').trim();
			if (query.length < this.minChars) {
				this.notify(`Wpisz co najmniej ${this.minChars} znaków, aby wyszukać użytkowników.`);
				return;
			}
			if (!this.searchUrl || this.loading) {
				return;
			}

			this.loading = true;
			try {
				const url = new URL(this.searchUrl, window.location.origin);
				url.searchParams.set('q', query);
				const data = await this.request(url.pathname + url.search, 'GET');
				this.results = Array.isArray(data.users) ? data.users : [];
				this.searched = true;
			} catch (error) {
				this.notify(error instanceof Error ? error.message : 'Nie udało się wyszukać.');
			} finally {
				this.loading = false;
			}
		},

		async add(user) {
			if (!user?.id || this.busyKey) {
				return;
			}
			this.busyKey = `add-${user.id}`;
			try {
				const data = await this.request(this.addUrl, 'POST', { user_id: user.id });
				this.results = this.results.filter((item) => item.id !== user.id);
				if (data.invitation) {
					this.pending = this.sortByName([...this.pending, data.invitation]);
				} else if (data.user) {
					this.related = this.sortByName([...this.related, data.user]);
				}
				this.notify(data.message || 'Zapisano.', 'success');
			} catch (error) {
				this.notify(error instanceof Error ? error.message : 'Nie udało się dodać użytkownika.');
			} finally {
				this.busyKey = null;
			}
		},

		async remove(user) {
			if (!user?.id || this.busyKey || !this.removeUrl) {
				return;
			}
			this.busyKey = `remove-${user.id}`;
			try {
				const data = await this.request(this.removeUrl, 'DELETE', { user_id: user.id });
				this.related = this.related.filter((item) => item.id !== user.id);
				this.notify(data.message || 'Usunięto.', 'success');
			} catch (error) {
				this.notify(error instanceof Error ? error.message : 'Nie udało się usunąć użytkownika.');
			} finally {
				this.busyKey = null;
			}
		},

		async cancel(invitation) {
			if (!invitation?.id || this.busyKey || !this.cancelUrlTemplate) {
				return;
			}
			this.busyKey = `cancel-${invitation.id}`;
			try {
				const data = await this.request(this.cancelUrl(invitation.id), 'POST');
				this.pending = this.pending.filter((item) => item.id !== invitation.id);
				this.notify(data.message || 'Anulowano zaproszenie.', 'success');
			} catch (error) {
				this.notify(error instanceof Error ? error.message : 'Nie udało się anulować zaproszenia.');
			} finally {
				this.busyKey = null;
			}
		},

		async request(url, method, body = null) {
			const response = await fetch(url, {
				method,
				headers: {
					Accept: 'application/json',
					'X-CSRF-TOKEN': this.csrfToken,
					'X-Requested-With': 'XMLHttpRequest',
					...(body ? { 'Content-Type': 'application/json' } : {}),
				},
				credentials: 'same-origin',
				body: body ? JSON.stringify(body) : undefined,
			});
			const data = await response.json().catch(() => ({}));
			if (!response.ok) {
				const validationMessage = Object.values(data.errors ?? {}).flat()[0];
				throw new Error(validationMessage || data.message || 'Operacja nie powiodła się.');
			}
			return data;
		},
	}));
}
