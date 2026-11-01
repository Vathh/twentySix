import { formatAverage as formatAverageValue } from './formatAverage.js';

export function registerPlayerCareer(alpine) {
    alpine.data('playerCareerDashboard', (initial) => ({
        window: initial.window || '90d',
        source: initial.source || 'all',
        isSelf: !!initial.isSelf,
        hero: initial.hero || {},
        series: initial.series || { x01_average: [], double_pct: [] },
        table: initial.table || emptyCareerTable(),
        chartTip: null,
        loading: false,
        filterStuck: false,
        filterBarH: 0,
        fetchUrl: initial.fetchUrl,
        _filterOnScroll: null,
        _filterVis: null,

        init() {
            this._filterOnScroll = () => this.syncFilterSticky();
            window.addEventListener('scroll', this._filterOnScroll, { passive: true });
            window.addEventListener('resize', this._filterOnScroll);
            this._filterVis = new MutationObserver(this._filterOnScroll);
            if (this.$el.parentElement) {
                this._filterVis.observe(this.$el.parentElement, {
                    attributes: true,
                    attributeFilter: ['style', 'class', 'hidden'],
                });
            }
            this.$nextTick(() => this.syncFilterSticky());
        },

        destroy() {
            window.removeEventListener('scroll', this._filterOnScroll);
            window.removeEventListener('resize', this._filterOnScroll);
            this._filterVis?.disconnect();
        },

        headerOffsetPx() {
            const header = document.querySelector('.site-header');
            const tabs = document.querySelector('.profile-tabs');
            let offset = header ? Math.round(header.getBoundingClientRect().height) : 64;
            if (tabs) {
                offset += Math.round(tabs.getBoundingClientRect().height);
            }
            return offset;
        },

        syncFilterSticky() {
            const anchor = this.$refs.filterAnchor;
            const bar = this.$refs.filterBar;
            if (!anchor || !bar) {
                return;
            }
            if (anchor.getClientRects().length === 0) {
                this.filterStuck = false;
                this.clearStuckStyles(bar);
                return;
            }
            if (!this.filterStuck) {
                this.filterBarH = bar.offsetHeight;
            }
            const shouldStick = anchor.getBoundingClientRect().top <= this.headerOffsetPx();
            this.filterStuck = shouldStick;
            if (shouldStick) {
                this.applyStuckStyles(bar);
            } else {
                this.clearStuckStyles(bar);
            }
        },

        applyStuckStyles(bar) {
            const main = bar.closest('main');
            const rect = main
                ? main.getBoundingClientRect()
                : { left: 0, width: window.innerWidth };
            bar.style.top = `${this.headerOffsetPx()}px`;
            bar.style.left = `${rect.left}px`;
            bar.style.width = `${rect.width}px`;
        },

        clearStuckStyles(bar) {
            bar.style.top = '';
            bar.style.left = '';
            bar.style.width = '';
        },

        windows: [
            { key: '30d', label: '1 mies.' },
            { key: '90d', label: '3 mies.' },
            { key: '180d', label: '6 mies.' },
            { key: '365d', label: '12 mies.' },
            { key: 'all', label: 'Całość' },
        ],

        get x01Chart() {
            return buildTrendChart(this.series.x01_average, { kind: 'average' });
        },

        get doubleChart() {
            return buildTrendChart(this.series.double_pct, { kind: 'percent' });
        },

        get x01ChartHtml() {
            return renderTrendChartSvg(this.x01Chart, 'Trend średniej X01');
        },

        get doubleChartHtml() {
            return renderTrendChartSvg(this.doubleChart, 'Trend double procent');
        },

        sources() {
            const list = [
                { key: 'all', label: 'Wszystkie' },
                { key: 'tournament', label: 'Turnieje' },
                { key: 'quick', label: 'Szybkie' },
            ];
            if (this.isSelf) {
                list.push({ key: 'training', label: 'Treningi' });
            }
            return list;
        },

        async applyFilters() {
            if (!this.fetchUrl) {
                return;
            }
            this.loading = true;
            try {
                const url = new URL(this.fetchUrl, window.location.origin);
                url.searchParams.set('window', this.window);
                url.searchParams.set('source', this.source);
                const res = await fetch(url.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.hero = data.hero || {};
                this.series = data.series || { x01_average: [], double_pct: [] };
                this.table = data.table || emptyCareerTable();
                this.chartTip = null;
            } finally {
                this.loading = false;
            }
        },

        setWindow(key) {
            if (this.window === key) {
                return;
            }
            this.window = key;
            this.applyFilters();
        },

        setSource(key) {
            if (this.source === key) {
                return;
            }
            this.source = key;
            this.applyFilters();
        },

        formatDelta(value) {
            if (value == null) {
                return null;
            }
            const n = Number(value);
            if (!Number.isFinite(n) || n === 0) {
                return n === 0 ? '0' : null;
            }
            return (n > 0 ? '+' : '') + (Number.isInteger(n) ? String(n) : n.toFixed(1));
        },

        formatAverage(value) {
            return formatAverageValue(value, '–');
        },

        dash(value) {
            return value == null ? '–' : value;
        },

        qf(value) {
            return value == null ? '–' : `${value} lotek`;
        },

        hideChartTip(chartId) {
            if (this.chartTip?.id === chartId) {
                this.chartTip = null;
            }
        },

        moveChartTip(event, chart, chartId) {
            const svg = event.currentTarget.querySelector('svg');
            const hit = readTrendHit(chart, svg, event.clientX, event.clientY);
            if (!hit) {
                this.hideChartTip(chartId);
                return;
            }
            const host = event.currentTarget.getBoundingClientRect();
            const dotLeft = hit.clientX - host.left;
            const dotTop = hit.clientY - host.top;
            const tipEl = event.currentTarget.querySelector('.career-chart-tip');
            const tipW = tipEl && tipEl.offsetWidth > 0 ? tipEl.offsetWidth : TREND_TIP_W;
            const tipH = tipEl && tipEl.offsetHeight > 0 ? tipEl.offsetHeight : TREND_TIP_H;
            const place = placeChartTip(dotLeft, dotTop, host, tipW, tipH);
            this.chartTip = {
                id: chartId,
                valueLabel: formatTrendHit(hit.value, chart.kind),
                dateLabel: formatChartDate(hit.date),
                dotStyle: { left: `${dotLeft}px`, top: `${dotTop}px` },
                tipStyle: { left: `${place.left}px`, top: `${place.top}px` },
            };
        },
    }));
}

function emptyCareerTable() {
    return {
        games: 0,
        avg_three_darts: null,
        highest_hf: null,
        fastest_qf: null,
        count_max: 0,
        count_170_plus: 0,
        count_hf: 0,
        count_qf: 0,
    };
}

const CHART_W = 640;
const CHART_H = 220;
const PAD = { l: 48, r: 14, t: 14, b: 34 };
const TREND_TIP_W = 96;
const TREND_TIP_H = 44;

function formatTrendHit(value, kind) {
    if (kind === 'percent') {
        return `${Number(value).toFixed(1)}%`;
    }
    return Number(value).toFixed(2);
}

function clientToSvg(svg, clientX, clientY) {
    const ctm = svg.getScreenCTM?.();
    if (!ctm) {
        return null;
    }
    const pt = svg.createSVGPoint();
    pt.x = clientX;
    pt.y = clientY;
    const local = pt.matrixTransform(ctm.inverse());
    return { x: local.x, y: local.y };
}

function svgToClient(svg, x, y) {
    const ctm = svg.getScreenCTM?.();
    if (!ctm) {
        return null;
    }
    const pt = svg.createSVGPoint();
    pt.x = x;
    pt.y = y;
    const screen = pt.matrixTransform(ctm);
    return { x: screen.x, y: screen.y };
}

export function pointAtTrendX(samples, svgX) {
    if (!samples || samples.length < 2) {
        return null;
    }
    const first = samples[0];
    const last = samples[samples.length - 1];
    if (svgX <= first.x) {
        return { x: first.x, y: first.y, value: first.value, date: first.date };
    }
    if (svgX >= last.x) {
        return { x: last.x, y: last.y, value: last.value, date: last.date };
    }
    for (let i = 0; i < samples.length - 1; i += 1) {
        const a = samples[i];
        const b = samples[i + 1];
        if (svgX < a.x || svgX > b.x) {
            continue;
        }
        const span = b.x - a.x;
        const t = span === 0 ? 0 : (svgX - a.x) / span;
        return {
            x: a.x + (b.x - a.x) * t,
            y: a.y + (b.y - a.y) * t,
            value: a.value + (b.value - a.value) * t,
            date: t <= 0.5 ? a.date : b.date,
        };
    }
    return null;
}

function readTrendHit(chart, svg, clientX, clientY) {
    if (!chart?.samples || !svg) {
        return null;
    }
    const local = clientToSvg(svg, clientX, clientY);
    if (!local) {
        return null;
    }
    const point = pointAtTrendX(chart.samples, local.x);
    if (!point) {
        return null;
    }
    const screen = svgToClient(svg, point.x, point.y);
    if (!screen) {
        return null;
    }
    return {
        value: point.value,
        date: point.date,
        clientX: screen.x,
        clientY: screen.y,
    };
}

function placeChartTip(dotLeft, dotTop, host, tipW, tipH) {
    const gap = 10;
    const margin = 8;
    let left = dotLeft - tipW / 2;
    let top = dotTop - tipH - gap;
    const maxLeft = Math.max(4, host.width - tipW - 4);
    const maxTop = Math.max(4, host.height - tipH - 4);
    left = Math.min(Math.max(left, 4), maxLeft);
    if (top < 4) {
        top = dotTop + gap;
    }
    top = Math.min(Math.max(top, 4), maxTop);

    const viewRight = host.left + left + tipW;
    const viewBottom = host.top + top + tipH;
    if (viewRight > window.innerWidth - margin) {
        left -= viewRight - (window.innerWidth - margin);
    }
    if (host.left + left < margin) {
        left += margin - (host.left + left);
    }
    if (viewBottom > window.innerHeight - margin) {
        top -= viewBottom - (window.innerHeight - margin);
    }
    if (host.top + top < margin) {
        top += margin - (host.top + top);
    }
    return { left, top };
}

/**
 * @param {Array<{t?: string, value?: number|null}>} points
 * @param {{kind: 'average'|'percent'}} options
 * @returns {null|{
 *   w: number,
 *   h: number,
 *   plot: {x: number, y: number, w: number, h: number},
 *   d: string,
 *   yTicks: Array<{y: number, label: string}>,
 *   xTicks: Array<{x: number, y: number, label: string, anchor: string}>,
 * }}
 */
export function buildTrendChart(points, options) {
    const rows = (points || []).filter((p) => p != null && p.value != null && Number.isFinite(Number(p.value)));
    if (rows.length < 2) {
        return null;
    }

    const values = rows.map((p) => Number(p.value));
    const scale = niceScale(Math.min(...values), Math.max(...values));
    const plot = {
        x: PAD.l,
        y: PAD.t,
        w: CHART_W - PAD.l - PAD.r,
        h: CHART_H - PAD.t - PAD.b,
    };
    const span = scale.max - scale.min || 1;
    const stepX = rows.length === 1 ? 0 : plot.w / (rows.length - 1);

    const samples = values.map((v, i) => ({
        x: plot.x + i * stepX,
        y: plot.y + plot.h - ((v - scale.min) / span) * plot.h,
        value: v,
        date: rows[i].t ?? '',
    }));

    const yTicks = scale.ticks.map((v) => ({
        y: plot.y + plot.h - ((v - scale.min) / span) * plot.h,
        label: formatYTick(v, options.kind, scale.step),
    }));

    const xTicks = xTickIndexes(rows.length).map((index) => {
        const isFirst = index === 0;
        const isLast = index === rows.length - 1;
        return {
            x: samples[index].x,
            y: plot.y + plot.h + 18,
            label: formatChartDate(rows[index].t),
            anchor: isFirst ? 'start' : (isLast ? 'end' : 'middle'),
        };
    });

    return {
        w: CHART_W,
        h: CHART_H,
        plot,
        kind: options.kind,
        samples,
        d: `M ${samples.map((c) => `${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' L ')}`,
        yTicks,
        xTicks,
        axisY2: plot.x + plot.w,
        axisX2: plot.y + plot.h,
    };
}

export function renderTrendChartSvg(chart, ariaLabel) {
    if (!chart) {
        return '';
    }

    const grid = chart.yTicks.map((tick) => (
        `<line x1="${chart.plot.x}" x2="${chart.axisY2}" y1="${tick.y.toFixed(1)}" y2="${tick.y.toFixed(1)}" stroke="var(--color-border)" stroke-width="1" />`
    )).join('');

    const yLabels = chart.yTicks.map((tick) => (
        `<text x="${chart.plot.x - 8}" y="${tick.y.toFixed(1)}" text-anchor="end" dominant-baseline="middle" fill="var(--color-text-muted)" font-size="11">${escapeXml(tick.label)}</text>`
    )).join('');

    const xLabels = chart.xTicks.map((tick) => (
        `<text x="${tick.x.toFixed(1)}" y="${tick.y}" text-anchor="${tick.anchor}" fill="var(--color-text-muted)" font-size="11">${escapeXml(tick.label)}</text>`
    )).join('');

    return `<svg viewBox="0 0 ${chart.w} ${chart.h}" class="block w-full h-auto text-accent" preserveAspectRatio="xMidYMid meet" role="img" aria-label="${escapeXml(ariaLabel)}">${grid}<line x1="${chart.plot.x}" y1="${chart.plot.y}" x2="${chart.plot.x}" y2="${chart.axisX2}" stroke="var(--color-border)" stroke-width="1.25" /><line x1="${chart.plot.x}" y1="${chart.axisX2}" x2="${chart.axisY2}" y2="${chart.axisX2}" stroke="var(--color-border)" stroke-width="1.25" /><path fill="none" stroke="currentColor" stroke-width="2.25" stroke-linejoin="round" stroke-linecap="round" d="${chart.d}" />${yLabels}${xLabels}</svg>`;
}

function escapeXml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function xTickIndexes(count) {
    if (count <= 2) {
        return [0, count - 1];
    }
    return [0, Math.floor((count - 1) / 2), count - 1];
}

function niceScale(min, max) {
    if (max === min) {
        const pad = Math.abs(max) * 0.08 || 1;
        min -= pad;
        max += pad;
    } else {
        const pad = (max - min) * 0.1;
        min -= pad;
        max += pad;
    }

    const tickCount = 3;
    const step = niceNum((max - min) / (tickCount - 1));
    const niceMin = Math.floor(min / step) * step;
    const niceMax = Math.ceil(max / step) * step;
    const ticks = [];
    const n = Math.round((niceMax - niceMin) / step);
    for (let i = 0; i <= n; i++) {
        ticks.push(roundTo(niceMin + i * step, step));
    }

    return { min: niceMin, max: niceMax, step, ticks };
}

function niceNum(range) {
    if (!(range > 0)) {
        return 1;
    }
    const exp = Math.floor(Math.log10(range));
    const f = range / (10 ** exp);
    let nf;
    if (f < 1.5) {
        nf = 1;
    } else if (f < 3) {
        nf = 2;
    } else if (f < 7) {
        nf = 5;
    } else {
        nf = 10;
    }

    return nf * (10 ** exp);
}

function roundTo(value, step) {
    const decimals = Math.max(0, Math.ceil(-Math.log10(step) - 1e-12));
    return Number(value.toFixed(decimals));
}

function formatYTick(value, kind, step) {
    if (kind === 'percent') {
        const decimals = step < 1 ? 1 : 0;
        return `${value.toFixed(decimals)}%`;
    }
    return value.toFixed(2);
}

function formatChartDate(iso) {
    if (!iso || typeof iso !== 'string') {
        return '';
    }
    const [y, m, d] = iso.split('-');
    if (!y || !m || !d) {
        return iso;
    }
    return `${d}.${m}.${y.slice(2)}`;
}
