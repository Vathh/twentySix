/** Okno zgłoszenia gracza albo komentarza. Otwiera je zdarzenie open-report. */
export function registerReportDialog(Alpine) {
	Alpine.data('reportDialog', () => ({
		visible: false,
		saved: false,
		title: 'Zgłoś',
		subjectType: null,
		subjectId: null,
		reason: 'spam',
		details: '',
		sending: false,
		error: '',

		open(detail) {
			if (!window.feedEngagement?.signedIn) {
				window.location = window.feedEngagement?.loginUrl || '/login';
				return;
			}
			this.title = detail?.title || 'Zgłoś';
			this.subjectType = detail?.subjectType || null;
			this.subjectId = detail?.subjectId ?? null;
			this.reason = 'spam';
			this.details = '';
			this.error = '';
			this.saved = false;
			this.sending = false;
			this.visible = true;
		},

		close() {
			if (this.sending) return;
			this.visible = false;
		},

		async submit() {
			if (this.sending || this.subjectId === null) return;
			this.sending = true;
			this.error = '';
			try {
				await request('/reports', 'POST', {
					subjectType: this.subjectType,
					subjectId: this.subjectId,
					reason: this.reason,
					details: this.details.trim(),
				});
				this.saved = true;
			} catch (error) {
				this.error = error.message;
			} finally {
				this.sending = false;
			}
		},
	}));
}

async function request(url, method, body) {
	const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
	const response = await fetch(url, {
		method,
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': token,
			'X-Requested-With': 'XMLHttpRequest',
		},
		body: body === undefined ? undefined : JSON.stringify(body),
	});
	const data = await response.json().catch(() => ({}));
	if (!response.ok) {
		throw new Error(data.message || 'Nie udało się zapisać.');
	}
	return data;
}
