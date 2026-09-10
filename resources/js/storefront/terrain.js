/**
 * The generated ground: a slow flight at dusk behind the home page hero, and the trail that
 * scroll drives a car along.
 *
 * The hero draws gentle hills with contour lines from a seeded noise field. The trail draws a
 * landscape — meadows, woods, a river, mud volcanoes, a lake — because it stands for a real
 * route through the Buzău hills. This module is its own chunk and is only downloaded by pages
 * that ask for it.
 */
import {
    BoxGeometry,
    BufferGeometry,
    CatmullRomCurve3,
    CircleGeometry,
    Color,
    ColorManagement,
    ConeGeometry,
    CylinderGeometry,
    DirectionalLight,
    DoubleSide,
    Float32BufferAttribute,
    Fog,
    HemisphereLight,
    InstancedMesh,
    LinearSRGBColorSpace,
    Mesh,
    MeshBasicMaterial,
    MeshLambertMaterial,
    Object3D,
    PerspectiveCamera,
    PlaneGeometry,
    RingGeometry,
    Scene,
    ShaderMaterial,
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

const mix = (a, b, t) => a + (b - a) * t;
const smooth = (x) => x * x * (3 - 2 * x);

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

/** Fractal noise from 0 to 1, broad at `scale` and finer with every octave. */
function fbmOf(noise) {
    return (x, z, scale, octaves = 5) => {
        let sum = 0;
        let amplitude = 1;
        let frequency = 1 / scale;
        let norm = 0;

        for (let octave = 0; octave < octaves; octave++) {
            sum += (noise(x * frequency, z * frequency) * 0.5 + 0.5) * amplitude;
            norm += amplitude;
            amplitude *= 0.5;
            frequency *= 2.03;
        }

        return sum / norm;
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

/**
 * Hill country at dusk: meadow in the valleys, drier grass higher up, bare earth and stone on
 * the steep parts, dark mud at the water's edge, a grain so no field reads as flat paint, and
 * the faint contour lines the rest of the shop draws.
 */
function meadowMaterial({ fog, near, far, water, sun }) {
    return new ShaderMaterial({
        uniforms: {
            uGrass: { value: new Color('#3b4a2b') },
            uDry: { value: new Color('#6e6947') },
            uEarth: { value: new Color('#6a5440') },
            uRock: { value: new Color('#7b756b') },
            uLine: { value: new Color('#dcd2bb') },
            uFog: { value: new Color(fog) },
            uSun: { value: sun.clone().normalize() },
            uNear: { value: near },
            uFar: { value: far },
            uWater: { value: water },
        },
        vertexShader: `
            varying float vHeight;
            varying float vDepth;
            varying vec3 vNormal;
            varying vec2 vXZ;
            void main() {
                vHeight = position.y;
                vNormal = normal;
                vXZ = position.xz;
                vec4 view = modelViewMatrix * vec4(position, 1.0);
                vDepth = -view.z;
                gl_Position = projectionMatrix * view;
            }`,
        fragmentShader: `
            uniform vec3 uGrass, uDry, uEarth, uRock, uLine, uFog, uSun;
            uniform float uNear, uFar, uWater;
            varying float vHeight;
            varying float vDepth;
            varying vec3 vNormal;
            varying vec2 vXZ;
            float hash(vec2 p) { return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453); }
            float grain(vec2 p) {
                vec2 i = floor(p);
                vec2 f = fract(p);
                f = f * f * (3.0 - 2.0 * f);
                return mix(mix(hash(i), hash(i + vec2(1.0, 0.0)), f.x), mix(hash(i + vec2(0.0, 1.0)), hash(i + vec2(1.0, 1.0)), f.x), f.y);
            }
            void main() {
                vec3 n = normalize(vNormal);
                float slope = 1.0 - n.y;
                float g = grain(vXZ * 0.07) * 0.6 + grain(vXZ * 0.5) * 0.4;
                vec3 colour = mix(uGrass, uDry, smoothstep(-6.0, 24.0, vHeight + (g - 0.5) * 12.0));
                colour = mix(colour, uEarth, smoothstep(0.1, 0.26, slope + (g - 0.5) * 0.07));
                colour = mix(colour, uRock, smoothstep(0.3, 0.46, slope));
                colour = mix(colour, uEarth * 0.55, smoothstep(uWater + 1.2, uWater + 0.2, vHeight));
                colour *= 0.84 + g * 0.3;
                float light = clamp(dot(n, uSun), 0.0, 1.0);
                colour *= 0.5 + 0.95 * light;
                float h = vHeight / 4.0;
                float line = 1.0 - smoothstep(0.0, max(fwidth(h), 1e-4) * 1.3, abs(fract(h - 0.5) - 0.5));
                colour = mix(colour, uLine, line * 0.1 * (1.0 - smoothstep(uNear * 0.3, uFar * 0.7, vDepth)));
                colour = mix(colour, uFog, smoothstep(uNear, uFar, vDepth));
                gl_FragColor = vec4(colour, 1.0);
            }`,
    });
}

/** A dirt road: two darker wheel ruts, edges that fade into the grass rather than stop. */
function roadMaterial({ fog, near, far }) {
    return new ShaderMaterial({
        uniforms: {
            uDirt: { value: new Color('#7a664d') },
            uRut: { value: new Color('#45382a') },
            uFog: { value: new Color(fog) },
            uNear: { value: near },
            uFar: { value: far },
        },
        transparent: true,
        depthWrite: false,
        side: DoubleSide,
        polygonOffset: true,
        polygonOffsetFactor: -2,
        polygonOffsetUnits: -2,
        vertexShader: `
            varying vec2 vUv;
            varying float vDepth;
            void main() {
                vUv = uv;
                vec4 view = modelViewMatrix * vec4(position, 1.0);
                vDepth = -view.z;
                gl_Position = projectionMatrix * view;
            }`,
        fragmentShader: `
            uniform vec3 uDirt, uRut, uFog;
            uniform float uNear, uFar;
            varying vec2 vUv;
            varying float vDepth;
            void main() {
                float across = vUv.y;
                float ruts = smoothstep(0.09, 0.0, abs(across - 0.3)) + smoothstep(0.09, 0.0, abs(across - 0.7));
                float edge = smoothstep(0.0, 0.18, across) * smoothstep(1.0, 0.82, across);
                vec3 colour = mix(uDirt, uRut, clamp(ruts, 0.0, 1.0) * 0.7);
                colour = mix(colour, uFog, smoothstep(uNear, uFar, vDepth));
                gl_FragColor = vec4(colour, edge * 0.92);
            }`,
    });
}

/** Still water under a dusk sky: a slow ripple, and a paler sheen the further it lies. */
function waterMaterial({ fog, near, far }) {
    return new ShaderMaterial({
        uniforms: {
            uDeep: { value: new Color('#25343a') },
            uSky: { value: new Color('#6f7b78') },
            uFog: { value: new Color(fog) },
            uNear: { value: near },
            uFar: { value: far },
            uTime: { value: 0 },
        },
        transparent: true,
        // The river is a ribbon whose winding depends on which way it bends; seen from above,
        // half of it would otherwise face away and not be drawn.
        side: DoubleSide,
        vertexShader: `
            varying vec3 vWorld;
            varying float vDepth;
            void main() {
                vec4 world = modelMatrix * vec4(position, 1.0);
                vWorld = world.xyz;
                vec4 view = viewMatrix * world;
                vDepth = -view.z;
                gl_Position = projectionMatrix * view;
            }`,
        fragmentShader: `
            uniform vec3 uDeep, uSky, uFog;
            uniform float uNear, uFar, uTime;
            varying vec3 vWorld;
            varying float vDepth;
            void main() {
                float ripple = sin(vWorld.x * 0.42 + uTime * 0.9) * sin(vWorld.z * 0.31 - uTime * 0.7);
                float sheen = smoothstep(0.35, 1.0, ripple) * 0.22 + smoothstep(120.0, 520.0, vDepth) * 0.35;
                vec3 colour = mix(uDeep, uSky, sheen);
                colour = mix(colour, uFog, smoothstep(uNear, uFar, vDepth));
                gl_FragColor = vec4(colour, 0.94);
            }`,
    });
}

/** A strip laid along points on the map, each edge sampled from `surface` so it drapes. */
function ribbon(points, width, surface) {
    const positions = [];
    const uvs = [];
    const indices = [];
    const last = points.length - 1;

    points.forEach((point, i) => {
        const a = points[Math.max(0, i - 1)];
        const b = points[Math.min(last, i + 1)];
        let sx = -(b.z - a.z);
        let sz = b.x - a.x;
        const length = Math.hypot(sx, sz) || 1;
        sx /= length;
        sz /= length;

        [-1, 1].forEach((side) => {
            const x = point.x + sx * side * width * 0.5;
            const z = point.z + sz * side * width * 0.5;
            positions.push(x, surface(x, z), z);
            uvs.push(i / last, side < 0 ? 0 : 1);
        });

        if (i < last) {
            const k = i * 2;
            indices.push(k, k + 2, k + 1, k + 1, k + 2, k + 3);
        }
    });

    const geometry = new BufferGeometry();
    geometry.setAttribute('position', new Float32BufferAttribute(positions, 3));
    geometry.setAttribute('uv', new Float32BufferAttribute(uvs, 2));
    geometry.setIndex(indices);

    return geometry;
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
 * The trail: a car driven along a route by scroll, through low hills cut by a river, past a
 * field of mud volcanoes, up a ridge and down to a lake. It slows into each checkpoint and holds
 * there while the camera swings round it and the panel says what the place is; then it drives
 * on. The stops, the panel, the labels and the kilometres come from the markup, so the copy
 * stays in the Blade file.
 *
 * Returns a setter for scroll progress (0 to 1). `snap` goes straight to that moment instead of
 * easing there, for a screenshot of one point of the drive; the page itself never sets it.
 */
export function mountTrail(section, { reduce, snap = false }) {
    const canvas = section.querySelector('[data-st-trail-gl]');
    const gl = canvas ? renderer(canvas) : null;

    if (! gl) {
        return null;
    }

    const stops = [...section.querySelectorAll('[data-st-trail-stop]')];
    const details = [...section.querySelectorAll('[data-st-trail-detail]')];
    const labels = [...section.querySelectorAll('[data-st-trail-label]')];
    const panel = section.querySelector('[data-st-trail-panel]');
    const kilometres = section.querySelector('[data-st-trail-km]');
    const hint = section.querySelector('[data-st-trail-hint]');
    const map = section.querySelector('[data-st-trail-map]');
    const distance = Number(section.dataset.distance) || 0;
    const marks = stops.map((stop) => Math.min(1, Math.max(0, Number(stop.dataset.t) || 0)));

    const FOG = '#1d1a17';
    const NEAR = 170;
    const FAR = 640;
    const WATER = -10.5;
    const SIZE = 900;
    const sunDirection = new Vector3(-0.62, 0.42, -0.28);

    const scene = new Scene();
    scene.fog = new Fog(FOG, NEAR, FAR);
    scene.add(new HemisphereLight(0xcdbb9c, 0x2a241b, 2.1));

    const sun = new DirectionalLight(0xffd6a8, 2.4);
    sun.position.copy(sunDirection).multiplyScalar(100);
    scene.add(sun);

    const camera = new PerspectiveCamera(40, 1, 0.5, 2200);

    // ---------- the land

    // The road on the map only; its heights come from the ground once the ground exists.
    const draft = new CatmullRomCurve3(
        [[-300, 250], [-235, 175], [-160, 125], [-95, 70], [-40, 5], [30, -35], [100, -20], [160, -85], [205, -160], [250, -225]]
            .map(([x, z]) => new Vector3(x, 0, z)),
        false,
        'centripetal',
    );
    const on = (t) => draft.getPointAt(Math.min(1, Math.max(0, t)));
    const ford = on(marks[1] ?? 0.24);
    const vents = on(marks[2] ?? 0.41);
    const climb = on(marks[3] ?? 0.67);
    const end = on(1);
    const onward = end.clone().sub(on(0.96)).setY(0).normalize();
    const lake = { x: end.x + onward.x * 36, z: end.z + onward.z * 36, r: 30 };

    const fbm = fbmOf(simplex(7));
    const around = (x, z, cx, cz, r) => Math.exp(-((x - cx) ** 2 + (z - cz) ** 2) / (2 * r * r));
    const riverX = (z) => ford.x + Math.sin((z - ford.z) * 0.0068) * 58 + Math.sin((z - ford.z) * 0.019 + 1.3) * 11;

    // Low, rolling hills: the Buzău foothills rise a few hundred metres over their valleys, not
    // thousands. On top of them, the shapes the route needs: a valley with a river to ford at
    // the second stop, a bare shelf for the mud volcanoes, a whaleback to climb and a basin for
    // the lake at the end.
    const heightAt = (x, z) => {
        let h = (fbm(x, z, 380) - 0.47) * 64 + (fbm(x + 900, z - 400, 95, 3) - 0.5) * 9;

        // Hollows level out above the water: only the river valley and the lake hold water, so
        // the rest of the low ground must not sink below it and read as a flooded field.
        if (h < -6) {
            h = -6 + (h + 6) * 0.35;
        }

        h += 24 * around(x, z, climb.x + 18, climb.z - 10, 85);
        h = mix(h, 5, 0.75 * around(x, z, vents.x, vents.z, 46));
        h = mix(h, WATER - 2.5, 0.96 * Math.exp(-((x - riverX(z)) ** 2) / (2 * 26 * 26)));
        h = mix(h, WATER - 4, 0.95 * around(x, z, lake.x, lake.z, lake.r * 1.15));

        return h;
    };

    const segments = small ? 150 : 260;
    const groundGeometry = new PlaneGeometry(SIZE, SIZE, segments, segments);
    groundGeometry.rotateX(-Math.PI / 2);

    const vertices = groundGeometry.attributes.position;

    for (let i = 0; i < vertices.count; i++) {
        vertices.setY(i, heightAt(vertices.getX(i), vertices.getZ(i)));
    }

    groundGeometry.computeVertexNormals();
    scene.add(new Mesh(groundGeometry, meadowMaterial({ fog: FOG, near: NEAR, far: FAR, water: WATER, sun: sunDirection })));

    // ---------- water

    const water = waterMaterial({ fog: FOG, near: NEAR, far: FAR });
    const riverLine = [];

    for (let z = -SIZE / 2; z <= SIZE / 2; z += 6) {
        riverLine.push(new Vector3(riverX(z), 0, z));
    }

    // Wider than the river: the banks decide where it shows, the way ground decides for water.
    scene.add(new Mesh(ribbon(riverLine, 30, () => WATER), water));

    const pond = new Mesh(new CircleGeometry(lake.r * 1.45, 48), water);
    pond.rotation.x = -Math.PI / 2;
    pond.position.set(lake.x, WATER + 0.02, lake.z);
    scene.add(pond);

    // ---------- the road, and the line of what has been driven

    const spaced = draft.getSpacedPoints(small ? 600 : 900);
    const centre = spaced.map((p) => new Vector3(p.x, heightAt(p.x, p.z) + 0.3, p.z));
    const route = new CatmullRomCurve3(centre);

    scene.add(new Mesh(ribbon(spaced, 4.6, (x, z) => heightAt(x, z) + 0.2), roadMaterial({ fog: FOG, near: NEAR, far: FAR })));

    const trackMaterial = new ShaderMaterial({
        uniforms: { uProgress: { value: 0 }, uDone: { value: new Color('#F26A1B') }, uAhead: { value: new Color('#E6DDCB') } },
        transparent: true,
        depthWrite: false,
        vertexShader: 'varying vec2 vUv; void main() { vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
        fragmentShader: `
            uniform float uProgress;
            uniform vec3 uDone, uAhead;
            varying vec2 vUv;
            void main() {
                float done = step(vUv.x, uProgress);
                // Solid behind, dashed ahead: what has been driven is the part that is certain.
                float dash = step(0.5, fract(vUv.x * 160.0));
                gl_FragColor = vec4(mix(uAhead, uDone, done), mix(0.45 * dash, 1.0, done));
            }`,
    });
    scene.add(new Mesh(new TubeGeometry(route, small ? 600 : 1000, 0.32, 5, false), trackMaterial));

    const roadSamples = centre.filter((_, i) => i % 8 === 0);
    const nearRoad = (x, z, reach) => roadSamples.some((p) => (p.x - x) ** 2 + (p.z - z) ** 2 < reach * reach);

    // ---------- woods, in patches, the way hill country grows them

    const budget = small ? 650 : 1500;
    const treeGeometry = new ConeGeometry(1.7, 6.2, 7);
    treeGeometry.translate(0, 3.1, 0);

    const trees = new InstancedMesh(treeGeometry, new MeshLambertMaterial({ color: 0xffffff }), budget);
    const shades = ['#22301e', '#2b3a24', '#1c261a', '#34422a'].map((hex) => new Color(hex));
    const next = random(99);
    const dummy = new Object3D();
    let planted = 0;

    for (let attempt = 0; attempt < budget * 14 && planted < budget; attempt++) {
        const x = (next() - 0.5) * (SIZE - 40);
        const z = (next() - 0.5) * (SIZE - 40);

        if (fbm(x - 300, z + 700, 150, 3) < 0.53) {
            continue;
        }

        const h = heightAt(x, z);
        const slope = Math.abs(heightAt(x + 2, z) - h) + Math.abs(heightAt(x, z + 2) - h);

        if (h < WATER + 1.5 || slope > 2.2 || around(x, z, vents.x, vents.z, 46) > 0.3 || nearRoad(x, z, 9)) {
            continue;
        }

        const scale = 0.7 + next() * 0.8;
        dummy.position.set(x, h - 0.3, z);
        dummy.rotation.set(0, next() * Math.PI, 0);
        dummy.scale.set(scale, scale * (0.85 + next() * 0.4), scale);
        dummy.updateMatrix();
        trees.setMatrixAt(planted, dummy.matrix);
        trees.setColorAt(planted, shades[Math.floor(next() * shades.length)]);
        planted++;
    }

    trees.count = planted;
    trees.instanceMatrix.needsUpdate = true;

    if (trees.instanceColor) {
        trees.instanceColor.needsUpdate = true;
    }

    scene.add(trees);

    // ---------- the mud volcanoes: low grey cones with a dark mouth, on bare ground

    const clay = new MeshLambertMaterial({ color: 0x8a847a });
    const mouth = new MeshBasicMaterial({ color: 0x3e3a35 });
    const scatter = random(5);

    for (let i = 0; i < 9; i++) {
        const angle = scatter() * Math.PI * 2;
        const reach = 10 + scatter() * 28;
        const x = vents.x + Math.cos(angle) * reach;
        const z = vents.z + Math.sin(angle) * reach;
        const radius = 2.5 + scatter() * 4.5;
        const height = radius * (0.35 + scatter() * 0.3);

        if (nearRoad(x, z, radius + 3)) {
            continue;
        }

        const y = heightAt(x, z);
        const cone = new Mesh(new CylinderGeometry(radius * 0.26, radius, height, 20), clay);
        cone.position.set(x, y + height / 2 - 0.2, z);

        const crater = new Mesh(new CircleGeometry(radius * 0.22, 14), mouth);
        crater.rotation.x = -Math.PI / 2;
        crater.position.set(x, y + height - 0.18, z);

        scene.add(cone, crater);
    }

    // ---------- checkpoints: a pole and a flag beside the road, orange once passed

    const poleMaterial = new MeshBasicMaterial({ color: 0xd8d0bf });
    const flagAhead = new MeshBasicMaterial({ color: 0xe6ddcb, side: DoubleSide });
    const flagDone = new MeshBasicMaterial({ color: 0xf26a1b, side: DoubleSide });

    const waypoints = marks.map((t, index) => {
        const point = route.getPointAt(t);
        const tangent = route.getTangentAt(t);
        const side = new Vector3(-tangent.z, 0, tangent.x).normalize();
        const foot = point.clone().addScaledVector(side, 4.6);
        foot.y = heightAt(foot.x, foot.z);

        const mast = new Mesh(new CylinderGeometry(0.16, 0.16, 9, 6), poleMaterial);
        mast.position.set(foot.x, foot.y + 4.5, foot.z);

        const flag = new Mesh(new PlaneGeometry(3.2, 1.9), flagAhead);
        flag.position.set(foot.x, foot.y + 8, foot.z);
        flag.rotation.y = Math.atan2(tangent.x, tangent.z);
        flag.translateX(1.6);

        scene.add(mast, flag);

        return { t, point, tangent, top: new Vector3(foot.x, foot.y + 10.5, foot.z), flag, label: labels[index] };
    });

    // ---------- the car

    const car = new Object3D();
    const body = new Mesh(new BoxGeometry(2.3, 1.1, 4.3), new MeshLambertMaterial({ color: 0xf26a1b }));
    body.position.y = 1.05;
    const cabin = new Mesh(new BoxGeometry(2, 0.9, 2.2), new MeshLambertMaterial({ color: 0x1a1b1e }));
    cabin.position.set(0, 2, -0.35);
    car.add(body, cabin);

    const halo = new Mesh(new RingGeometry(3.6, 4.4, 40), new MeshBasicMaterial({ color: 0xf26a1b, transparent: true, opacity: 0.5, side: DoubleSide, depthWrite: false }));
    halo.rotation.x = -Math.PI / 2;
    scene.add(car, halo);

    // ---------- the little map in the corner: the same route from above, drawn once

    let mapDone = null;
    let mapDot = null;
    let mapLength = 0;

    if (map) {
        const xs = centre.map((p) => p.x).concat([lake.x - lake.r, lake.x + lake.r]);
        const zs = centre.map((p) => p.z).concat([lake.z - lake.r, lake.z + lake.r]);
        const minX = Math.min(...xs) - 16;
        const maxX = Math.max(...xs) + 16;
        const minZ = Math.min(...zs) - 16;
        const maxZ = Math.max(...zs) + 16;
        const scale = Math.min(156 / (maxX - minX), 116 / (maxZ - minZ));
        const offsetX = 12 + (156 - (maxX - minX) * scale) / 2;
        const offsetZ = 12 + (116 - (maxZ - minZ) * scale) / 2;
        const mx = (x) => (offsetX + (x - minX) * scale).toFixed(1);
        const mz = (z) => (offsetZ + (z - minZ) * scale).toFixed(1);
        const line = (list) => list.map((p, i) => `${i ? 'L' : 'M'}${mx(p.x)} ${mz(p.z)}`).join('');
        const road = line(centre.filter((_, i) => i % 6 === 0));

        map.innerHTML = `<path d="${line(riverLine)}" fill="none" stroke="#3E5660" stroke-width="3" stroke-linecap="round"/>`
            + `<circle cx="${mx(lake.x)}" cy="${mz(lake.z)}" r="${(lake.r * scale).toFixed(1)}" fill="#3E5660"/>`
            + `<path d="${road}" fill="none" stroke="#3A3D43" stroke-width="2"/>`
            + `<path data-done d="${road}" fill="none" stroke="#F26A1B" stroke-width="2"/>`
            + waypoints.map(({ point }) => `<circle cx="${mx(point.x)}" cy="${mz(point.z)}" r="2.6" fill="#E6DDCB"/>`).join('')
            + '<circle data-dot r="4.5" fill="#F26A1B" stroke="#0E0F11" stroke-width="2"/>';

        mapDone = map.querySelector('[data-done]');
        mapDot = map.querySelector('[data-dot]');
        mapLength = mapDone.getTotalLength();
        mapDone.style.strokeDasharray = String(mapLength);
    }

    // ---------- where scroll puts the car

    // The pinned scroll is split into an opening view, a drive with a hold at every checkpoint,
    // and a closing view. Between two holds the car eases out of one and into the next, so it
    // slows to each stop rather than passing it.
    const HOLD = 0.075;
    const START = 0.05;
    const FINISH = 0.95;
    const count = marks.length;
    const span = count > 1 ? (marks[count - 1] - marks[0]) || 1 : 1;
    const drive = Math.max(0, FINISH - START - HOLD * count);
    const keys = [];
    let cursor = START;

    marks.forEach((t, i) => {
        keys.push({ s: cursor, t, stop: i });
        cursor += HOLD;
        keys.push({ s: cursor, t, stop: i });

        if (i < count - 1) {
            cursor += drive * ((marks[i + 1] - t) / span);
        }
    });

    const place = (p) => {
        if (! keys.length) {
            return { q: p, stop: -1, hold: 0 };
        }

        if (p <= keys[0].s) {
            return { q: keys[0].t, stop: -1, hold: 0 };
        }

        for (let k = 0; k < keys.length - 1; k++) {
            const a = keys[k];
            const b = keys[k + 1];

            if (p <= b.s) {
                const x = (p - a.s) / Math.max(1e-6, b.s - a.s);

                return a.stop === b.stop
                    ? { q: a.t, stop: a.stop, hold: x }
                    : { q: a.t + (b.t - a.t) * smooth(x), stop: -1, hold: 0 };
            }
        }

        return { q: keys[keys.length - 1].t, stop: -1, hold: 0 };
    };

    const ease = (a, b, x) => smooth(Math.min(1, Math.max(0, (x - a) / (b - a))));
    const overview = { position: new Vector3(-120, 210, 380), target: new Vector3(-30, 0, 40) };
    const finale = { position: new Vector3(lake.x + 120, 120, lake.z + 170), target: new Vector3((lake.x + climb.x) / 2, 0, (lake.z + climb.z) / 2) };

    // Behind the car and a little to one side, looking a stretch ahead down the road.
    const chase = (q) => {
        const point = route.getPointAt(q);
        const tangent = route.getTangentAt(q);
        const side = new Vector3(-tangent.z, 0, tangent.x).normalize();

        return {
            position: point.clone().addScaledVector(tangent, -30).add(new Vector3(0, 14, 0)).addScaledVector(side, 9),
            target: route.getPointAt(Math.min(1, q + 0.03)).clone().add(new Vector3(0, 2, 0)),
        };
    };

    // At a checkpoint the camera swings slowly round it while the car stands.
    const circle = (index, hold) => {
        const { point, tangent } = waypoints[index];
        const angle = Math.atan2(tangent.x, tangent.z) + Math.PI + 0.5 - hold * 1.2;

        return {
            position: new Vector3(point.x + Math.sin(angle) * 32, point.y + 16, point.z + Math.cos(angle) * 32),
            target: point.clone().add(new Vector3(0, 3, 0)),
        };
    };

    const eye = overview.position.clone();
    const look = overview.target.clone();
    let target = reduce ? 1 : 0;
    let progress = target;
    let reachedStop = -2;
    let shownStop = -2;
    let time = 0;

    fitTo(canvas, gl, camera);

    const settle = reduce || snap;

    const frame = (dt) => {
        time += dt;
        progress += (target - progress) * (settle ? 1 : Math.min(1, dt * 4.5));

        const { q, stop, hold } = place(progress);
        trackMaterial.uniforms.uProgress.value = reduce ? 1 : q;
        water.uniforms.uTime.value = time;

        const moving = chase(q);
        const wantEye = moving.position;
        const wantLook = moving.target;
        const pause = stop >= 0 ? ease(0, 0.2, hold) * (1 - ease(0.8, 1, hold)) : 0;

        if (pause > 0) {
            const still = circle(stop, hold);
            wantEye.lerp(still.position, pause);
            wantLook.lerp(still.target, pause);
        }

        const into = ease(0, START, progress);
        const out = ease(FINISH, 1, progress);
        const finalEye = overview.position.clone().lerp(wantEye, into).lerp(finale.position, out);
        const finalLook = overview.target.clone().lerp(wantLook, into).lerp(finale.target, out);
        finalEye.y = Math.max(finalEye.y, heightAt(finalEye.x, finalEye.z) + 5);

        eye.lerp(finalEye, settle ? 1 : 0.12);
        look.lerp(finalLook, settle ? 1 : 0.12);
        camera.position.copy(eye);
        camera.lookAt(look);

        const point = route.getPointAt(q);
        car.position.set(point.x, point.y - 0.3, point.z);
        // Pointed down the road, pitched with it on a climb.
        car.lookAt(car.position.clone().add(route.getTangentAt(q)));
        halo.position.set(point.x, point.y + 0.1, point.z);

        const pulse = 1 + (Math.sin(time * 3.2) * 0.5 + 0.5) * 0.5;
        halo.scale.set(pulse, pulse, pulse);

        gl.render(scene, camera);

        const width = canvas.clientWidth;
        const height = canvas.clientHeight;

        waypoints.forEach(({ t, top, flag, label }) => {
            const passed = q >= t - 0.002;
            flag.material = passed ? flagDone : flagAhead;

            if (! label) {
                return;
            }

            const projected = top.clone().project(camera);
            const onScreen = projected.z < 1 && Math.abs(projected.x) < 1.05 && Math.abs(projected.y) < 1.05;
            label.style.transform = `translate(${(projected.x * 0.5 + 0.5) * width}px, ${(-projected.y * 0.5 + 0.5) * height}px)`;
            label.classList.toggle('is-on', onScreen && progress > 0.03);
            label.classList.toggle('is-passed', passed);
        });

        let reached = 0;
        marks.forEach((t, index) => {
            if (q + 0.004 >= t) {
                reached = index;
            }
        });

        if (reached !== reachedStop) {
            reachedStop = reached;
            stops.forEach((item, index) => item.classList.toggle('is-on', index === reached || reduce));
        }

        // The panel shows only while the car stands at a stop. The card it last showed stays in
        // place as it fades out, so it never goes blank on the way.
        const holding = ! reduce && stop >= 0 && hold > 0.06 && hold < 0.96 ? stop : -1;

        if (holding !== shownStop) {
            shownStop = holding;
            panel?.classList.toggle('is-on', holding >= 0);

            if (holding >= 0) {
                details.forEach((detail, index) => detail.classList.toggle('is-on', index === holding));
            }
        }

        if (kilometres) {
            kilometres.textContent = String(Math.round(q * distance));
        }

        if (mapDone) {
            mapDone.style.strokeDashoffset = String(mapLength * (1 - q));
            const dot = mapDone.getPointAtLength(mapLength * q);
            mapDot.setAttribute('cx', dot.x);
            mapDot.setAttribute('cy', dot.y);
        }

        if (hint) {
            hint.style.opacity = progress > 0.03 ? '0' : '1';
        }
    };

    whileVisible(section, frame, { reduce, keepRunning: true });

    return (value) => {
        target = value;
    };
}
