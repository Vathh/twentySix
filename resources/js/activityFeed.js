/** Dociąganie starszych wpisów tablicy, gdy sentinel wejdzie w widok. */

export function observeFeedEnd(node, onReach) {
	if (!node || typeof IntersectionObserver === 'undefined') {
		return;
	}

	const observer = new IntersectionObserver((entries) => {
		if (entries.some((entry) => entry.isIntersecting)) {
			onReach();
		}
	}, { rootMargin: '320px' });
	observer.observe(node);
}

export function registerHomeFeed(Alpine) {
	Alpine.data('homeFeed', (config) => ({
		extra: [],
		nextCursor: config.nextCursor ?? null,
		loading: false,
		error: '',
		paused: false,

		init() {
			this.$nextTick(() => {
				observeFeedEnd(this.$root.querySelector('[data-feed-end]'), () => this.loadOlder());
			});
		},

		feedAgo(iso) {
			const date = new Date(iso);
			if (Number.isNaN(date.getTime())) return '';
			const minutes = Math.round((Date.now() - date.getTime()) / 60000);
			if (minutes < 1) return 'przed chwilą';
			if (minutes < 60) return minutes + ' min temu';
			const hours = Math.round(minutes / 60);
			if (hours < 24) return hours + ' godz. temu';
			const days = Math.round(hours / 24);
			if (days === 1) return 'wczoraj';
			if (days < 30) return days + ' dni temu';
			return date.toLocaleDateString('pl-PL');
		},

		retryOlder() {
			this.paused = false;
			this.error = '';
			this.loadOlder();
		},

		async loadOlder() {
			if (this.loading || this.paused || !this.nextCursor) return;
			this.loading = true;
			this.error = '';
			try {
				const response = await fetch(config.url + '?cursor=' + encodeURIComponent(this.nextCursor), {
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});
				if (!response.ok) {
					throw new Error('feed');
				}
				const data = await response.json();
				this.extra = this.extra.concat(data.items || []);
				this.nextCursor = data.nextCursor || null;
			} catch (error) {
				this.error = 'Nie udało się wczytać starszych wpisów.';
				this.paused = true;
			} finally {
				this.loading = false;
			}
		},
	}));
}
