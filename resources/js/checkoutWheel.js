const SVG_NS = 'http://www.w3.org/2000/svg';

const LEVELS = [
    ['apex', 15],
    ['bright', 10],
    ['gold', 5],
    ['bronze', 3],
    ['iron', 1],
];

const DEMO_HITS = {
    170: 15, 167: 5, 164: 10, 161: 3, 151: 1, 152: 3, 154: 5, 157: 10, 160: 15,
    138: 1, 140: 3, 144: 5, 148: 10, 150: 15, 121: 1, 126: 3, 130: 5, 134: 10,
    100: 1, 107: 3, 112: 5, 118: 15, 120: 10,
};

const offGlow = { enabled: false, color: '#2e2e38', blur: 0, opacity: 0 };
const offInnerGlow = { enabled: false, color: '#2e2e38', blur: 0, opacity: 0, erode: 0 };
const offInnerStroke = { enabled: false, color: '#f4f4f5', width: 1 };

const PRESETS = {
    locked: {
        fill: '#16161a',
        stroke: '#0c0c0f',
        strokeWidth: 1.25,
        label: '#52525b',
        innerStroke: { ...offInnerStroke },
        outerGlow: { ...offGlow },
        innerGlow: { ...offInnerGlow },
        material: { enabled: false, sheen: 0 },
    },
    iron: {
        fill: '#27272a',
        stroke: '#0c0c0f',
        strokeWidth: 1.25,
        label: '#e4e4e7',
        innerStroke: { enabled: true, color: '#a1a1aa', width: 0.85 },
        outerGlow: { ...offGlow },
        innerGlow: { ...offInnerGlow },
        material: {
            enabled: true,
            surfaceScale: 2.0,
            diffuse: 0.62,
            specular: 0.1,
            specularExponent: 16,
            grain: 0.16,
            recess: 0.3,
            sheen: 0.48,
            veins: 0.24,
        },
    },
    bronze: {
        fill: '#7c2d12',
        stroke: '#0c0c0f',
        strokeWidth: 1.25,
        label: '#fed7aa',
        innerStroke: { ...offInnerStroke },
        outerGlow: { enabled: true, color: '#c2410c', blur: 6, opacity: 0.28 },
        innerGlow: { ...offInnerGlow },
        material: {
            enabled: true,
            surfaceScale: 2.3,
            diffuse: 0.78,
            specular: 0.16,
            specularExponent: 16,
            grain: 0.12,
            recess: 0.24,
            sheen: 0.58,
            veins: 0.16,
            crystal: true,
        },
    },
    gold: {
        fill: '#ffc400',
        stroke: '#0c0c0f',
        strokeWidth: 1.25,
        label: '#3a2400',
        innerStroke: { ...offInnerStroke },
        outerGlow: { enabled: true, color: '#ffd54a', blur: 14, opacity: 0.55 },
        innerGlow: { enabled: true, color: '#ffe566', blur: 2.2, opacity: 0.35, erode: 1.1 },
        material: {
            enabled: true,
            surfaceScale: 2.0,
            diffuse: 0.55,
            specular: 0.42,
            specularExponent: 24,
            grain: 0,
            recess: 0,
            sheen: 0.55,
            sheenBlend: 'screen',
            sheenFill: 'gold',
            skipDiffuse: true,
            veins: 0,
            lightColor: '#ffd24a',
            specColor: '#ffe566',
            molten: true,
        },
    },
    bright: {
        fill: '#0369a1',
        stroke: '#0c0c0f',
        strokeWidth: 1.25,
        label: '#e0f2fe',
        innerStroke: { enabled: true, color: '#7dd3fc', width: 1 },
        outerGlow: { enabled: true, color: '#38bdf8', blur: 12, opacity: 0.5 },
        innerGlow: { enabled: true, color: '#7dd3fc', blur: 2.5, opacity: 0.4, erode: 1.5 },
        material: {
            enabled: true,
            surfaceScale: 2.2,
            diffuse: 0.82,
            specular: 0.38,
            specularExponent: 26,
            grain: 0.05,
            recess: 0.12,
            sheen: 0.72,
            veins: 0.08,
            lightColor: '#e0f2fe',
            specColor: '#f0f9ff',
            veinCool: true,
            cracks: true,
        },
    },
    apex: {
        fill: '#c4e4f2',
        stroke: '#0c0c0f',
        strokeWidth: 1.25,
        label: '#0c4a6e',
        innerStroke: { ...offInnerStroke },
        outerGlow: { enabled: true, color: '#67e8f9', blur: 26, opacity: 0.62 },
        innerGlow: { enabled: true, color: '#ecfeff', blur: 5, opacity: 0.5, erode: 2.8 },
        material: {
            enabled: true,
            surfaceScale: 2.1,
            diffuse: 0.9,
            specular: 0.55,
            specularExponent: 32,
            grain: 0.04,
            recess: 0.08,
            sheen: 0.82,
            veins: 0.06,
            lightColor: '#f0f9ff',
            specColor: '#ffffff',
            veinCool: true,
            cracks: true,
            sparkle: true,
        },
    },
};

let wheelSeq = 0;

function svgEl(name, attrs = {}) {
    const el = document.createElementNS(SVG_NS, name);
    Object.entries(attrs).forEach(([key, value]) => el.setAttribute(key, String(value)));
    return el;
}

function levelFromHits(hits) {
    const n = Number(hits) || 0;
    for (const [name, min] of LEVELS) {
        if (n >= min) {
            return name;
        }
    }
    return 'locked';
}

function hash01(n) {
    const x = Math.sin(n * 127.1) * 43758.5453;
    return x - Math.floor(x);
}

function polylineD(pts) {
    return pts.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
}

function jaggedLine(x1, y1, x2, y2, steps, amp, seed) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    const len = Math.hypot(dx, dy) || 1;
    const nx = -dy / len;
    const ny = dx / len;
    const pts = [];
    for (let i = 0; i <= steps; i += 1) {
        const t = i / steps;
        const j = (i === 0 || i === steps) ? 0 : (hash01(seed + i * 17) - 0.5) * 2 * amp;
        pts.push([x1 + dx * t + nx * j, y1 + dy * t + ny * j]);
    }
    return pts;
}

function wanderPoints(x, y, angle, steps, stepLen, wander, seed) {
    const pts = [[x, y]];
    let a = angle;
    let px = x;
    let py = y;
    for (let i = 0; i < steps; i += 1) {
        a += (hash01(seed + i * 3) - 0.5) * wander;
        px += Math.cos(a) * stepLen;
        py += Math.sin(a) * stepLen;
        pts.push([px, py]);
    }
    return pts;
}

function smoothPathD(pts) {
    if (pts.length < 2) {
        return '';
    }
    let d = `M${pts[0][0].toFixed(1)},${pts[0][1].toFixed(1)}`;
    for (let i = 1; i < pts.length - 1; i += 1) {
        const xc = (pts[i][0] + pts[i + 1][0]) / 2;
        const yc = (pts[i][1] + pts[i + 1][1]) / 2;
        d += ` Q${pts[i][0].toFixed(1)},${pts[i][1].toFixed(1)} ${xc.toFixed(1)},${yc.toFixed(1)}`;
    }
    const last = pts[pts.length - 1];
    d += ` T${last[0].toFixed(1)},${last[1].toFixed(1)}`;
    return d;
}

function parseHits(host) {
    if (host.dataset.checkoutWheelDemo === '1') {
        return { ...DEMO_HITS };
    }
    try {
        const raw = host.dataset.checkoutHits || '{}';
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
}

class CheckoutWheel {
    constructor(host) {
        this.host = host;
        this.uid = `cw${++wheelSeq}`;
        this.svg = host.querySelector('svg');
    }

    qid(name) {
        return `${this.uid}-${name}`;
    }

    mount() {
        if (!this.svg) {
            return;
        }

        this.svg.removeAttribute('width');
        this.svg.removeAttribute('height');
        this.svg.setAttribute('overflow', 'visible');

        this.ensureSharedDefs();
        this.mountAtmosphere();
        this.mountLife();
        this.buildLevelFilters();

        const wheel = this.svg.querySelector('#checkout-wheel');
        const center = this.svg.querySelector('#checkout-170');
        if (wheel && center) {
            wheel.appendChild(center);
        }

        const hits = parseHits(this.host);

        this.svg.querySelectorAll('[data-checkout]').forEach((group) => {
            group.setAttribute('overflow', 'visible');
            group.querySelectorAll('path, circle').forEach((shape) => {
                if (
                    shape.classList.contains('checkout-halo')
                    || shape.classList.contains('checkout-inner-stroke')
                    || shape.classList.contains('checkout-sheen')
                    || shape.classList.contains('checkout-sparkle')
                    || shape.closest('.checkout-cracks')
                    || shape.closest('.checkout-molten')
                ) {
                    return;
                }
                shape.removeAttribute('style');
                shape.removeAttribute('fill');
                shape.removeAttribute('stroke');
                shape.removeAttribute('stroke-width');
                shape.classList.add('checkout-shape');
            });
            group.querySelectorAll('text').forEach((label) => {
                label.removeAttribute('fill');
            });

            const checkout = group.getAttribute('data-checkout');
            const level = levelFromHits(hits[checkout] ?? hits[String(checkout)] ?? 0);
            group.setAttribute('data-level', level);
            group.setAttribute('data-hits', String(hits[checkout] ?? hits[String(checkout)] ?? 0));
            this.applyBrush(group, PRESETS[level], checkout);
        });
    }

    ensureSharedDefs() {
        let defs = this.svg.querySelector('defs');
        if (!defs) {
            defs = svgEl('defs');
            this.svg.prepend(defs);
        }
        this.defs = defs;

        if (!document.getElementById(this.qid('sheen'))) {
            const sheen = svgEl('radialGradient', {
                id: this.qid('sheen'),
                gradientUnits: 'userSpaceOnUse',
                cx: '450',
                cy: '450',
                r: '430',
            });
            sheen.appendChild(svgEl('stop', { offset: '0%', 'stop-color': '#fffaf2', 'stop-opacity': '0.02' }));
            sheen.appendChild(svgEl('stop', { offset: '38%', 'stop-color': '#f8edd4', 'stop-opacity': '0.05' }));
            sheen.appendChild(svgEl('stop', { offset: '70%', 'stop-color': '#f8edd4', 'stop-opacity': '0.1' }));
            sheen.appendChild(svgEl('stop', { offset: '100%', 'stop-color': '#05040a', 'stop-opacity': '0.07' }));
            defs.appendChild(sheen);
        }

        if (!document.getElementById(this.qid('sheen-gold'))) {
            const gold = svgEl('radialGradient', {
                id: this.qid('sheen-gold'),
                gradientUnits: 'userSpaceOnUse',
                cx: '450',
                cy: '450',
                r: '430',
            });
            gold.appendChild(svgEl('stop', { offset: '0%', 'stop-color': '#fff3b0', 'stop-opacity': '0.05' }));
            gold.appendChild(svgEl('stop', { offset: '42%', 'stop-color': '#ffd24a', 'stop-opacity': '0.18' }));
            gold.appendChild(svgEl('stop', { offset: '72%', 'stop-color': '#ffcc00', 'stop-opacity': '0.28' }));
            gold.appendChild(svgEl('stop', { offset: '100%', 'stop-color': '#ffb300', 'stop-opacity': '0.08' }));
            defs.appendChild(gold);
        }

        if (!document.getElementById(this.qid('caustic'))) {
            const caustic = svgEl('linearGradient', {
                id: this.qid('caustic'),
                x1: '0',
                y1: '0',
                x2: '0',
                y2: '1',
            });
            caustic.appendChild(svgEl('stop', { offset: '0%', 'stop-color': '#ffffff', 'stop-opacity': '0' }));
            caustic.appendChild(svgEl('stop', { offset: '44%', 'stop-color': '#ffffff', 'stop-opacity': '0' }));
            caustic.appendChild(svgEl('stop', { offset: '50%', 'stop-color': '#ffffff', 'stop-opacity': '0.55' }));
            caustic.appendChild(svgEl('stop', { offset: '56%', 'stop-color': '#ffffff', 'stop-opacity': '0' }));
            caustic.appendChild(svgEl('stop', { offset: '100%', 'stop-color': '#ffffff', 'stop-opacity': '0' }));
            defs.appendChild(caustic);
        }

        if (!document.getElementById(this.qid('pit-fog'))) {
            const fog = svgEl('filter', {
                id: this.qid('pit-fog'),
                x: '-15%',
                y: '-15%',
                width: '130%',
                height: '130%',
                'color-interpolation-filters': 'sRGB',
            });
            fog.appendChild(svgEl('feTurbulence', {
                type: 'fractalNoise',
                baseFrequency: '0.013 0.018',
                numOctaves: '4',
                seed: '11',
                stitchTiles: 'stitch',
                result: 't',
            }));
            fog.appendChild(svgEl('feColorMatrix', {
                in: 't',
                type: 'matrix',
                values: '0 0 0 0 0.72  0 0 0 0 0.52  0 0 0 0 0.28  0 0 0 0.55 0',
                result: 'tint',
            }));
            fog.appendChild(svgEl('feGaussianBlur', { in: 'tint', stdDeviation: '6.5' }));
            defs.appendChild(fog);

            const fade = svgEl('radialGradient', {
                id: this.qid('pit-fade'),
                gradientUnits: 'userSpaceOnUse',
                cx: '450',
                cy: '450',
                r: '375',
            });
            fade.appendChild(svgEl('stop', { offset: '0%', 'stop-color': '#1c140e', 'stop-opacity': '0.2' }));
            fade.appendChild(svgEl('stop', { offset: '58%', 'stop-color': '#8a6230', 'stop-opacity': '0.22' }));
            fade.appendChild(svgEl('stop', { offset: '100%', 'stop-color': '#0c0c0f', 'stop-opacity': '0' }));
            defs.appendChild(fade);
        }
    }

    mountAtmosphere() {
        if (this.svg.querySelector(`#${this.qid('atmosphere')}`)) {
            return;
        }
        const layer = svgEl('g', { id: this.qid('atmosphere') });
        layer.setAttribute('pointer-events', 'none');
        layer.setAttribute('opacity', '0.7');
        layer.appendChild(svgEl('circle', {
            cx: '450',
            cy: '450',
            r: '372',
            fill: `url(#${this.qid('pit-fade')})`,
        }));
        layer.appendChild(svgEl('circle', {
            cx: '450',
            cy: '450',
            r: '372',
            fill: '#d4b07a',
            filter: `url(#${this.qid('pit-fog')})`,
        }));
        const spider = svgEl('g');
        [60, 140, 210, 275, 325, 360].forEach((r) => {
            spider.appendChild(svgEl('circle', {
                cx: '450',
                cy: '450',
                r: String(r),
                fill: 'none',
                stroke: '#4a321c',
                'stroke-width': r === 60 || r === 360 ? '1.6' : '1.15',
                opacity: '0.55',
            }));
        });
        for (let i = 0; i < 24; i += 1) {
            const a = (i / 24) * Math.PI * 2 - Math.PI / 2;
            spider.appendChild(svgEl('line', {
                x1: String(450 + Math.cos(a) * 62),
                y1: String(450 + Math.sin(a) * 62),
                x2: String(450 + Math.cos(a) * 358),
                y2: String(450 + Math.sin(a) * 358),
                stroke: '#4a321c',
                'stroke-width': '0.85',
                opacity: '0.32',
            }));
        }
        layer.appendChild(spider);
        const pulse = svgEl('circle', {
            class: 'cw-fog-pulse',
            cx: '450',
            cy: '450',
            r: '372',
            fill: '#d4b07a',
        });
        const wheel = this.svg.querySelector('#checkout-wheel');
        if (wheel) {
            this.svg.insertBefore(layer, wheel);
            this.svg.insertBefore(pulse, wheel);
        } else {
            this.svg.appendChild(layer);
            this.svg.appendChild(pulse);
        }
    }

    mountLife() {
        if (this.svg.querySelector(`#${this.qid('life')}`)) {
            return;
        }
        const life = svgEl('g', { id: this.qid('life'), class: 'cw-life' });
        life.setAttribute('pointer-events', 'none');
        life.appendChild(svgEl('circle', {
            class: 'cw-life-disc',
            cx: '450',
            cy: '450',
            r: '368',
            fill: `url(#${this.qid('caustic')})`,
        }));
        this.svg.appendChild(life);
    }

    buildLevelFilters() {
        Object.entries(PRESETS).forEach(([level, brush]) => {
            this.buildMaterialFilter(level, brush);
            this.buildHaloFilters(level, brush);
        });
    }

    buildMaterialFilter(level, brush) {
        const id = this.qid(`mat-${level}`);
        document.getElementById(id)?.remove();
        const mat = brush.material;
        const glow = brush.innerGlow;
        const needMat = Boolean(mat?.enabled);
        const needGlow = Boolean(glow?.enabled && glow.blur > 0 && glow.opacity > 0);
        if (!needMat && !needGlow) {
            return;
        }

        const filter = svgEl('filter', {
            id,
            x: '-40%',
            y: '-40%',
            width: '180%',
            height: '180%',
            filterUnits: 'objectBoundingBox',
            primitiveUnits: 'userSpaceOnUse',
            'color-interpolation-filters': 'sRGB',
        });

        let current = 'SourceGraphic';
        const seed = level.length + 7;

        if (needMat) {
            const crystal = Boolean(mat.crystal);
            filter.appendChild(svgEl('feTurbulence', {
                type: crystal ? 'turbulence' : 'fractalNoise',
                baseFrequency: crystal ? '0.014 0.006' : '0.012 0.007',
                numOctaves: '2',
                seed: String(seed),
                stitchTiles: 'stitch',
                result: 'bumpRaw',
            }));

            if (crystal) {
                const posterize = svgEl('feComponentTransfer', { in: 'bumpRaw', result: 'bump' });
                posterize.appendChild(svgEl('feFuncA', {
                    type: 'discrete',
                    tableValues: '0 0.18 0.38 0.55 0.72 0.88 1',
                }));
                filter.appendChild(posterize);
            } else {
                filter.appendChild(svgEl('feGaussianBlur', {
                    in: 'bumpRaw',
                    stdDeviation: '0.55',
                    result: 'bump',
                }));
            }

            const bumpDiff = svgEl('feDiffuseLighting', {
                in: 'bump',
                surfaceScale: String(mat.surfaceScale * (crystal ? 2.6 : 2.4)),
                diffuseConstant: String(Math.min(1, mat.diffuse * 0.9)),
                'lighting-color': mat.lightColor || '#fff6ea',
                result: 'bumpDiff',
            });
            bumpDiff.appendChild(svgEl('feDistantLight', { azimuth: '270', elevation: '64' }));
            filter.appendChild(bumpDiff);
            filter.appendChild(svgEl('feComposite', {
                in: 'bumpDiff',
                in2: 'SourceAlpha',
                operator: 'in',
                result: 'bumpDiffClip',
            }));
            if (!mat.skipDiffuse) {
                filter.appendChild(svgEl('feBlend', {
                    in: current,
                    in2: 'bumpDiffClip',
                    mode: mat.litBlend || 'overlay',
                    result: 'bumpLit',
                }));
                current = 'bumpLit';
            }

            if (mat.specular > 0) {
                const bumpSpec = svgEl('feSpecularLighting', {
                    in: 'bump',
                    surfaceScale: String(mat.surfaceScale * (crystal ? 2.2 : 1.6)),
                    specularConstant: String(mat.specular * (crystal ? 1.15 : 0.9)),
                    specularExponent: String(crystal ? Math.max(mat.specularExponent, 28) : mat.specularExponent),
                    'lighting-color': mat.specColor || '#fffaf3',
                    result: 'bumpSpec',
                });
                bumpSpec.appendChild(svgEl('feDistantLight', { azimuth: '270', elevation: '64' }));
                filter.appendChild(bumpSpec);
                filter.appendChild(svgEl('feComposite', {
                    in: 'bumpSpec',
                    in2: 'SourceAlpha',
                    operator: 'in',
                    result: 'bumpSpecClip',
                }));
                filter.appendChild(svgEl('feBlend', {
                    in: current,
                    in2: 'bumpSpecClip',
                    mode: 'screen',
                    result: 'bumpGlazed',
                }));
                current = 'bumpGlazed';
            }

            if (!mat.skipDiffuse) {
                filter.appendChild(svgEl('feGaussianBlur', {
                    in: 'SourceAlpha',
                    stdDeviation: '1.2',
                    result: 'height',
                }));
                const diff = svgEl('feDiffuseLighting', {
                    in: 'height',
                    surfaceScale: String(Math.max(1.2, mat.surfaceScale * 0.55)),
                    diffuseConstant: String(mat.diffuse * 0.42),
                    'lighting-color': mat.lightColor || '#fff6ea',
                    result: 'diff',
                });
                diff.appendChild(svgEl('feDistantLight', { azimuth: '270', elevation: '64' }));
                filter.appendChild(diff);
                filter.appendChild(svgEl('feComposite', {
                    in: 'diff',
                    in2: 'SourceAlpha',
                    operator: 'in',
                    result: 'diffClip',
                }));
                filter.appendChild(svgEl('feBlend', {
                    in: current,
                    in2: 'diffClip',
                    mode: mat.litBlend || 'overlay',
                    result: 'lit',
                }));
                current = 'lit';
            }

            if (mat.recess > 0) {
                filter.appendChild(svgEl('feMorphology', {
                    in: 'SourceAlpha',
                    operator: 'erode',
                    radius: '1.4',
                    result: 'eroded',
                }));
                filter.appendChild(svgEl('feComposite', {
                    in: 'SourceAlpha',
                    in2: 'eroded',
                    operator: 'out',
                    result: 'rim',
                }));
                filter.appendChild(svgEl('feGaussianBlur', {
                    in: 'rim',
                    stdDeviation: '1.7',
                    result: 'rimBlur',
                }));
                filter.appendChild(svgEl('feColorMatrix', {
                    in: 'rimBlur',
                    type: 'matrix',
                    values: `0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 ${mat.recess} 0`,
                    result: 'rimDark',
                }));
                filter.appendChild(svgEl('feBlend', {
                    in: current,
                    in2: 'rimDark',
                    mode: 'multiply',
                    result: 'recessed',
                }));
                current = 'recessed';
            }

            if (mat.molten) {
                filter.appendChild(svgEl('feTurbulence', {
                    type: 'turbulence',
                    baseFrequency: '0.008 0.02',
                    numOctaves: '3',
                    seed: String(seed + 4),
                    stitchTiles: 'stitch',
                    result: 'moltenNoise',
                }));
                filter.appendChild(svgEl('feColorMatrix', {
                    in: 'moltenNoise',
                    type: 'matrix',
                    values: '0 0 0 0 1  0 0 0 0 0.82  0 0 0 0 0.14  0 0 0 0.32 0',
                    result: 'moltenTint',
                }));
                filter.appendChild(svgEl('feComposite', {
                    in: 'moltenTint',
                    in2: 'SourceAlpha',
                    operator: 'in',
                    result: 'moltenClip',
                }));
                filter.appendChild(svgEl('feBlend', {
                    in: current,
                    in2: 'moltenClip',
                    mode: 'overlay',
                    result: 'moltenLit',
                }));
                current = 'moltenLit';
                filter.appendChild(svgEl('feColorMatrix', {
                    in: 'moltenNoise',
                    type: 'matrix',
                    values: '0 0 0 0 0.42  0 0 0 0 0.22  0 0 0 0 0.02  0 0 0 0.18 0',
                    result: 'moltenDark',
                }));
                filter.appendChild(svgEl('feComposite', {
                    in: 'moltenDark',
                    in2: 'SourceAlpha',
                    operator: 'in',
                    result: 'moltenDarkClip',
                }));
                filter.appendChild(svgEl('feBlend', {
                    in: current,
                    in2: 'moltenDarkClip',
                    mode: 'multiply',
                    result: 'moltenDepth',
                }));
                current = 'moltenDepth';
            }

            if (mat.grain > 0) {
                filter.appendChild(svgEl('feTurbulence', {
                    type: 'fractalNoise',
                    baseFrequency: '0.028 0.05',
                    numOctaves: '3',
                    seed: String(seed + 3),
                    stitchTiles: 'stitch',
                    result: 'noise',
                }));
                filter.appendChild(svgEl('feColorMatrix', {
                    in: 'noise',
                    type: 'matrix',
                    values: `0 0 0 0 0.52  0 0 0 0 0.5  0 0 0 0 0.46  0 0 0 ${mat.grain} 0`,
                    result: 'grain',
                }));
                filter.appendChild(svgEl('feComposite', {
                    in: 'grain',
                    in2: 'SourceAlpha',
                    operator: 'in',
                    result: 'grainClip',
                }));
                filter.appendChild(svgEl('feBlend', {
                    in: current,
                    in2: 'grainClip',
                    mode: 'overlay',
                    result: 'grained',
                }));
                current = 'grained';
            }

            if (mat.veins > 0) {
                filter.appendChild(svgEl('feTurbulence', {
                    type: 'turbulence',
                    baseFrequency: '0.007 0.042',
                    numOctaves: '2',
                    seed: String(seed + 5),
                    stitchTiles: 'stitch',
                    result: 'veinNoise',
                }));
                filter.appendChild(svgEl('feColorMatrix', {
                    in: 'veinNoise',
                    type: 'matrix',
                    values: mat.veinCool
                        ? `0 0 0 0 0.25  0 0 0 0 0.48  0 0 0 0 0.62  0 0 0 ${mat.veins} 0`
                        : `0 0 0 0 0.12  0 0 0 0 0.08  0 0 0 0 0.05  0 0 0 ${mat.veins} 0`,
                    result: 'veinTint',
                }));
                filter.appendChild(svgEl('feComposite', {
                    in: 'veinTint',
                    in2: 'SourceAlpha',
                    operator: 'in',
                    result: 'veinClip',
                }));
                filter.appendChild(svgEl('feBlend', {
                    in: current,
                    in2: 'veinClip',
                    mode: 'multiply',
                    result: 'veined',
                }));
                current = 'veined';
            }
        }

        if (needGlow) {
            filter.appendChild(svgEl('feMorphology', {
                in: 'SourceAlpha',
                operator: 'erode',
                radius: String(glow.erode),
                result: 'glowEroded',
            }));
            filter.appendChild(svgEl('feComposite', {
                in: 'SourceAlpha',
                in2: 'glowEroded',
                operator: 'out',
                result: 'glowRim',
            }));
            filter.appendChild(svgEl('feGaussianBlur', {
                in: 'glowRim',
                stdDeviation: String(glow.blur),
                result: 'glowBlur',
            }));
            filter.appendChild(svgEl('feFlood', {
                'flood-color': glow.color,
                'flood-opacity': String(glow.opacity),
                result: 'glowFlood',
            }));
            filter.appendChild(svgEl('feComposite', {
                in: 'glowFlood',
                in2: 'glowBlur',
                operator: 'in',
                result: 'glowColored',
            }));
            filter.appendChild(svgEl('feComposite', {
                in: 'glowColored',
                in2: 'SourceAlpha',
                operator: 'in',
                result: 'glowInner',
            }));
            const merge = svgEl('feMerge', { result: 'withGlow' });
            merge.appendChild(svgEl('feMergeNode', { in: current }));
            merge.appendChild(svgEl('feMergeNode', { in: 'glowInner' }));
            filter.appendChild(merge);
            current = 'withGlow';
        }

        filter.appendChild(svgEl('feComposite', {
            in: current,
            in2: 'SourceAlpha',
            operator: 'in',
        }));
        this.defs.appendChild(filter);
    }

    buildHaloFilters(level, brush) {
        if (!brush.outerGlow.enabled || brush.outerGlow.blur <= 0) {
            return;
        }
        const glow = brush.outerGlow;
        [
            { key: 0, blur: Math.max(8, glow.blur * 0.9) },
            { key: 1, blur: Math.max(4, glow.blur * 0.4) },
        ].forEach((spec) => {
            const id = this.qid(`halo-${level}-${spec.key}`);
            document.getElementById(id)?.remove();
            const filter = svgEl('filter', {
                id,
                x: '-150%',
                y: '-150%',
                width: '400%',
                height: '400%',
                filterUnits: 'objectBoundingBox',
                primitiveUnits: 'userSpaceOnUse',
                'color-interpolation-filters': 'sRGB',
            });
            filter.appendChild(svgEl('feGaussianBlur', {
                in: 'SourceGraphic',
                stdDeviation: String(spec.blur),
            }));
            this.defs.appendChild(filter);
        });
    }

    applyBrush(group, brush, checkout) {
        const level = group.getAttribute('data-level');
        group.style.setProperty('--cw-fill', brush.fill);
        group.style.setProperty('--cw-stroke', brush.stroke);
        group.style.setProperty('--cw-stroke-width', `${brush.strokeWidth}px`);
        group.style.setProperty('--cw-label', brush.label);
        group.style.setProperty('--cw-sheen', String(brush.material?.sheen ?? 0));
        group.style.setProperty('--cw-sheen-blend', brush.material?.sheenBlend || 'overlay');

        const filterId = brush.material?.enabled ? this.qid(`mat-${level}`) : null;
        group.style.setProperty('--cw-shape-filter', filterId ? `url(#${filterId})` : 'none');

        this.syncHalo(group, brush, level);
        this.syncInnerStroke(group, brush, checkout);
        this.syncSheen(group, brush);
        this.syncCracks(group, brush, checkout);
        this.syncMolten(group, brush, checkout);
        this.syncSparkles(group, brush, checkout);
    }

    syncHalo(group, brush, level) {
        const shape = group.querySelector('.checkout-shape');
        group.querySelectorAll('.checkout-halo').forEach((el) => el.remove());
        if (!shape || !brush.outerGlow.enabled) {
            return;
        }
        const glow = brush.outerGlow;
        [
            { key: 0, opacity: glow.opacity * 0.55, color: glow.color },
            { key: 1, opacity: Math.min(0.7, glow.opacity * 0.75), color: brush.fill },
        ].forEach((spec) => {
            const halo = shape.cloneNode(true);
            halo.classList.remove('checkout-shape', 'checkout-segment');
            halo.classList.add('checkout-halo');
            halo.removeAttribute('id');
            halo.removeAttribute('style');
            halo.setAttribute('fill', spec.color);
            halo.setAttribute('stroke', 'none');
            halo.setAttribute('opacity', String(spec.opacity));
            halo.setAttribute('filter', `url(#${this.qid(`halo-${level}-${spec.key}`)})`);
            shape.parentNode.insertBefore(halo, shape);
        });
    }

    ensureClip(group, checkout) {
        const shape = group.querySelector('.checkout-shape');
        const clipId = this.qid(`clip-${checkout}`);
        document.getElementById(clipId)?.remove();
        if (!shape) {
            return clipId;
        }
        const clip = svgEl('clipPath', { id: clipId, clipPathUnits: 'userSpaceOnUse' });
        const clipShape = shape.cloneNode(true);
        clipShape.removeAttribute('id');
        clipShape.removeAttribute('class');
        clipShape.removeAttribute('style');
        clipShape.removeAttribute('filter');
        clip.appendChild(clipShape);
        this.defs.appendChild(clip);
        return clipId;
    }

    syncInnerStroke(group, brush, checkout) {
        const shape = group.querySelector('.checkout-shape');
        const existing = group.querySelector('.checkout-inner-stroke');
        if (!shape || !brush.innerStroke.enabled || brush.innerStroke.width <= 0) {
            existing?.remove();
            return;
        }
        const clipId = this.ensureClip(group, checkout);
        let strokeEl = existing;
        if (!strokeEl) {
            strokeEl = shape.cloneNode(true);
            strokeEl.classList.remove('checkout-shape', 'checkout-segment');
            strokeEl.classList.add('checkout-inner-stroke');
            strokeEl.removeAttribute('id');
            shape.after(strokeEl);
        }
        strokeEl.setAttribute('fill', 'none');
        strokeEl.setAttribute('stroke', brush.innerStroke.color);
        strokeEl.setAttribute('stroke-width', String(brush.innerStroke.width * 2));
        strokeEl.setAttribute('clip-path', `url(#${clipId})`);
    }

    syncSheen(group, brush) {
        const shape = group.querySelector('.checkout-shape');
        const existing = group.querySelector('.checkout-sheen');
        const sheenOn = Boolean(brush.material?.enabled && (brush.material.sheen ?? 0) > 0);
        if (!shape || !sheenOn) {
            existing?.remove();
            return;
        }
        let sheen = existing;
        if (!sheen) {
            sheen = shape.cloneNode(true);
            sheen.classList.remove('checkout-shape', 'checkout-segment');
            sheen.classList.add('checkout-sheen');
            sheen.removeAttribute('id');
            sheen.removeAttribute('style');
            sheen.removeAttribute('filter');
            shape.after(sheen);
        }
        const fill = brush.material.sheenFill === 'gold'
            ? `url(#${this.qid('sheen-gold')})`
            : `url(#${this.qid('sheen')})`;
        sheen.setAttribute('fill', fill);
        sheen.setAttribute('stroke', 'none');
    }

    syncCracks(group, brush, checkout) {
        group.querySelector('.checkout-cracks')?.remove();
        if (!brush.material?.cracks) {
            return;
        }
        const shape = group.querySelector('.checkout-shape');
        if (!shape) {
            return;
        }
        const n = Number(checkout);
        const clipId = this.ensureClip(group, checkout);
        const bbox = shape.getBBox();
        const cx = bbox.x + bbox.width / 2;
        const cy = bbox.y + bbox.height / 2;
        const originX = n === 170 ? 450 : cx + ((450 - cx) * 0.12);
        const originY = n === 170 ? 450 : cy + ((450 - cy) * 0.12);
        const span = Math.min(bbox.width, bbox.height);
        const isDiamond = Boolean(brush.material.sparkle);
        const count = isDiamond ? (n === 170 ? 10 : 9) : 8;
        const layer = svgEl('g', { class: 'checkout-cracks', 'clip-path': `url(#${clipId})` });

        for (let i = 0; i < count; i += 1) {
            const seed = n * 13 + i * 97;
            const ang = hash01(seed) * Math.PI * 2;
            const bend = (hash01(seed + 3) - 0.5) * 0.55;
            const inner = span * (0.06 + hash01(seed + 1) * 0.1);
            const outer = span * (0.4 + hash01(seed + 2) * 0.34);
            const x1 = originX + Math.cos(ang) * inner;
            const y1 = originY + Math.sin(ang) * inner;
            const x2 = originX + Math.cos(ang + bend) * outer;
            const y2 = originY + Math.sin(ang + bend) * outer;
            const steps = 4 + Math.floor(hash01(seed + 4) * 3);
            const amp = span * (isDiamond ? 0.038 : 0.052);
            layer.appendChild(svgEl('path', {
                d: polylineD(jaggedLine(x1, y1, x2, y2, steps, amp, seed)),
                stroke: isDiamond ? '#ecfeff' : '#bae6fd',
                'stroke-width': isDiamond ? (i % 3 === 0 ? '0.95' : '0.5') : (i % 2 === 0 ? '1.1' : '0.65'),
                opacity: isDiamond ? '0.78' : '0.64',
            }));
            if (hash01(seed + 8) > 0.28) {
                const mid = 0.42 + hash01(seed + 9) * 0.28;
                const mx = x1 + (x2 - x1) * mid;
                const my = y1 + (y2 - y1) * mid;
                const bang = ang + (hash01(seed + 10) > 0.5 ? 0.75 : -0.75);
                const blen = outer * 0.38;
                layer.appendChild(svgEl('path', {
                    d: polylineD(jaggedLine(mx, my, mx + Math.cos(bang) * blen, my + Math.sin(bang) * blen, 3, amp * 0.7, seed + 20)),
                    stroke: isDiamond ? '#ffffff' : '#7dd3fc',
                    'stroke-width': isDiamond ? '0.45' : '0.55',
                    opacity: '0.58',
                }));
            }
        }

        if (!isDiamond) {
            for (let i = 0; i < 2; i += 1) {
                const seed = n * 19 + i * 41;
                const ang = hash01(seed) * Math.PI * 2;
                const x2 = originX + Math.cos(ang) * span * (0.42 + i * 0.1);
                const y2 = originY + Math.sin(ang) * span * (0.42 + i * 0.1);
                layer.appendChild(svgEl('path', {
                    class: 'is-inclusion',
                    d: polylineD(jaggedLine(originX, originY, x2, y2, 5, span * 0.04, seed)),
                    stroke: '#0c4a6e',
                    'stroke-width': '0.9',
                    opacity: '0.32',
                }));
            }
        }

        const label = group.querySelector('.checkout-label');
        if (label) {
            label.before(layer);
        } else {
            shape.after(layer);
        }
    }

    syncMolten(group, brush, checkout) {
        group.querySelector('.checkout-molten')?.remove();
        if (!brush.material?.molten) {
            return;
        }
        const shape = group.querySelector('.checkout-shape');
        if (!shape) {
            return;
        }
        const n = Number(checkout);
        const clipId = this.ensureClip(group, checkout);
        const bbox = shape.getBBox();
        const cx = bbox.x + bbox.width / 2;
        const cy = bbox.y + bbox.height / 2;
        const span = Math.min(bbox.width, bbox.height);
        const layer = svgEl('g', { class: 'checkout-molten', 'clip-path': `url(#${clipId})` });

        const pours = 4 + Math.floor(hash01(n) * 2);
        for (let i = 0; i < pours; i += 1) {
            const seed = n * 17 + i * 53;
            const startAng = hash01(seed) * Math.PI * 2;
            const startR = span * (0.08 + hash01(seed + 1) * 0.22);
            const x0 = cx + Math.cos(startAng) * startR;
            const y0 = cy + Math.sin(startAng) * startR;
            const heading = startAng + (hash01(seed + 2) - 0.5) * 1.8;
            const steps = 7 + Math.floor(hash01(seed + 3) * 6);
            const stepLen = span * (0.06 + hash01(seed + 4) * 0.05);
            const wander = 0.55 + hash01(seed + 5) * 0.45;
            const d = smoothPathD(wanderPoints(x0, y0, heading, steps, stepLen, wander, seed));
            const thick = 1.5 + hash01(seed + 6) * 2.2;
            layer.appendChild(svgEl('path', {
                class: 'is-pour',
                d,
                stroke: i % 2 === 0 ? '#fff3b0' : '#ffe566',
                'stroke-width': String(thick),
                opacity: String(0.55 + hash01(seed + 7) * 0.16),
            }));
            layer.appendChild(svgEl('path', {
                class: 'is-shadow',
                d,
                stroke: '#7a4e00',
                'stroke-width': String(thick * 0.7),
                opacity: String(0.28 + hash01(seed + 8) * 0.12),
            }));
        }

        const pools = 3 + Math.floor(hash01(n + 9) * 2);
        for (let i = 0; i < pools; i += 1) {
            const seed = n * 29 + i * 71;
            const ang = hash01(seed) * Math.PI * 2;
            const r = span * (0.12 + hash01(seed + 1) * 0.28);
            const px = cx + Math.cos(ang) * r;
            const py = cy + Math.sin(ang) * r;
            layer.appendChild(svgEl('ellipse', {
                class: 'is-pool',
                cx: String(px),
                cy: String(py),
                rx: String(2.8 + hash01(seed + 2) * 4.5),
                ry: String(1.5 + hash01(seed + 3) * 2.6),
                fill: hash01(seed + 4) > 0.5 ? '#fff8d0' : '#ffd54a',
                opacity: String(0.32 + hash01(seed + 5) * 0.18),
                transform: `rotate(${(hash01(seed + 6) * 180).toFixed(1)} ${px} ${py})`,
            }));
        }

        const label = group.querySelector('.checkout-label');
        if (label) {
            label.before(layer);
        } else {
            shape.after(layer);
        }
    }

    syncSparkles(group, brush, checkout) {
        group.querySelectorAll('.checkout-sparkle').forEach((el) => el.remove());
        if (!brush.material?.sparkle) {
            return;
        }
        const shape = group.querySelector('.checkout-shape');
        if (!shape) {
            return;
        }
        const n = Number(checkout);
        const bbox = shape.getBBox();
        const cx = bbox.x + bbox.width / 2;
        const cy = bbox.y + bbox.height / 2;
        const towardX = (450 - cx) * 0.18;
        const towardY = (450 - cy) * 0.18;
        const isBull = n === 170;
        const count = isBull ? 4 : 3;
        const label = group.querySelector('.checkout-label');

        for (let i = 0; i < count; i += 1) {
            const ang = ((n * 0.41) + i * 2.15) % (Math.PI * 2);
            const rad = isBull ? 34 + (i * 4) : Math.min(bbox.width, bbox.height) * (0.16 + (i * 0.05));
            const x = (isBull ? 450 : cx + towardX) + Math.cos(ang) * rad;
            const y = (isBull ? 450 : cy + towardY) + Math.sin(ang) * rad;
            const delay = `${((n % 5) * 0.28) + (i * 0.45)}s`;
            const halo = svgEl('circle', {
                class: 'checkout-sparkle is-halo',
                cx: String(x),
                cy: String(y),
                r: i === 0 ? '3.4' : '2.4',
            });
            halo.style.animationDelay = delay;
            const core = svgEl('circle', {
                class: 'checkout-sparkle',
                cx: String(x),
                cy: String(y),
                r: i === 0 ? '1.35' : '0.95',
            });
            core.style.animationDelay = delay;
            if (label) {
                label.before(halo);
                label.before(core);
            } else {
                shape.after(halo);
                shape.after(core);
            }
        }
    }
}

export function registerCheckoutWheels() {
    document.querySelectorAll('[data-checkout-wheel]').forEach(watchAndMount);
}

function isRendered(el) {
    return el.getClientRects().length > 0;
}

function watchAndMount(host) {
    if (host.dataset.cwMounted === '1') {
        return;
    }

    const tryMount = () => {
        if (host.dataset.cwMounted === '1' || !isRendered(host)) {
            return false;
        }
        host.dataset.cwMounted = '1';
        new CheckoutWheel(host).mount();
        return true;
    };

    if (tryMount()) {
        return;
    }

    const stop = () => {
        io.disconnect();
        mo.disconnect();
    };

    const io = new IntersectionObserver(() => {
        if (tryMount()) {
            stop();
        }
    });
    io.observe(host);

    const hiddenRoot = host.closest('[x-show], [hidden]');
    const mo = new MutationObserver(() => {
        if (tryMount()) {
            stop();
        }
    });
    mo.observe(hiddenRoot || host.parentElement || host, {
        attributes: true,
        attributeFilter: ['style', 'hidden', 'class'],
    });
}
