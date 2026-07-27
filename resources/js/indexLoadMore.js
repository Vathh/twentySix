/** Wspólny Alpine: listy index z „Załaduj więcej”. */
export function registerIndexLoadMore(Alpine) {
	Alpine.data('indexLoadMore', (config) => ({
		items: config.items ?? [],
		page: 1,
		hasMore: !!config.hasMore,
		loading: false,

		async loadMore() {
			if (this.loading || !this.hasMore || !config.url) {
				return;
			}
			this.loading = true;
			try {
				const res = await fetch(`${config.url}?page=${this.page + 1}`, {
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});
				if (!res.ok) {
					return;
				}
				const data = await res.json();
				this.items = this.items.concat(data.items ?? []);
				this.hasMore = !!data.has_more;
				this.page += 1;
			} finally {
				this.loading = false;
			}
		},
	}));
}
