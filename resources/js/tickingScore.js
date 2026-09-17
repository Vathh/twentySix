const MIN_MS = 160;
const MAX_MS = 380;
const MS_PER_POINT = 2.2;

function durationForDelta(absDelta) {
    return Math.min(MAX_MS, Math.max(MIN_MS, absDelta * MS_PER_POINT));
}

function easeOutCubic(t) {
    return 1 - (1 - t) ** 3;
}

function numericScore(value) {
    const n = Number(value);

    return Number.isFinite(n) ? n : null;
}

/**
 * Jak TickingScore na mobile: spadek remaining odlicza w dół i hamuje na celu.
 * Wzrost (undo / nowy leg) oraz wartości nienumeryczne — od razu na cel.
 *
 * @param {unknown} from
 * @param {unknown} to
 * @param {(value: unknown) => void} onUpdate
 * @returns {() => void} cancel
 */
export function animateScoreDown(from, to, onUpdate) {
    const fromN = numericScore(from);
    const toN = numericScore(to);
    if (fromN == null || toN == null || toN >= fromN) {
        onUpdate(to);
        return () => {};
    }

    const startedAt = performance.now();
    const duration = durationForDelta(fromN - toN);
    let raf = null;

    const frame = (now) => {
        const t = Math.min(1, (now - startedAt) / duration);
        onUpdate(Math.round(fromN + (toN - fromN) * easeOutCubic(t)));
        if (t < 1) {
            raf = requestAnimationFrame(frame);
        } else {
            raf = null;
        }
    };

    raf = requestAnimationFrame(frame);

    return () => {
        if (raf != null) {
            cancelAnimationFrame(raf);
            raf = null;
        }
    };
}
