/**
 * An overlay scrollbar for the shop.
 *
 * The native one cannot do what is wanted here. On Windows and Linux a classic scrollbar takes
 * layout space whether or not it is styled, so a page that grows one shifts sideways the moment
 * it does — and `scrollbar-color: transparent` hides the bar without giving that space back. The
 * only way to a bar that floats over the content is to remove the native one and draw it.
 *
 * That is a real accessibility trade, so it is paid back rather than ignored: the thumb is
 * draggable exactly like a native one, it is a full-height hit target rather than a decoration,
 * and it stays put for anyone whose system asks for reduced motion instead of fading away.
 *
 * Storefront only. The back office keeps its native scrollbars, where operators work in long
 * tables all day and a familiar control beats a prettier one.
 */

const HIDE_AFTER_MS = 900;
const MIN_THUMB_PX = 44;

export default function mountScrollIndicator() {
    if (!document.documentElement.classList.contains('storefront')) {
        return;
    }

    let bar = null;
    let hideTimer = null;
    let frame = null;
    let dragging = false;
    let dragStartY = 0;
    let dragStartScroll = 0;

    const scroller = () => document.scrollingElement || document.documentElement;

    function ensureBar() {
        if (bar && bar.isConnected) {
            return bar;
        }

        bar = document.createElement('div');
        bar.className = 'scroll-indicator';
        // Hidden from assistive tech: it duplicates a scroll position the browser already
        // exposes, and announcing it again would be noise.
        bar.setAttribute('aria-hidden', 'true');
        bar.addEventListener('pointerdown', startDrag);
        document.body.appendChild(bar);

        return bar;
    }

    function measure() {
        const element = scroller();
        const viewport = element.clientHeight;
        const content = element.scrollHeight;
        const scrollable = content - viewport;

        return { element, viewport, content, scrollable };
    }

    function draw() {
        frame = null;

        const { element, viewport, scrollable } = measure();
        const node = ensureBar();

        // A page that does not scroll has no scrollbar, and neither does one sitting behind an
        // open dialog — the finder and the variant chooser lock the body while they are up.
        if (scrollable < 4 || document.body.style.overflow === 'hidden') {
            node.classList.add('is-off');
            node.classList.remove('is-visible');

            return;
        }

        node.classList.remove('is-off');

        const thumb = Math.max(MIN_THUMB_PX, (viewport / element.scrollHeight) * viewport);
        const track = viewport - thumb;
        const progress = scrollable > 0 ? element.scrollTop / scrollable : 0;

        node.style.height = `${Math.round(thumb)}px`;
        node.style.transform = `translateY(${Math.round(progress * track)}px)`;
    }

    function schedule() {
        if (frame === null) {
            // One read per frame. scrollHeight forces layout, and asking for it on every one of
            // the dozens of scroll events a single wheel gesture fires is how a smooth page
            // starts to stutter.
            frame = requestAnimationFrame(draw);
        }
    }

    function reveal() {
        ensureBar().classList.add('is-visible');

        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            if (!dragging && !bar?.matches(':hover')) {
                bar?.classList.remove('is-visible');
            }
        }, HIDE_AFTER_MS);
    }

    function onScroll() {
        schedule();
        reveal();
    }

    function startDrag(event) {
        const { scrollable, viewport, element } = measure();

        if (scrollable < 4) {
            return;
        }

        dragging = true;
        dragStartY = event.clientY;
        dragStartScroll = element.scrollTop;

        // Captured so the drag survives the pointer leaving a six-pixel-wide target, which it
        // does immediately on any real gesture.
        bar.setPointerCapture(event.pointerId);
        bar.classList.add('is-dragging', 'is-visible');
        event.preventDefault();

        const thumb = Math.max(MIN_THUMB_PX, (viewport / element.scrollHeight) * viewport);
        const track = Math.max(1, viewport - thumb);

        function onMove(moveEvent) {
            const delta = moveEvent.clientY - dragStartY;
            element.scrollTop = dragStartScroll + (delta / track) * scrollable;
        }

        function onUp(upEvent) {
            dragging = false;
            bar.releasePointerCapture(upEvent.pointerId);
            bar.classList.remove('is-dragging');
            bar.removeEventListener('pointermove', onMove);
            bar.removeEventListener('pointerup', onUp);
            bar.removeEventListener('pointercancel', onUp);
            reveal();
        }

        bar.addEventListener('pointermove', onMove);
        bar.addEventListener('pointerup', onUp);
        bar.addEventListener('pointercancel', onUp);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });

    // The page can grow without anyone scrolling — a filter applied, images arriving, a Livewire
    // re-render — and a thumb sized for the old height would then be wrong the next time it
    // appears.
    if ('ResizeObserver' in window) {
        new ResizeObserver(schedule).observe(document.body);
    }

    document.addEventListener('livewire:navigated', () => {
        ensureBar();
        schedule();
    });

    schedule();
}
