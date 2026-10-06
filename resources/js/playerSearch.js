/** Wyszukiwarka graczy: wyniki bez przeładowania strony. */
export function registerPlayerSearch(Alpine) {
	Alpine.data('playerSearch', (config) => ({
		query: config.query ?? '',
		lastQuery: config.query ?? '',
		results: Array.isArray(config.players) ? config.players : [],
		searched: String(config.query ?? '').trim() !== '',
		loading: false,
		error: '',
		searchUrl: config.searchUrl ?? '',

		resultLabel() {
			const count = this.results.length;
			if (count === 1) {
				return 'Znaleziono 1 gracza';
			}

			return `Znaleziono ${count} graczy`;
		},

		async search() {
			const query = String(this.query || '').trim();
			this.query = query;
			if (query === '') {
				this.error = 'Wpisz nazwę gracza.';
				return;
			}
			if (!this.searchUrl || this.loading) {
				return;
			}

			this.loading = true;
			this.error = '';
			try {
				const url = new URL(this.searchUrl, window.location.origin);
				url.searchParams.set('q', query);
				const response = await fetch(url.pathname + url.search, {
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					credentials: 'same-origin',
				});
				const data = await response.json().catch(() => ({}));
				if (!response.ok) {
					throw new Error(data.message || 'Nie udało się wyszukać.');
				}
				this.results = Array.isArray(data.players) ? data.players : [];
				this.lastQuery = query;
				this.searched = true;
				const next = new URL(window.location.href);
				next.searchParams.set('q', query);
				history.replaceState(null, '', `${next.pathname}${next.search}`);
			} catch (error) {
				this.error = error instanceof Error ? error.message : 'Nie udało się wyszukać.';
			} finally {
				this.loading = false;
			}
		},
	}));
}
