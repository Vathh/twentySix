const POLL_MS = 5000;
const RESIZE_DEBOUNCE_MS = 150;

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
    let contentSignature = '';
    let timer = null;
    let resizeTimer = null;
    let layoutRaf = null;

    const layout = () => {
        if (layoutRaf !== null) {
            window.cancelAnimationFrame(layoutRaf);
        }
        layoutRaf = window.requestAnimationFrame(() => {
            layoutRaf = null;
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
        });
    };

    const paint = (items) => {
        const nextSignature = items.map((item) => item.key).join('|');
        const nextContent = items.map(itemContentKey).join('|');
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
            contentSignature = nextContent;
            group.replaceChildren();
            items.forEach((item) => {
                const node = template.content.firstElementChild.cloneNode(true);
                fillNode(node, item);
                group.appendChild(node);
            });
            layout();
            return;
        }
        if (nextContent === contentSignature) {
            return;
        }
        contentSignature = nextContent;
        items.forEach((item) => {
            root.querySelectorAll(`.ticker-item[data-key="${CSS.escape(item.key)}"]`).forEach((node) => {
                fillNode(node, item);
            });
        });
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

    window.addEventListener('resize', () => {
        if (resizeTimer !== null) {
            window.clearTimeout(resizeTimer);
        }
        resizeTimer = window.setTimeout(() => {
            resizeTimer = null;
            layout();
        }, RESIZE_DEBOUNCE_MS);
    });
    window.addEventListener('pagehide', () => {
        if (timer !== null) {
            window.clearInterval(timer);
        }
        if (resizeTimer !== null) {
            window.clearTimeout(resizeTimer);
        }
        if (layoutRaf !== null) {
            window.cancelAnimationFrame(layoutRaf);
        }
    });
}

function itemSignature(group) {
    return [...group.querySelectorAll(':scope > .ticker-item')]
        .map((node) => node.dataset.key ?? '')
        .join('|');
}

function itemContentKey(item) {
    return [
        item.key,
        item.finished ? '1' : '0',
        item.label,
        item.player1,
        item.player2,
        item.score1,
        item.score2,
        item.setLegs1,
        item.setLegs2,
    ].join('\0');
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
        const next = value == null ? '' : String(value);
        if (target.textContent !== next) {
            target.textContent = next;
        }
    }
}

bootOverlayTicker();
