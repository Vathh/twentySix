/** Komentarze i polubienia pod kartą tablicy. */
export function registerFeedPost(Alpine) {
	Alpine.data('feedPost', (item) => ({
		id: item.id,
		commentCount: item.commentCount ?? 0,
		reactions: item.reactions ?? { counts: {}, mine: null },
		canEngage: Boolean(item.canEngage),
		signedIn: Boolean(window.feedEngagement?.signedIn),
		viewerId: window.feedEngagement?.userId ?? null,
		authorUserId: item.actor?.userId ?? item.header?.userId ?? null,
		open: false,
		loaded: false,
		loading: false,
		sending: false,
		comments: [],
		body: '',
		replyTo: null,
		pendingDelete: null,
		error: '',
		termsAccepted: Boolean(window.feedEngagement?.termsAccepted),
		termsChecked: false,

		get likeCount() {
			return Number(this.reactions?.counts?.like ?? 0);
		},

		get liked() {
			return this.reactions?.mine === 'like';
		},

		get canModerateAuthor() {
			return this.signedIn && this.authorUserId && this.authorUserId !== this.viewerId;
		},

		otherPerson(userId) {
			return this.signedIn && userId && userId !== this.viewerId;
		},

		guard() {
			if (!this.signedIn) {
				window.location = window.feedEngagement?.loginUrl || '/login';
				return false;
			}
			return this.canEngage;
		},

		async toggleLike() {
			if (!this.guard() || this.sending) return;
			this.error = '';
			this.sending = true;
			try {
				const data = await request(`/feed/activities/${this.id}/reaction`, 'PUT', {
					kind: this.liked ? null : 'like',
				});
				this.reactions = data.reactions;
			} catch (error) {
				this.error = error.message;
			} finally {
				this.sending = false;
			}
		},

		async toggleComments() {
			if (!this.guard()) return;
			this.open = !this.open;
			if (this.open && !this.loaded) {
				await this.loadComments();
			}
		},

		async loadComments() {
			this.loading = true;
			this.error = '';
			try {
				const data = await request(`/feed/activities/${this.id}/comments`, 'GET');
				this.comments = data.comments ?? [];
				this.loaded = true;
			} catch (error) {
				this.error = error.message;
				this.open = false;
			} finally {
				this.loading = false;
			}
		},

		startReply(comment) {
			this.replyTo = { id: comment.id, name: comment.author?.name || 'Gracz' };
			this.pendingDelete = null;
		},

		async send() {
			if (!this.guard() || this.sending) return;
			const body = this.body.trim();
			if (body === '') return;
			this.sending = true;
			this.error = '';
			try {
				if (!this.termsAccepted) {
					if (!this.termsChecked) {
						this.error = 'Zaakceptuj regulamin i politykę prywatności.';
						return;
					}
					await request(window.feedEngagement.termsUrl, 'POST', {});
					this.termsAccepted = true;
					window.feedEngagement.termsAccepted = true;
				}
				const data = await request(`/feed/activities/${this.id}/comments`, 'POST', {
					body,
					parentId: this.replyTo?.id ?? null,
				});
				this.place(data.comment);
				this.commentCount = data.commentCount;
				this.body = '';
				this.replyTo = null;
				this.open = true;
				this.loaded = true;
			} catch (error) {
				this.error = error.message;
				if (error.code === 'terms_required') {
					this.termsAccepted = false;
				}
			} finally {
				this.sending = false;
			}
		},

		async remove(comment) {
			if (this.pendingDelete !== comment.id) {
				this.pendingDelete = comment.id;
				return;
			}
			this.sending = true;
			this.error = '';
			try {
				const data = await request(`/feed/comments/${comment.id}`, 'DELETE');
				this.comments = this.comments
					.filter((row) => row.id !== comment.id)
					.map((row) => ({
						...row,
						replies: (row.replies ?? []).filter((reply) => reply.id !== comment.id),
					}));
				this.commentCount = data.commentCount;
				this.pendingDelete = null;
			} catch (error) {
				this.error = error.message;
			} finally {
				this.sending = false;
			}
		},

		async likeComment(comment) {
			if (!this.guard() || this.sending) return;
			this.sending = true;
			this.error = '';
			try {
				const data = await request(`/feed/comments/${comment.id}/reaction`, 'PUT', {
					kind: comment.reactions?.mine === 'like' ? null : 'like',
				});
				this.applyCommentReaction(comment.id, data.reactions);
			} catch (error) {
				this.error = error.message;
			} finally {
				this.sending = false;
			}
		},

		place(comment) {
			if (comment.parentId) {
				const parent = this.comments.find((row) => row.id === comment.parentId);
				if (parent) {
					parent.replies = [...(parent.replies ?? []), { ...comment, replies: [] }];
					return;
				}
			}
			this.comments = [...this.comments, { ...comment, replies: comment.replies ?? [] }];
		},

		applyCommentReaction(id, reactions) {
			const touch = (comment) => (comment.id === id ? { ...comment, reactions } : comment);
			this.comments = this.comments.map((comment) => ({
				...touch(comment),
				replies: (comment.replies ?? []).map(touch),
			}));
		},

		likesOf(comment) {
			return Number(comment?.reactions?.counts?.like ?? 0);
		},

		async blockUser(userId) {
			if (!this.otherPerson(userId) || this.sending) return;
			this.sending = true;
			this.error = '';
			try {
				await request('/blocks', 'POST', { userId });
				if (userId === this.authorUserId) {
					this.$root.remove();
					return;
				}
				this.dropAuthor(userId);
			} catch (error) {
				this.error = error.message;
			} finally {
				this.sending = false;
			}
		},

		dropAuthor(userId) {
			this.comments = this.comments
				.filter((comment) => comment.author?.userId !== userId)
				.map((comment) => ({
					...comment,
					replies: (comment.replies ?? []).filter((reply) => reply.author?.userId !== userId),
				}));
		},

		startReport(type, id, title) {
			this.$dispatch('open-report', {
				subjectType: type,
				subjectId: id,
				title,
			});
		},

		ago(iso) {
			const date = new Date(iso);
			if (Number.isNaN(date.getTime())) return '';
			const minutes = Math.round((Date.now() - date.getTime()) / 60000);
			if (minutes < 1) return 'przed chwilą';
			if (minutes < 60) return `${minutes} min temu`;
			const hours = Math.round(minutes / 60);
			if (hours < 24) return `${hours} godz. temu`;
			const days = Math.round(hours / 24);
			if (days === 1) return 'wczoraj';
			if (days < 30) return `${days} dni temu`;
			return date.toLocaleDateString('pl-PL');
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
		const failure = new Error(data.message || 'Nie udało się zapisać.');
		failure.code = data.code || null;
		throw failure;
	}
	return data;
}
