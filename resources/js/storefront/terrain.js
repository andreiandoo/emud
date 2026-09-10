/**
 * The generated terrain: a slow flight at dusk behind the home page hero, and the trail that
 * scroll drives a camera along.
 *
 * Both draw the same kind of ground — gentle hills with contour lines, the major ones picked out
 * — from a seeded noise field, so they look like one place. This module is its own chunk and is
 * only downloaded by pages that ask for it.
 */
import {
    CatmullRomCurve3,
    Color,
    ColorManagement,
    CylinderGeometry,
    DoubleSide,
    LinearSRGBColorSpace,
    Mesh,
    MeshBasicMaterial,
    PerspectiveCamera,
    PlaneGeometry,
    RingGeometry,
    Scene,
    ShaderMaterial,
    SphereGeometry,
    TubeGeometry,
    Vector3,
    WebGLRenderer,
} from 'three';
import { random } from './effects';

// The shaders here write their colour straight out, the way three.js did before r152. With
// colour management on, every hex below would be converted to linear light on the way in and
// never converted back, and the whole scene comes out several stops darker than it was drawn.
ColorManagement.enabled = false;

const small = window.innerWidth < 760;

function simplex(seed) {
    const gradients = [[1, 1], [-1, 1], [1, -1], [-1, -1], [1, 0], [-1, 0], [0, 1], [0, -1]];
    const p = new Uint8Array(256);

    for (let i = 0; i < 256; i++) {
        p[i] = i;
    }

    const next = random(seed);

    for (let i = 255; i > 0; i--) {
        const j = Math.floor(next() * (i + 1));
        [p[i], p[j]] = [p[j], p[i]];
    }

    const perm = new Uint8Array(512);

    for (let i = 0; i < 512; i++) {
        perm[i] = p[i & 255];
    }

    const F2 = 0.5 * (Math.sqrt(3) - 1);
    const G2 = (3 - Math.sqrt(3)) / 6;

    return (xin, yin) => {
        const s = (xin + yin) * F2;
        const i = Math.floor(xin + s);
        const j = Math.floor(yin + s);
        const t = (i + j) * G2;
        const x0 = xin - (i - t);
        const y0 = yin - (j - t);
        const i1 = x0 > y0 ? 1 : 0;
        const j1 = x0 > y0 ? 0 : 1;
        const x1 = x0 - i1 + G2;
        const y1 = y0 - j1 + G2;
        const x2 = x0 - 1 + 2 * G2;
        const y2 = y0 - 1 + 2 * G2;
        const ii = i & 255;
        const jj = j & 255;
        let n = 0;
        let q;

        q = 0.5 - x0 * x0 - y0 * y0;
        if (q > 0) {
            const g = gradients[perm[ii + perm[jj]] & 7];
            q *= q;
            n += q * q * (g[0] * x0 + g[1] * y0);
        }

        q = 0.5 - x1 * x1 - y1 * y1;
        if (q > 0) {
            const g = gradients[perm[ii + i1 + perm[jj + j1]] & 7];
            q *= q;
            n += q * q * (g[0] * x1 + g[1] * y1);
        }

        q = 0.5 - x2 * x2 - y2 * y2;
        if (q > 0) {
            const g = gradients[perm[ii + 1 + perm[jj + 1]] & 7];
            q *= q;
            n += q * q * (g[0] * x2 + g[1] * y2);
        }

        return 70 * n;
    };
}

function terrain({ width, depth, segmentsX, segmentsZ, seed, height, scale }) {
    const noise = simplex(seed);

    // Broad hills rather than spikes: plain fractal noise, lifted by a curve so the valleys
    // flatten out and the tops round off.
    const heightAt = (x, z) => {
        let sum = 0;
        let amplitude = 1;
        let frequency = 1 / scale;
        let norm = 0;

        for (let octave = 0; octave < 5; octave++) {
            sum += (noise(x * frequency, z * frequency) * 0.5 + 0.5) * amplitude;
            norm += amplitude;
            amplitude *= 0.48;
            frequency *= 2.07;
        }

        const e = Math.min(1, Math.max(0, (sum / norm - 0.28) / 0.5));

        return Math.pow(e, 1.6) * height - height * 0.3;
    };

    const geometry = new PlaneGeometry(width, depth, segmentsX, segmentsZ);
    geometry.rotateX(-Math.PI / 2);

    const positions = geometry.attributes.position;

    for (let i = 0; i < positions.count; i++) {
        positions.setY(i, heightAt(positions.getX(i), positions.getZ(i)));
    }

    geometry.computeVertexNormals();

    return { geometry, heightAt };
}

function groundMaterial(options) {
    return new ShaderMaterial({
        uniforms: {
            uBase: { value: new Color(options.base) },
            uHigh: { value: new Color(options.high) },
            uLine: { value: new Color(options.line) },
            uMajor: { value: new Color(options.major) },
            uFog: { value: new Color(options.fog) },
            uNear: { value: options.near },
            uFar: { value: options.far },
            uStep: { value: options.step },
            uMin: { value: options.min },
            uMax: { value: options.max },
            uLineAlpha: { value: options.lineAlpha },
            uMajorAlpha: { value: options.majorAlpha },
            uSun: { value: new Vector3(...options.sun).normalize() },
        },
        vertexShader: `
            varying float vHeight;
            varying float vDepth;
            varying vec3 vNormal;
            void main() {
                vHeight = position.y;
                vNormal = normal;
                vec4 view = modelViewMatrix * vec4(position, 1.0);
                vDepth = -view.z;
                gl_Position = projectionMatrix * view;
            }`,
        fragmentShader: `
            uniform vec3 uBase, uHigh, uLine, uMajor, uFog, uSun;
            uniform float uNear, uFar, uStep, uMin, uMax, uLineAlpha, uMajorAlpha;
            varying float vHeight;
            varying float vDepth;
            varying vec3 vNormal;
            void main() {
                float t = clamp((vHeight - uMin) / (uMax - uMin), 0.0, 1.0);
                float light = clamp(dot(normalize(vNormal), uSun), 0.0, 1.0);
                vec3 colour = mix(uBase, uHigh, t) * (0.32 + 0.9 * light);
                float h = vHeight / uStep;
                // fwidth is zero on a perfectly flat valley floor, and smoothstep(0, 0, x) is undefined:
                // without the floor the flats fill with speckle.
                float line = 1.0 - smoothstep(0.0, max(fwidth(h), 1e-4) * 1.4, abs(fract(h - 0.5) - 0.5));
                float hm = vHeight / (uStep * 5.0);
                float major = 1.0 - smoothstep(0.0, max(fwidth(hm), 1e-4) * 1.9, abs(fract(hm - 0.5) - 0.5));
                float near = 1.0 - smoothstep(uNear * 0.5, uFar, vDepth);
                colour = mix(colour, uLine, line * uLineAlpha * near);
                colour = mix(colour, uMajor, major * uMajorAlpha * near);
                colour = mix(colour, uFog, smoothstep(uNear, uFar, vDepth));
                gl_FragColor = vec4(colour, 1.0);
            }`,
    });
}

function renderer(canvas) {
    try {
        const gl = new WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
        gl.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        gl.setClearColor(0x000000, 0);
        gl.outputColorSpace = LinearSRGBColorSpace;

        return gl;
    } catch {
        return null;
    }
}

function fitTo(canvas, gl, camera) {
    const resize = () => {
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;

        if (! width || ! height) {
            return;
        }

        gl.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    };

    resize();
    new ResizeObserver(resize).observe(canvas);
}

/** Runs frame(dt) only while element is near the screen. Reduced motion draws a single frame. */
function whileVisible(element, frame, { reduce, keepRunning = false }) {
    if (reduce && ! keepRunning) {
        frame(0);

        return;
    }

    let running = false;
    let handle = 0;
    let last = 0;

    const tick = (now) => {
        if (! running) {
            handle = 0;

            return;
        }

        const dt = Math.min(0.05, (now - last) / 1000);
        last = now;
        frame(dt);
        handle = requestAnimationFrame(tick);
    };

    new IntersectionObserver(([entry]) => {
        running = entry.isIntersecting;

        if (running && ! handle) {
            last = performance.now();
            handle = requestAnimationFrame(tick);
        }
    }, { rootMargin: '100px' }).observe(element);
}

export function mountHero(canvas, { reduce }) {
    const gl = renderer(canvas);

    if (! gl) {
        return;
    }

    const scene = new Scene();
    const camera = new PerspectiveCamera(50, 1, 0.5, 1200);
    const depth = 1800;
    const ground = terrain({ width: 1200, depth, segmentsX: small ? 120 : 220, segmentsZ: small ? 180 : 330, seed: 11, height: 96, scale: 260 });

    scene.add(new Mesh(ground.geometry, groundMaterial({
        base: '#1B1A18', high: '#534A40', line: '#8E8575', major: '#F26A1B', fog: '#241E1A',
        near: 110, far: 620, step: 3.4, min: -29, max: 60, lineAlpha: 0.4, majorAlpha: 0.55, sun: [-0.35, 0.5, -0.8],
    })));

    const path = (z) => Math.sin(z * 0.003) * 70 + Math.sin(z * 0.0011 + 1) * 45;
    const start = depth / 2 - 140;
    const end = -depth / 2 + 660;
    let z = start;
    let eye = ground.heightAt(path(z), z) + 32;

    fitTo(canvas, gl, camera);

    const frame = (dt) => {
        z -= 10 * dt;

        // The flight loops like a video would, fading back in from the start.
        if (z < end) {
            z = start;
            eye = ground.heightAt(path(z), z) + 32;
            canvas.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 1400, easing: 'ease-out' });
        }

        const x = path(z);
        const below = Math.max(ground.heightAt(x, z), ground.heightAt(path(z - 40), z - 40), ground.heightAt(path(z - 90), z - 90));
        eye += (below + 32 - eye) * Math.min(1, dt * 1.1);

        camera.position.set(x, eye, z);
        camera.lookAt(path(z - 100), eye - 20, z - 100);
        gl.render(scene, camera);
    };

    whileVisible(canvas, frame, { reduce });
    canvas.classList.add('is-live');
}

/**
 * Builds the trail scene inside section and returns a setter for scroll progress (0 to 1).
 * The stops, their labels and the little map are read from the markup, so the copy stays in
 * the Blade file.
 */
export function mountTrail(section, { reduce }) {
    const canvas = section.querySelector('[data-st-trail-gl]');
    const gl = canvas ? renderer(canvas) : null;

    if (! gl) {
        return null;
    }

    const scene = new Scene();
    const camera = new PerspectiveCamera(46, 1, 0.5, 1400);
    const ground = terrain({ width: 680, depth: 680, segmentsX: small ? 130 : 220, segmentsZ: small ? 130 : 220, seed: 42, height: 110, scale: 200 });

    scene.add(new Mesh(ground.geometry, groundMaterial({
        base: '#1C1B19', high: '#5E5449', line: '#9C917F', major: '#E6DDCB', fog: '#141416',
        near: 260, far: 780, step: 4.2, min: -33, max: 77, lineAlpha: 0.42, majorAlpha: 0.4, sun: [-0.5, 0.55, -0.35],
    })));

    const draft = new CatmullRomCurve3(
        [[-250, 200], [-170, 150], [-190, 40], [-90, -5], [-10, 70], [90, 30], [120, -70], [215, -170]].map(([x, z]) => new Vector3(x, 0, z)),
        false,
        'centripetal',
    );
    const points = draft.getSpacedPoints(700).map((p) => new Vector3(p.x, ground.heightAt(p.x, p.z) + 1.6, p.z));
    const route = new CatmullRomCurve3(points);

    const routeMaterial = new ShaderMaterial({
        uniforms: { uProgress: { value: 0 }, uDone: { value: new Color('#F26A1B') }, uAhead: { value: new Color('#E6DDCB') } },
        vertexShader: 'varying vec2 vUv; void main() { vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
        fragmentShader: `
            uniform float uProgress;
            uniform vec3 uDone, uAhead;
            varying vec2 vUv;
            void main() {
                vec3 colour = mix(uAhead * 0.5, uDone, step(vUv.x, uProgress));
                float head = smoothstep(0.01, 0.0, abs(vUv.x - uProgress));
                gl_FragColor = vec4(mix(colour, vec3(1.0, 0.86, 0.72), head), 1.0);
            }`,
    });
    scene.add(new Mesh(new TubeGeometry(route, 1000, 1.2, 6, false), routeMaterial));

    const marker = new Mesh(new SphereGeometry(2.6, 20, 20), new MeshBasicMaterial({ color: 0xF26A1B }));
    const ring = new Mesh(new RingGeometry(4, 5, 40), new MeshBasicMaterial({ color: 0xF26A1B, transparent: true, opacity: 0.6, side: DoubleSide }));
    ring.rotation.x = -Math.PI / 2;
    scene.add(marker, ring);

    const stops = [...section.querySelectorAll('[data-st-trail-stop]')];
    const labels = [...section.querySelectorAll('[data-st-trail-label]')];
    const counter = section.querySelector('[data-st-trail-count]');
    const hint = section.querySelector('[data-st-trail-hint]');
    const map = section.querySelector('[data-st-trail-map]');

    const waypoints = stops.map((stop, index) => {
        const t = Number(stop.dataset.t) || 0;
        const at = route.getPointAt(t);
        const pole = new Mesh(new CylinderGeometry(0.35, 0.35, 18, 6), new MeshBasicMaterial({ color: 0xE6DDCB }));
        pole.position.set(at.x, at.y + 9, at.z);
        scene.add(pole);

        return { t, at, label: labels[index] };
    });

    // The little map in the corner: the same route seen from above, drawn once.
    let mapDone = null;
    let mapDot = null;
    let mapLength = 0;

    if (map) {
        let minX = Infinity;
        let maxX = -Infinity;
        let minZ = Infinity;
        let maxZ = -Infinity;

        points.forEach((p) => {
            minX = Math.min(minX, p.x);
            maxX = Math.max(maxX, p.x);
            minZ = Math.min(minZ, p.z);
            maxZ = Math.max(maxZ, p.z);
        });

        const mx = (x) => 14 + ((x - minX) / (maxX - minX)) * 152;
        const mz = (z) => 12 + ((z - minZ) / (maxZ - minZ)) * 116;
        const d = points.filter((_, i) => i % 6 === 0).map((p, i) => `${i ? 'L' : 'M'}${mx(p.x).toFixed(1)} ${mz(p.z).toFixed(1)}`).join('');
        const dots = waypoints.map(({ at }) => `<circle cx="${mx(at.x)}" cy="${mz(at.z)}" r="3" fill="#E6DDCB"/>`).join('');

        map.innerHTML = `<path d="${d}" fill="none" stroke="#3A3D43" stroke-width="2"/><path data-done d="${d}" fill="none" stroke="#F26A1B" stroke-width="2"/>${dots}<circle data-dot r="5" fill="#F26A1B" stroke="#0E0F11" stroke-width="2"/>`;
        mapDone = map.querySelector('[data-done]');
        mapDot = map.querySelector('[data-dot]');
        mapLength = mapDone.getTotalLength();
        mapDone.style.strokeDasharray = String(mapLength);
    }

    const overview = { position: new Vector3(-40, 300, 360), target: new Vector3(-10, 0, 0) };
    const finish = { position: new Vector3(80, 240, 320), target: new Vector3(0, 0, -20) };
    const follow = (q) => {
        const at = route.getPointAt(q);
        const tangent = route.getTangentAt(q);
        const side = new Vector3(-tangent.z, 0, tangent.x).normalize();

        return {
            position: at.clone().addScaledVector(tangent, -52).add(new Vector3(0, 36, 0)).addScaledVector(side, 20),
            target: route.getPointAt(Math.min(1, q + 0.045)).clone().add(new Vector3(0, 4, 0)),
        };
    };
    const ease = (a, b, x) => {
        const t = Math.min(1, Math.max(0, (x - a) / (b - a)));

        return t * t * (3 - 2 * t);
    };

    const eye = overview.position.clone();
    const look = overview.target.clone();
    let target = reduce ? 1 : 0;
    let progress = target;
    let active = -1;

    fitTo(canvas, gl, camera);

    const frame = (dt) => {
        progress += (target - progress) * (reduce ? 1 : Math.min(1, dt * 5));

        const q = Math.min(1, Math.max(0, (progress - 0.1) / 0.8));
        routeMaterial.uniforms.uProgress.value = reduce ? 1 : q;

        const near = follow(q);
        const into = ease(0, 0.12, progress);
        const out = ease(0.9, 1, progress);
        const wantEye = overview.position.clone().lerp(near.position, into).lerp(finish.position, out);
        const wantLook = overview.target.clone().lerp(near.target, into).lerp(finish.target, out);

        eye.lerp(wantEye, reduce ? 1 : 0.14);
        look.lerp(wantLook, reduce ? 1 : 0.14);
        camera.position.copy(eye);
        camera.lookAt(look);

        const at = route.getPointAt(q);
        marker.position.copy(at);
        ring.position.set(at.x, at.y + 0.2, at.z);
        const pulse = 1 + (Math.sin(performance.now() / 300) * 0.5 + 0.5) * 0.6;
        ring.scale.set(pulse, pulse, pulse);

        gl.render(scene, camera);

        const width = canvas.clientWidth;
        const height = canvas.clientHeight;

        waypoints.forEach(({ t, at: point, label }) => {
            if (! label) {
                return;
            }

            const projected = point.clone();
            projected.y += 21;
            projected.project(camera);

            const onScreen = projected.z < 1 && Math.abs(projected.x) < 1.05 && Math.abs(projected.y) < 1.05;
            label.style.transform = `translate(${(projected.x * 0.5 + 0.5) * width}px, ${(-projected.y * 0.5 + 0.5) * height}px)`;
            label.classList.toggle('is-on', onScreen && progress > 0.06);
            label.classList.toggle('is-passed', q >= t - 0.003);
        });

        let current = 0;
        waypoints.forEach(({ t }, index) => {
            if (q + 0.01 >= t) {
                current = index;
            }
        });

        if (current !== active) {
            active = current;
            stops.forEach((stop, index) => stop.classList.toggle('is-on', index === current || reduce));

            if (counter) {
                counter.textContent = String(current + 1).padStart(2, '0');
            }
        }

        if (mapDone) {
            mapDone.style.strokeDashoffset = String(mapLength * (1 - q));
            const dot = mapDone.getPointAtLength(mapLength * q);
            mapDot.setAttribute('cx', dot.x);
            mapDot.setAttribute('cy', dot.y);
        }

        if (hint) {
            hint.style.opacity = progress > 0.05 ? '0' : '1';
        }
    };

    whileVisible(section, frame, { reduce, keepRunning: true });

    return (value) => {
        target = value;
    };
}
