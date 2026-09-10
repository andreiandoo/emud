/**
 * Motion for the shop.
 *
 * Loaded only on pages that carry html.storefront, and only after app.js has run, so the back
 * office and the first paint never pay for it. Every feature is switched on by a data attribute
 * in the markup, which keeps the Blade files the single place that decides what a page does.
 *
 * Nothing here is required to use the shop. Without this file the page is still complete: rails
 * scroll natively, the ticker drifts in CSS, the hero shows its gradient, and every heading is
 * already on screen.
 */
import Lenis from 'lenis';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import { mountScenes, mountSurfaceEffects, mountTopo } from './effects';

gsap.registerPlugin(ScrollTrigger, SplitText);

const html = document.documentElement;
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

let lenis = null;

function smoothScroll() {
    if (reduce) {
        return;
    }

    lenis = new Lenis({ lerp: 0.09, smoothWheel: true, anchors: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);

    // Pinned sections and opening panels change the page's length after Lenis measured it.
    // Without this the smooth scroll stops short of the footer.
    ScrollTrigger.addEventListener('refresh', () => lenis.resize());

    // Dialogs lock the page by hiding the body's overflow, or with html.st-locked. Lenis drives
    // the window itself, so it has to be told to hold still while either is true.
    let stopped = false;
    const sync = () => {
        const locked = document.body.style.overflow === 'hidden' || html.classList.contains('st-locked');

        if (locked && ! stopped) {
            lenis.stop();
            stopped = true;
        } else if (! locked && stopped) {
            lenis.start();
            stopped = false;
        }
    };
    const observer = new MutationObserver(sync);
    observer.observe(document.body, { attributes: true, attributeFilter: ['style', 'class'] });
    observer.observe(html, { attributes: true, attributeFilter: ['class'] });

    window.emudLenis = lenis;
}

function onScroll(callback) {
    if (lenis) {
        lenis.on('scroll', ({ scroll }) => callback(scroll));
    } else {
        window.addEventListener('scroll', () => callback(window.scrollY), { passive: true });
    }

    callback(window.scrollY);
}

/**
 * The bar hides while the page moves down and comes back the moment it moves up. Anywhere but
 * the very top it comes back as its main row only (is-tucked); the rows above it show again
 * once the page is back at the top.
 *
 * --st-header-visible is how much of the bar is on screen right now, for anything that sticks
 * underneath it: a filter bar, a sticky gallery.
 */
function header() {
    const bar = document.querySelector('[data-st-header]');

    if (! bar) {
        return;
    }

    const main = bar.querySelector('[data-st-header-main]');
    let tuck = 0;
    let last = window.scrollY;

    const expose = () => {
        const visible = bar.classList.contains('is-hidden')
            ? 0
            : bar.offsetHeight - (bar.classList.contains('is-tucked') ? tuck : 0);

        html.style.setProperty('--st-header-visible', `${visible}px`);
    };

    const measure = () => {
        tuck = main ? main.offsetTop : 0;
        html.style.setProperty('--st-header-h', `${bar.offsetHeight}px`);
        html.style.setProperty('--st-header-tuck', `${tuck}px`);
        expose();
    };

    const update = (y) => {
        bar.classList.toggle('is-solid', y > 24);
        // Only once the rows above have scrolled past on their own: tucking earlier would open
        // a gap between the bar and a page that has not moved far enough to fill it.
        bar.classList.toggle('is-tucked', y > Math.max(24, tuck));

        // A panel opened from the bar, or the vehicle picker opened from anywhere on the page.
        const busy = bar.dataset.open === '1' || bar.hasAttribute('data-picking');

        if (! busy && y > 240 && y > last + 4) {
            bar.classList.add('is-hidden');
        } else if (busy || y < last - 4 || y <= 240) {
            bar.classList.remove('is-hidden');
        }

        last = y;
        expose();
    };

    measure();
    new ResizeObserver(measure).observe(bar);
    onScroll(update);

    // Opening the picker from a button further down the page has to bring a hidden bar back,
    // even though nothing scrolled.
    new MutationObserver(() => update(window.scrollY))
        .observe(bar, { attributes: true, attributeFilter: ['data-open', 'data-picking'] });
}

/**
 * Rails are native horizontal scrollers with snap points, so touch, trackpad and keyboard all
 * work without this code. What it adds is the mouse: drag with momentum, the arrows, the
 * progress line and the counter.
 */
function carousels() {
    document.querySelectorAll('[data-st-carousel]:not([data-st-ready])').forEach((root) => {
        root.dataset.stReady = '1';

        const rail = root.querySelector('.st-rail');

        if (! rail) {
            return;
        }

        const bar = root.querySelector('.st-progress > i');
        const counter = root.querySelector('[data-st-counter]');
        const prev = root.querySelector('[data-st-prev]');
        const next = root.querySelector('[data-st-next]');
        const items = () => [...rail.children];
        const step = () => {
            const list = items();

            return list.length > 1 ? list[1].offsetLeft - list[0].offsetLeft : rail.clientWidth;
        };

        const update = () => {
            const max = rail.scrollWidth - rail.clientWidth;
            const progress = max > 0 ? rail.scrollLeft / max : 1;

            if (bar) {
                bar.style.transform = `scaleX(${Math.max(0.06, progress)})`;
            }

            if (prev) {
                prev.disabled = rail.scrollLeft <= 2;
            }

            if (next) {
                next.disabled = rail.scrollLeft >= max - 2;
            }

            if (counter) {
                const total = items().length;
                const visible = Math.max(1, Math.round(rail.clientWidth / step()));
                const shown = Math.min(total, Math.round(rail.scrollLeft / step()) + visible);
                counter.innerHTML = `<b>${String(shown).padStart(2, '0')}</b> / ${String(total).padStart(2, '0')}`;
            }
        };

        rail.addEventListener('scroll', update, { passive: true });
        prev?.addEventListener('click', () => rail.scrollBy({ left: -step(), behavior: 'smooth' }));
        next?.addEventListener('click', () => rail.scrollBy({ left: step(), behavior: 'smooth' }));
        new ResizeObserver(update).observe(rail);
        new MutationObserver(update).observe(rail, { childList: true });

        if (fine) {
            dragToScroll(rail, root);
        }

        update();
    });
}

function dragToScroll(rail, root) {
    let down = false;
    let moved = false;
    let startX = 0;
    let startLeft = 0;
    let lastX = 0;
    let lastT = 0;
    let velocity = 0;
    let frame = 0;

    rail.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'mouse' || event.button !== 0) {
            return;
        }

        down = true;
        moved = false;
        startX = lastX = event.clientX;
        startLeft = rail.scrollLeft;
        lastT = performance.now();
        velocity = 0;
        cancelAnimationFrame(frame);
        root.classList.add('is-grabbing');
    });

    window.addEventListener('pointermove', (event) => {
        if (! down) {
            return;
        }

        const dx = event.clientX - startX;

        if (Math.abs(dx) > 5) {
            moved = true;
        }

        rail.scrollLeft = startLeft - dx;

        const now = performance.now();
        velocity = (event.clientX - lastX) / Math.max(1, now - lastT);
        lastX = event.clientX;
        lastT = now;
    });

    window.addEventListener('pointerup', () => {
        if (! down) {
            return;
        }

        down = false;
        let speed = -velocity * 16;

        // The rail keeps gliding after release and slows down on its own; snapping comes back
        // once it has settled, so it lands on a card instead of between two.
        const glide = () => {
            speed *= 0.94;
            rail.scrollLeft += speed;

            if (Math.abs(speed) > 0.5) {
                frame = requestAnimationFrame(glide);
            } else {
                root.classList.remove('is-grabbing');
            }
        };

        frame = requestAnimationFrame(glide);
    });

    // A drag must not also count as a click on the card it ended over.
    rail.addEventListener('click', (event) => {
        if (moved) {
            event.preventDefault();
            event.stopPropagation();
            moved = false;
        }
    }, true);

    rail.addEventListener('dragstart', (event) => event.preventDefault());
}

/** A round label that follows the pointer over draggable areas. Mouse only. */
function cursor() {
    if (! fine) {
        return;
    }

    const dot = document.createElement('div');
    dot.className = 'st-cursor';
    dot.setAttribute('aria-hidden', 'true');
    dot.innerHTML = '<span></span>';
    document.body.appendChild(dot);

    const label = dot.firstElementChild;
    const xTo = gsap.quickTo(dot, 'x', { duration: 0.5, ease: 'power3' });
    const yTo = gsap.quickTo(dot, 'y', { duration: 0.5, ease: 'power3' });

    window.addEventListener('pointermove', (event) => {
        xTo(event.clientX);
        yTo(event.clientY);
    }, { passive: true });

    document.addEventListener('pointerover', (event) => {
        const zone = event.target.closest('[data-st-cursor]');
        const control = event.target.closest('button, input, select, textarea, [data-st-cursor-skip]');

        if (zone && ! control) {
            label.textContent = zone.dataset.stCursor;
            dot.classList.add('is-on');
        } else {
            dot.classList.remove('is-on');
        }
    });
}

/** The band of promises drifts on its own and speeds up while the page is being scrolled. */
function tickers() {
    if (reduce) {
        return;
    }

    document.querySelectorAll('[data-st-ticker]').forEach((band) => {
        const track = band.querySelector('.st-ticker__track');

        if (! track) {
            return;
        }

        const tween = gsap.to(track, { xPercent: -50, duration: 42, ease: 'none', repeat: -1 });

        ScrollTrigger.create({
            trigger: band,
            start: 'top bottom',
            end: 'bottom top',
            onUpdate: (self) => {
                const boost = Math.min(4, Math.abs(self.getVelocity()) / 400);
                gsap.to(tween, { timeScale: 1 + boost, duration: 0.3, overwrite: true });
                gsap.to(tween, { timeScale: 1, duration: 1.2, delay: 0.3 });
            },
        });
    });
}

/** Headings marked data-st-split rise out of their own lines when the page opens. */
function intro() {
    if (reduce) {
        return;
    }

    document.querySelectorAll('[data-st-split]').forEach((heading) => {
        const split = SplitText.create(heading, { type: 'lines', mask: 'lines' });
        gsap.from(split.lines, { yPercent: 108, duration: 1.3, ease: 'expo.out', stagger: 0.09, delay: 0.1 });
    });

    const rising = document.querySelectorAll('[data-st-rise]');

    if (rising.length > 0) {
        gsap.from(rising, { y: 22, opacity: 0, duration: 1.1, ease: 'expo.out', stagger: 0.08, delay: 0.35 });
    }
}

/**
 * A paragraph that lights up word by word as it is scrolled through. It starts dim, never
 * invisible, so it is readable before anyone scrolls.
 */
function litWords() {
    if (reduce) {
        return;
    }

    document.querySelectorAll('[data-st-words]').forEach((paragraph) => {
        const split = SplitText.create(paragraph, { type: 'words' });

        gsap.fromTo(split.words, { opacity: 0.24 }, {
            opacity: 1,
            stagger: 0.06,
            ease: 'none',
            scrollTrigger: { trigger: paragraph, start: 'top 78%', end: 'bottom 42%', scrub: true },
        });
    });
}

/** The numbers are printed final; they run up from zero once, when they come into view. */
function counters() {
    if (reduce) {
        return;
    }

    const format = (value) => String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    document.querySelectorAll('[data-st-count]').forEach((element) => {
        const target = Number(element.dataset.stCount);

        if (! Number.isFinite(target) || target <= 0) {
            return;
        }

        ScrollTrigger.create({
            trigger: element,
            start: 'top 90%',
            once: true,
            onEnter: () => {
                const state = { value: 0 };
                gsap.to(state, {
                    value: target,
                    duration: 1.8,
                    ease: 'power3.out',
                    onUpdate: () => {
                        element.textContent = format(Math.round(state.value));
                    },
                });
            },
        });
    });
}

function parallax() {
    if (reduce) {
        return;
    }

    document.querySelectorAll('[data-st-parallax]').forEach((layer) => {
        gsap.fromTo(layer, { yPercent: -6 }, {
            yPercent: 6,
            ease: 'none',
            scrollTrigger: { trigger: layer.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
        });
    });

    document.querySelectorAll('[data-st-giant]').forEach((word) => {
        gsap.fromTo(word, { yPercent: 30 }, {
            yPercent: 0,
            ease: 'none',
            scrollTrigger: { trigger: word.closest('footer') ?? word, start: 'top bottom', end: 'bottom bottom', scrub: true },
        });
    });
}

/**
 * The 3D terrain is a separate chunk that only pages with a terrain element ever download.
 * Devices that asked to save data, or have very little to spare, keep the static gradient.
 */
function terrain() {
    const hero = document.querySelector('[data-st-terrain]');
    const trail = document.querySelector('[data-st-trail]');

    if (! hero && ! trail) {
        return;
    }

    const saveData = navigator.connection?.saveData === true;
    const weak = (navigator.hardwareConcurrency ?? 8) <= 2;

    if (saveData || weak) {
        html.classList.add('st-no-gl');

        return;
    }

    import('./terrain')
        .then(({ mountHero, mountTrail }) => {
            if (hero) {
                mountHero(hero, { reduce });
            }

            if (! trail) {
                return;
            }

            const setProgress = mountTrail(trail, { reduce });

            if (setProgress && ! reduce) {
                ScrollTrigger.create({
                    trigger: trail,
                    start: 'top top',
                    // Longer than a straight drive would need: the car holds at every checkpoint.
                    end: () => `+=${Math.round(window.innerHeight * 4.5)}`,
                    pin: trail.querySelector('[data-st-trail-stage]'),
                    scrub: true,
                    anticipatePin: 1,
                    invalidateOnRefresh: true,
                    onUpdate: (self) => setProgress(self.progress),
                });
            }

            ScrollTrigger.refresh();
        })
        .catch(() => html.classList.add('st-no-gl'));
}

/** Livewire replaces parts of the page; whatever it adds gets the same treatment. */
function afterLivewireUpdates(callback) {
    const hook = () => {
        try {
            window.Livewire.hook('commit', ({ succeed }) => {
                succeed(() => requestAnimationFrame(callback));
            });
        } catch {
            // An older or newer hook API only costs the re-initialisation of added rails.
        }
    };

    if (window.Livewire) {
        hook();
    } else {
        document.addEventListener('livewire:init', hook, { once: true });
    }
}

function boot() {
    html.classList.add('st-motion');

    smoothScroll();
    header();
    carousels();
    cursor();
    tickers();
    mountScenes(document);
    mountSurfaceEffects({ reduce });
    mountTopo(document);
    terrain();

    const whenFontsAreIn = document.fonts?.ready ?? Promise.resolve();

    whenFontsAreIn.then(() => {
        intro();
        litWords();
        counters();
        parallax();
        ScrollTrigger.refresh();
    });

    afterLivewireUpdates(() => {
        carousels();
        mountScenes(document);
        ScrollTrigger.refresh();
    });
}

boot();
