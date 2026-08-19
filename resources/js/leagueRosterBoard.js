/** Tablica składu ligi: przeciąganie zawodników między pulą a szczebelami. */
export function registerLeagueRosterBoard(Alpine) {
	Alpine.data('leagueRosterBoard', (config) => ({
		locked: !!config.locked,
		busy: false,
		divisions: config.divisions ?? [],
		related: config.related ?? [],
		guests: config.guests ?? [],
		relatedManageUrl: config.relatedManageUrl ?? '',
		guestsManageUrl: config.guestsManageUrl ?? '',
		draggingId: null,
		dropTarget: null,

		notify(text, type = 'error') {
			window.dispatchEvent(new CustomEvent('app-toast', {
				detail: { type, text },
			}));
		},

		snapshot() {
			return {
				divisions: JSON.parse(JSON.stringify(this.divisions)),
				related: JSON.parse(JSON.stringify(this.related)),
				guests: JSON.parse(JSON.stringify(this.guests)),
			};
		},

		restore(state) {
			this.divisions = state.divisions;
			this.related = state.related;
			this.guests = state.guests;
		},

		findPlayer(playerId) {
			for (const division of this.divisions) {
				const player = division.players.find((item) => item.id === playerId);
				if (player) {
					return player;
				}
			}
			return this.related.find((item) => item.id === playerId)
				?? this.guests.find((item) => item.id === playerId)
				?? null;
		},

		takePlayer(playerId) {
			for (const division of this.divisions) {
				const index = division.players.findIndex((item) => item.id === playerId);
				if (index >= 0) {
					return division.players.splice(index, 1)[0];
				}
			}
			let index = this.related.findIndex((item) => item.id === playerId);
			if (index >= 0) {
				return this.related.splice(index, 1)[0];
			}
			index = this.guests.findIndex((item) => item.id === playerId);
			if (index >= 0) {
				return this.guests.splice(index, 1)[0];
			}
			return null;
		},

		placePlayer(player, target) {
			if (target.type === 'division') {
				const division = this.divisions.find((item) => item.id === target.id);
				if (division) {
					division.players.push(player);
				}
				return;
			}
			if (player.kind === 'guest') {
				this.guests.push(player);
				this.guests.sort((a, b) => a.name.localeCompare(b.name, 'pl'));
				return;
			}
			this.related.push(player);
			this.related.sort((a, b) => a.name.localeCompare(b.name, 'pl'));
		},

		currentLocation(playerId) {
			for (const division of this.divisions) {
				if (division.players.some((item) => item.id === playerId)) {
					return { type: 'division', id: division.id };
				}
			}
			if (this.related.some((item) => item.id === playerId)) {
				return { type: 'related' };
			}
			return { type: 'guest' };
		},

		sameTarget(a, b) {
			if (a.type !== b.type) {
				return false;
			}
			if (a.type === 'division') {
				return a.id === b.id;
			}
			return true;
		},

		minCapacity(division) {
			const occupied = division.players.length;
			const promotionFloor = (division.promoteDirect ?? 0) + (division.promotePlayoff ?? 0) + 1;

			return Math.max(2, occupied, promotionFloor);
		},

		canDecrease(division) {
			return division.capacity > this.minCapacity(division);
		},

		canIncrease(division) {
			return division.capacity < 16;
		},

		async changeCapacity(division, delta) {
			if (this.locked || this.busy || !delta) {
				return;
			}
			if (delta < 0 && !this.canDecrease(division)) {
				return;
			}
			if (delta > 0 && !this.canIncrease(division)) {
				return;
			}

			const previous = division.capacity;
			const next = division.capacity + delta;
			division.capacity = next;
			this.busy = true;
			try {
				await this.request(config.capacityUrl, 'PATCH', {
					division_id: division.id,
					capacity: next,
				});
			} catch (error) {
				division.capacity = previous;
				this.notify(error instanceof Error ? error.message : 'Nie udało się zmienić pojemności.');
			} finally {
				this.busy = false;
			}
		},

		onDragStart(event, player) {
			if (this.locked || this.busy) {
				event.preventDefault();
				return;
			}
			this.draggingId = player.id;
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', String(player.id));
		},

		onDragEnd() {
			this.draggingId = null;
			this.dropTarget = null;
		},

		onDragOver(event, target) {
			if (this.locked || this.draggingId === null) {
				return;
			}
			event.preventDefault();
			this.dropTarget = target.type === 'division' ? `division-${target.id}` : target.type;
		},

		async onDrop(event, target) {
			event.preventDefault();
			if (this.locked || this.busy) {
				return;
			}
			const playerId = Number(event.dataTransfer.getData('text/plain') || this.draggingId);
			this.draggingId = null;
			this.dropTarget = null;
			await this.movePlayer(playerId, target);
		},

		async movePlayer(playerId, target) {
			const player = this.findPlayer(playerId);
			if (!player) {
				return;
			}
			const from = this.currentLocation(playerId);
			if (this.sameTarget(from, target)) {
				return;
			}
			if (from.type !== 'division' && target.type !== 'division') {
				return;
			}
			if (target.type === 'division') {
				const division = this.divisions.find((item) => item.id === target.id);
				if (!division) {
					return;
				}
				if (division.players.length >= division.capacity) {
					this.notify(`Szczebel „${division.name}” jest pełny.`);
					return;
				}
			}

			const previous = this.snapshot();
			this.takePlayer(playerId);
			this.placePlayer(player, target);
			this.busy = true;

			try {
				if (target.type === 'division') {
					await this.request(config.assignUrl, 'POST', {
						player_id: player.id,
						division_id: target.id,
					});
				} else {
					await this.request(config.removeUrl, 'DELETE', {
						player_id: player.id,
					});
				}
			} catch (error) {
				this.restore(previous);
				this.notify(error instanceof Error ? error.message : 'Nie udało się przesunąć zawodnika.');
			} finally {
				this.busy = false;
			}
		},

		async request(url, method, body) {
			const response = await fetch(url, {
				method,
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': config.csrfToken,
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify(body),
			});
			const data = await response.json().catch(() => ({}));
			if (!response.ok) {
				const validationMessage = Object.values(data.errors ?? {}).flat()[0];
				throw new Error(validationMessage || data.message || 'Operacja nie powiodła się.');
			}
		},
	}));
}
