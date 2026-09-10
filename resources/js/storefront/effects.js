/**
 * Drawn surfaces: painted landscapes that stand in for photography, the particles over the
 * "shop by surface" panels, and the contour lines behind the footer.
 *
 * All of it is plain 2D canvas. Each canvas is redrawn when its size settles rather than on
 * every frame of a resize, because a panel that is animating its width would otherwise repaint
 * a full landscape sixty times a second.
 */

const PALETTES = {
    dusk: { sky: ['#0E0F12', '#3B2E25'], sun: 'rgba(242,106,27,.38)', ridges: ['#3B342E', '#2B2622', '#1D1A18', '#100E0D'] },
    sand: { sky: ['#3E372F', '#C8B28A'], sun: 'rgba(255,214,160,.5)', ridges: ['#A68F69', '#7F6C4E', '#56493A', '#29231C'] },
    steel: { sky: ['#15181C', '#5B6068'], sun: 'rgba(230,221,203,.18)', ridges: ['#484C53', '#35383E', '#25272B', '#121315'] },
    mud: { sky: ['#17140F', '#4B3E30'], sun: 'rgba(242,106,27,.22)', ridges: ['#4B3F31', '#3A3127', '#28221B', '#130F0C'] },
    snow: { sky: ['#2E3742', '#B4BEC8'], sun: 'rgba(255,255,255,.35)', ridges: ['#CDD4DA', '#9AA4AE', '#66717C', '#2A3036'] },
    forest: { sky: ['#101311', '#46513F'], sun: 'rgba(230,221,203,.16)', ridges: ['#36412F', '#283124', '#1B221A', '#0D110D'] },
};

const effects = new Map();

export function random(seed) {
    let state = (seed * 2654435761) >>> 0 || 1;

    return () => {
        state ^= state << 13;
        state >>>= 0;
        state ^= state >>> 17;
        state ^= state << 5;
        state >>>= 0;

        return state / 4294967296;
    };
}

function noise1d(seed) {
    const next = random(seed);
    const points = Array.from({ length: 64 }, next);

    return (x) => {
        const i = Math.floor(x);
        const f = x - i;
        const t = f * f * (3 - 2 * f);
        const a = points[i & 63];
        const b = points[(i + 1) & 63];

        return a + (b - a) * t;
    };
}

function fit(canvas) {
    const width = canvas.clientWidth;
    const height = canvas.clientHeight;

    if (! width || ! height) {
        return null;
    }

    const ratio = Math.min(window.devicePixelRatio || 1, 1.5);
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);

    const context = canvas.getContext('2d');
    context.setTransform(ratio, 0, 0, ratio, 0, 0);

    return { context, width, height, ratio };
}

function landscape(context, width, height, palette, seed) {
    const sky = context.createLinearGradient(0, 0, 0, height * 0.78);
    sky.addColorStop(0, palette.sky[0]);
    sky.addColorStop(1, palette.sky[1]);
    context.fillStyle = sky;
    context.fillRect(0, 0, width, height);

    const sunX = width * (0.25 + (seed % 6) / 10);
    const sunY = height * 0.5;
    const glow = context.createRadialGradient(sunX, sunY, 0, sunX, sunY, Math.max(width, height) * 0.7);
    glow.addColorStop(0, palette.sun);
    glow.addColorStop(1, 'rgba(0,0,0,0)');
    context.fillStyle = glow;
    context.fillRect(0, 0, width, height);

    palette.ridges.forEach((colour, layer) => {
        const n = noise1d(seed * 31 + layer * 7);
        const base = height * (0.44 + layer * 0.11);
        const amplitude = height * (0.25 - layer * 0.045);
        const k = (1.5 + layer * 1.3) / Math.max(width, 1);

        context.beginPath();
        context.moveTo(0, height);

        for (let x = 0; x <= width + 3; x += 3) {
            context.lineTo(x, base - (n(x * k * 2) * 0.7 + n(x * k * 8 + 5) * 0.3) * amplitude);
        }

        context.lineTo(width, height);
        context.closePath();
        context.fillStyle = colour;
        context.fill();
    });

    const grain = random(seed + 99);
    context.fillStyle = 'rgba(255,255,255,.035)';

    for (let i = 0, count = (width * height) / 55; i < count; i++) {
        context.fillRect(grain() * width, grain() * height, 1, 1);
    }
}

function paint(canvas) {
    const surface = fit(canvas);

    if (! surface) {
        return;
    }

    const palette = PALETTES[canvas.dataset.stScene] ?? PALETTES.dusk;
    const seed = Number(canvas.dataset.seed) || 1;

    if (canvas.dataset.stFx) {
        const base = document.createElement('canvas');
        base.width = canvas.width;
        base.height = canvas.height;
        const baseContext = base.getContext('2d');
        baseContext.setTransform(surface.ratio, 0, 0, surface.ratio, 0, 0);
        landscape(baseContext, surface.width, surface.height, palette, seed);

        effects.set(canvas, {
            base,
            context: surface.context,
            width: surface.width,
            height: surface.height,
            kind: canvas.dataset.stFx,
            particles: particles(canvas.dataset.stFx, surface.width, surface.height, seed),
        });

        surface.context.drawImage(base, 0, 0, surface.width, surface.height);

        return;
    }

    landscape(surface.context, surface.width, surface.height, palette, seed);
}

const pending = new Map();
const resizes = new ResizeObserver((entries) => {
    entries.forEach(({ target }) => {
        if (! target.dataset.stPainted) {
            target.dataset.stPainted = '1';
            paint(target);

            return;
        }

        clearTimeout(pending.get(target));
        pending.set(target, setTimeout(() => paint(target), 140));
    });
});

/** Paints every scene canvas under root that has not been painted yet. Safe to call again. */
export function mountScenes(root) {
    root.querySelectorAll('canvas[data-st-scene]:not([data-st-watched])').forEach((canvas) => {
        canvas.dataset.stWatched = '1';
        resizes.observe(canvas);
    });
}

function particles(kind, width, height, seed) {
    const next = random(seed + 5);
    const count = { mud: 90, rock: 70, sand: 140, snow: 160 }[kind] ?? 80;

    return Array.from({ length: count }, () => spawn(kind, width, height, next, true));
}

function spawn(kind, width, height, next, initial) {
    if (kind === 'mud') {
        return { x: width * (0.22 + next() * 0.1), y: height * 0.8, vx: 80 + next() * 260, vy: -(160 + next() * 320), size: 1.5 + next() * 4.5, next };
    }

    if (kind === 'rock') {
        return { x: next() * width, y: next() * height, vx: 6 + next() * 14, vy: -(2 + next() * 6), size: 0.8 + next() * 1.8, alpha: 0.15 + next() * 0.35, next };
    }

    if (kind === 'sand') {
        return { x: initial ? next() * width : -40, y: height * (0.35 + next() * 0.6), vx: 260 + next() * 420, length: 10 + next() * 46, alpha: 0.08 + next() * 0.25, phase: next() * 6, next };
    }

    return { x: next() * width, y: initial ? next() * height : -10, vy: 30 + next() * 60, size: 0.8 + next() * 2.4, phase: next() * 6, alpha: 0.5 + next() * 0.5, next };
}

function step(effect, dt, time) {
    const { context, width, height, kind } = effect;
    context.drawImage(effect.base, 0, 0, width, height);

    for (const p of effect.particles) {
        if (kind === 'mud') {
            p.vy += 520 * dt;
            p.x += p.vx * dt;
            p.y += p.vy * dt;
            context.fillStyle = 'rgba(40,31,23,.9)';
            context.beginPath();
            context.ellipse(p.x, p.y, p.size * 1.3, p.size, Math.atan2(p.vy, p.vx), 0, 7);
            context.fill();

            if (p.y > height + 10 || p.x > width + 10) {
                Object.assign(p, spawn(kind, width, height, p.next, false));
            }
        } else if (kind === 'rock') {
            p.x += p.vx * dt;
            p.y += p.vy * dt + Math.sin(time + p.x * 0.02) * 0.2;
            context.fillStyle = `rgba(230,221,203,${p.alpha})`;
            context.fillRect(p.x, p.y, p.size, p.size);

            if (p.x > width + 4 || p.y < -4) {
                Object.assign(p, spawn(kind, width, height, p.next, true), { x: -4 });
            }
        } else if (kind === 'sand') {
            p.x += p.vx * dt;
            const y = p.y + Math.sin(time * 2 + p.phase) * 4;
            context.strokeStyle = `rgba(236,214,172,${p.alpha})`;
            context.lineWidth = 1;
            context.beginPath();
            context.moveTo(p.x, y);
            context.lineTo(p.x - p.length, y + 1.5);
            context.stroke();

            if (p.x - p.length > width) {
                Object.assign(p, spawn(kind, width, height, p.next, false));
            }
        } else {
            p.y += p.vy * dt;
            const x = p.x + Math.sin(time + p.phase) * 12;
            context.fillStyle = `rgba(255,255,255,${p.alpha})`;
            context.beginPath();
            context.arc(x, p.y, p.size, 0, 7);
            context.fill();

            if (p.y > height + 6) {
                Object.assign(p, spawn(kind, width, height, p.next, false));
            }
        }
    }
}

/** Animates the particles of whichever surface panel is open, while the panels are on screen. */
export function mountSurfaceEffects({ reduce }) {
    const group = document.querySelector('[data-st-surface]');

    if (! group || reduce) {
        return;
    }

    let visible = false;
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
    }).observe(group);

    let last = performance.now();

    const loop = (now) => {
        const dt = Math.min(0.05, (now - last) / 1000);
        last = now;

        if (visible) {
            const canvas = group.querySelector('.is-open canvas[data-st-fx]');
            const effect = canvas ? effects.get(canvas) : null;

            if (effect) {
                step(effect, dt, now / 1000);
            }
        }

        requestAnimationFrame(loop);
    };

    requestAnimationFrame(loop);
}

const CASES = {
    1: [['l', 'b']], 2: [['b', 'r']], 3: [['l', 'r']], 4: [['t', 'r']], 5: [['t', 'r'], ['l', 'b']], 6: [['t', 'b']], 7: [['t', 'l']],
    8: [['t', 'l']], 9: [['t', 'b']], 10: [['t', 'l'], ['b', 'r']], 11: [['t', 'r']], 12: [['l', 'r']], 13: [['b', 'r']], 14: [['l', 'b']],
};

function hash(i, j) {
    let h = (Math.imul(i, 374761393) + Math.imul(j, 668265263)) | 0;
    h = Math.imul(h ^ (h >>> 13), 1274126177);

    return ((h ^ (h >>> 16)) >>> 0) / 4294967296;
}

function valueNoise(x, y) {
    const i = Math.floor(x);
    const j = Math.floor(y);
    const fx = x - i;
    const fy = y - j;
    const u = fx * fx * (3 - 2 * fx);
    const v = fy * fy * (3 - 2 * fy);
    const a = hash(i, j);
    const b = hash(i + 1, j);
    const c = hash(i, j + 1);
    const d = hash(i + 1, j + 1);

    return a + (b - a) * u + (c - a) * v + (a - b - c + d) * u * v;
}

/** Contour lines, as on a topographic map: the shop's quiet signature on dark grounds. */
function contours(canvas) {
    const surface = fit(canvas);

    if (! surface) {
        return;
    }

    const { context, width, height } = surface;
    const cell = 10;
    const cols = Math.ceil(width / cell) + 1;
    const rows = Math.ceil(height / cell) + 1;
    const scale = 1 / 240;
    const field = new Float32Array(cols * rows);

    for (let j = 0; j < rows; j++) {
        for (let i = 0; i < cols; i++) {
            const x = i * cell * scale;
            const y = j * cell * scale;
            field[j * cols + i] = valueNoise(x, y) * 0.6 + valueNoise(x * 2.1 + 3, y * 2.1) * 0.3 + valueNoise(x * 4.3, y * 4.3 + 7) * 0.1;
        }
    }

    context.strokeStyle = canvas.dataset.stTopo || 'rgba(241,238,230,.05)';
    context.lineWidth = 1;

    for (let level = 0.2; level < 0.85; level += 0.04) {
        context.beginPath();

        for (let j = 0; j < rows - 1; j++) {
            for (let i = 0; i < cols - 1; i++) {
                const a = field[j * cols + i];
                const b = field[j * cols + i + 1];
                const c = field[(j + 1) * cols + i + 1];
                const d = field[(j + 1) * cols + i];
                const segments = CASES[(a > level ? 8 : 0) | (b > level ? 4 : 0) | (c > level ? 2 : 0) | (d > level ? 1 : 0)];

                if (! segments) {
                    continue;
                }

                const x = i * cell;
                const y = j * cell;
                const points = {
                    t: [x + cell * (level - a) / (b - a), y],
                    r: [x + cell, y + cell * (level - b) / (c - b)],
                    b: [x + cell * (level - d) / (c - d), y + cell],
                    l: [x, y + cell * (level - a) / (d - a)],
                };

                for (const [from, to] of segments) {
                    context.moveTo(points[from][0], points[from][1]);
                    context.lineTo(points[to][0], points[to][1]);
                }
            }
        }

        context.stroke();
    }
}

export function mountTopo(root) {
    root.querySelectorAll('canvas[data-st-topo]:not([data-st-watched])').forEach((canvas) => {
        canvas.dataset.stWatched = '1';
        new ResizeObserver(() => contours(canvas)).observe(canvas);
    });
}
