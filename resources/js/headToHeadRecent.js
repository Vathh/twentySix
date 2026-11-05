/** Dociąga kolejne piątki spotkań H2H pod listą na widoku meczu. */
export function registerHeadToHeadRecent(Alpine) {
	Alpine.data('headToHeadRecent', (config) => ({
		extra: [],
		offset: config.offset ?? 0,
		hasMore: !!config.hasMore,
		loading: false,
		error: '',

		formatAverage(value) {
			if (value == null || value === '') {
				return '—';
			}
			const number = Number(value);
			if (Number.isNaN(number)) {
				return '—';
			}

			return number.toFixed(2);
		},

		async loadMore() {
			if (this.loading || !this.hasMore) {
				return;
			}
			this.loading = true;
			this.error = '';
			try {
				const response = await fetch(`${config.url}?offset=${this.offset}`, {
					headers: { Accept: 'application/json' },
				});
				if (!response.ok) {
					throw new Error('request failed');
				}
				const data = await response.json();
				const recent = Array.isArray(data.recent) ? data.recent : [];
				this.extra = this.extra.concat(recent);
				this.offset += recent.length;
				this.hasMore = !!data.hasMore;
			} catch (e) {
				this.error = 'Nie udało się wczytać kolejnych spotkań.';
			} finally {
				this.loading = false;
			}
		},
	}));
}
