/** Pasek znajomych między nawigacją a stopką. Lista ładuje się przy pierwszym otwarciu. */
export function registerFriendsPanel(Alpine) {
	Alpine.data('friendsPanel', (config) => ({
		collapsed: false,
		html: '',
		loading: false,
		loaded: false,
		error: false,

		init() {
			const stored = localStorage.getItem('friends-rail');
			const narrow = window.matchMedia('(max-width: 767px)').matches;
			this.collapsed = stored === '1' || (stored === null && narrow);
			this.sync();
			if (!this.collapsed) {
				this.load();
			}
		},

		toggle() {
			this.collapsed = !this.collapsed;
			localStorage.setItem('friends-rail', this.collapsed ? '1' : '0');
			this.sync();
			if (!this.collapsed) {
				this.load();
			}
		},

		sync() {
			document.documentElement.classList.toggle('friends-rail-collapsed', this.collapsed);
		},

		async load() {
			if (this.loading || this.loaded) {
				return;
			}
			this.error = false;
			this.loading = true;
			try {
				const res = await fetch(config.url, {
					headers: {
						Accept: 'text/html',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});
				if (!res.ok) {
					throw new Error('friends-panel');
				}
				this.html = await res.text();
				this.loaded = true;
			} catch {
				this.error = true;
				this.html = '';
			} finally {
				this.loading = false;
			}
		},
	}));
}
