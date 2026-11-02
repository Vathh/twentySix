const POLL_MS = 5000;

function bootOverlayTicker() {
    const root = document.getElementById('overlay-ticker');
    if (!root) {
        return;
    }

    const track = root.querySelector('.ticker-track');
    const group = root.querySelector('.ticker-group');
    const live = root.querySelector('.ticker-live');
    const mark = root.querySelector('.ticker-mark');
    const empty = root.querySelector('.ticker-empty');
    const template = document.getElementById('overlay-ticker-item');
    const stateUrl = root.dataset.stateUrl;
    if (!track || !group || !template || !stateUrl) {
        return;
    }

    let signature = itemSignature(group);
    let timer = null;

    const layout = () => {
        track.querySelector('.ticker-group.is-clone')?.remove();
        track.classList.remove('is-marquee');
        track.style.removeProperty('--ticker-duration');

        if (track.hidden || group.childElementCount === 0) {
            return;
        }

        const viewport = track.parentElement;
        if (!viewport || group.scrollWidth <= viewport.clientWidth + 1) {
            return;
        }

        const copy = group.cloneNode(true);
        copy.classList.add('is-clone');
        copy.setAttribute('aria-hidden', 'true');
        track.appendChild(copy);
        const seconds = Math.max(16, Math.round(group.scrollWidth / 80));
        track.style.setProperty('--ticker-duration', `${seconds}s`);
        track.classList.add('is-marquee');
    };

    const paint = (items) => {
        const nextSignature = items.map((item) => item.key).join('|');
        const hasItems = items.length > 0;
        track.hidden = !hasItems;
        if (live) {
            live.hidden = !hasItems;
        }
        if (mark) {
            mark.hidden = hasItems;
        }
        if (empty) {
            empty.hidden = hasItems;
        }
        if (nextSignature !== signature) {
            signature = nextSignature;
            group.replaceChildren();
            items.forEach((item) => {
                const node = template.content.firstElementChild.cloneNode(true);
                fillNode(node, item);
                group.appendChild(node);
            });
            layout();
        } else {
            items.forEach((item) => {
                root.querySelectorAll(`.ticker-item[data-key="${CSS.escape(item.key)}"]`).forEach((node) => {
                    fillNode(node, item);
                });
            });
        }
    };

    const poll = async () => {
        try {
            const response = await fetch(stateUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) {
                return;
            }
            const body = await response.json();
            if (!Array.isArray(body.items)) {
                return;
            }
            paint(body.items);
        } catch {
            // Zostaw ostatni kadr, gdy odświeżenie nie dojdzie.
        }
    };

    const start = () => {
        layout();
        poll();
        timer = window.setInterval(poll, POLL_MS);
    };

    if (document.fonts?.ready) {
        document.fonts.ready.then(start);
    } else {
        start();
    }

    window.addEventListener('resize', layout);
    window.addEventListener('pagehide', () => {
        if (timer !== null) {
            window.clearInterval(timer);
        }
    });
}

function itemSignature(group) {
    return [...group.querySelectorAll(':scope > .ticker-item')]
        .map((node) => node.dataset.key ?? '')
        .join('|');
}

function fillNode(node, item) {
    node.dataset.key = item.key;
    setField(node, 'label', item.label);
    const result = node.querySelector('[data-field="result"]');
    if (result) {
        result.hidden = !item.finished;
    }
    setField(node, 'player1', item.player1);
    setField(node, 'player2', item.player2);
    setField(node, 'score1', item.score1);
    setField(node, 'score2', item.score2);
    const legs = node.querySelector('[data-field="legs"]');
    if (!legs) {
        return;
    }
    if (item.setLegs1 == null || item.setLegs2 == null) {
        legs.hidden = true;
        legs.textContent = '';
        return;
    }
    legs.hidden = false;
    legs.textContent = `(${item.setLegs1}–${item.setLegs2})`;
}

function setField(node, field, value) {
    const target = node.querySelector(`[data-field="${field}"]`);
    if (target) {
        target.textContent = value == null ? '' : String(value);
    }
}

bootOverlayTicker();
