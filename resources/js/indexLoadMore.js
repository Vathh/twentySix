/** Wspólny Alpine: katalog organizacji, sezonów i turniejów. */
export function registerIndexLoadMore(Alpine) {
	Alpine.data('indexLoadMore', (config) => ({
		items: config.items ?? [],
		page: config.page ?? 1,
		hasMore: !!config.hasMore,
		loading: false,
		searching: false,
		query: config.query ?? '',
		searchedQuery: (config.query ?? '').trim(),
		pendingQuery: (config.query ?? '').trim(),
		summary: config.summary ?? null,
		searchError: '',
		sort: config.sort ?? 'activity',
		sortOpen: false,
		status: config.status ?? '',
		kind: config.kind ?? 'organization',
		requestId: 0,

		get isNarrowed() {
			return this.searchedQuery !== '' || this.status !== '';
		},

		get emptyHeading() {
			return this.isNarrowed ? 'Brak wyników' : config.emptyTitle;
		},

		get emptyCopy() {
			if (this.searchedQuery && this.status) {
				return 'Nic nie pasuje do „' + this.searchedQuery + '” przy wybranym statusie.';
			}
			if (this.searchedQuery) {
				return 'Nic nie pasuje do „' + this.searchedQuery + '”.';
			}
			if (this.status) {
				const noun = this.kind === 'season' ? 'sezon' : 'turniej';

				return 'Żaden ' + noun + ' nie ma wybranego statusu.';
			}

			return config.emptyDescription;
		},

		async search() {
			const q = this.query.trim();
			if (q === this.pendingQuery) {
				return;
			}

			this.pendingQuery = q;
			await this.load(q);
		},

		chooseSort(value) {
			this.sortOpen = false;
			if (this.sort === value) {
				return;
			}

			this.sort = value;

			return this.changeSort();
		},

		changeSort() {
			return this.load(this.query.trim());
		},

		changeStatus() {
			return this.load(this.query.trim());
		},

		clearSearch() {
			this.query = '';
			this.search();
		},

		async load(q) {
			const id = ++this.requestId;
			this.searching = true;
			this.searchError = '';

			try {
				const data = await this.fetchPage(1, q);
				if (id !== this.requestId) {
					return;
				}
				this.searchedQuery = q;
				this.pendingQuery = q;
				this.page = 1;
				this.items = data.items ?? [];
				this.hasMore = !!data.has_more;
				this.summary = data.summary ?? null;
				this.syncUrl(q);
			} catch {
				if (id === this.requestId) {
					this.pendingQuery = this.searchedQuery;
					this.searchError = 'Nie udało się odświeżyć listy.';
				}
			} finally {
				if (id === this.requestId) {
					this.searching = false;
				}
			}
		},

		async loadMore() {
			if (this.loading || this.searching || !this.hasMore || !config.url) {
				return;
			}
			this.loading = true;
			try {
				const data = await this.fetchPage(this.page + 1, this.searchedQuery);
				this.items = this.items.concat(data.items ?? []);
				this.hasMore = !!data.has_more;
				this.page += 1;
			} catch {
				// Zostaw już wczytaną listę.
			} finally {
				this.loading = false;
			}
		},

		async fetchPage(page, q) {
			const url = new URL(config.url, window.location.origin);
			url.searchParams.set('page', String(page));
			if (q) {
				url.searchParams.set('q', q);
			}
			if (this.sort && this.sort !== 'activity') {
				url.searchParams.set('sort', this.sort);
			}
			if (this.status) {
				url.searchParams.set('status', this.status);
			}
			const res = await fetch(url, {
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			});
			if (!res.ok) {
				throw new Error('catalog');
			}

			return res.json();
		},

		syncUrl(q) {
			const url = new URL(window.location.href);
			if (q) {
				url.searchParams.set('q', q);
			} else {
				url.searchParams.delete('q');
			}
			if (this.sort && this.sort !== 'activity') {
				url.searchParams.set('sort', this.sort);
			} else {
				url.searchParams.delete('sort');
			}
			if (this.status) {
				url.searchParams.set('status', this.status);
			} else {
				url.searchParams.delete('status');
			}
			url.searchParams.delete('page');
			window.history.replaceState(null, '', url);
		},
	}));
}
