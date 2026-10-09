const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Hero photo drifts slower than the page. Transform only, so layout never shifts. */
function initParallax() {
    const layers = [...document.querySelectorAll('[data-parallax]')];
    if (!layers.length || reducedMotion()) return;

    let queued = false;

    const update = () => {
        queued = false;
        layers.forEach((layer) => {
            const rect = layer.parentElement.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > window.innerHeight) return;
            layer.style.transform = `translate3d(0, ${rect.top * Number(layer.dataset.parallax)}px, 0)`;
        });
    };

    window.addEventListener('scroll', () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(update);
    }, { passive: true });
    update();
}

/** Marks which phase (Sore, Petang, Malam, Subuh) fills the viewport in the phase marker. */
function initPhaseMarker() {
    const sections = [...document.querySelectorAll('[data-phase]')];
    const links = [...document.querySelectorAll('[data-phase-link]')];
    if (!sections.length || !links.length) return;

    // The active phase is the last one whose top has passed the middle of the screen, so a jump
    // straight to the footer (or any anchor) still lands on the right phase instead of a stale one.
    let scheduled = false;
    const update = () => {
        scheduled = false;
        const middle = window.innerHeight / 2;
        const current = sections.filter((section) => section.getBoundingClientRect().top <= middle).pop() ?? sections[0];
        links.forEach((link) => {
            link.setAttribute('aria-current', link.dataset.phaseLink === current.dataset.phase ? 'true' : 'false');
        });
    };

    window.addEventListener('scroll', () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(update);
    }, { passive: true });
    update();
}

/** Arrow buttons for the tent rail. The rail itself stays natively scrollable and keyboard reachable. */
function initRail() {
    document.querySelectorAll('[data-rail]').forEach((rail) => {
        const scrollRail = (direction) => rail.scrollBy({
            left: direction * rail.clientWidth * 0.8,
            behavior: reducedMotion() ? 'auto' : 'smooth',
        });

        document.querySelectorAll(`[data-rail-step="${rail.dataset.rail}"]`).forEach((button) => {
            button.addEventListener('click', () => {
                rail.dispatchEvent(new Event('pointerdown'));
                scrollRail(Number(button.dataset.dir));
            });
        });

        initRailAutoplay(rail);
    });
}

const DRIFT_PIXELS_PER_SECOND = 28;
const IDLE_AFTER_TOUCH_MS = 4000;

/**
 * Copies the cards once, hidden from assistive tech and the tab order, so the rail can drift forever:
 * when the scroll passes the end of the real cards it jumps back by exactly their width, which looks
 * like nothing happened because the copies sit where the originals were.
 */
function cloneCardsForLoop(rail) {
    const list = rail.querySelector(':scope > ul');
    const cards = [...list.children];

    cards.forEach((card) => {
        const copy = card.cloneNode(true);
        copy.setAttribute('aria-hidden', 'true');
        copy.setAttribute('inert', '');
        copy.dataset.railClone = '';
        copy.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'));
        list.appendChild(copy);
    });

    return () => list.children[cards.length].offsetLeft - cards[0].offsetLeft;
}

/**
 * Moves the rail left at a slow constant speed, like an escalator, and loops without an end.
 * It never fights the visitor: it stops while the rail is focused, touched or off screen,
 * waits after any manual input, and has a pause button. With reduced motion it does not run.
 */
function initRailAutoplay(rail) {
    const toggle = document.querySelector(`[data-rail-toggle="${rail.dataset.rail}"]`);

    if (reducedMotion()) {
        toggle?.classList.add('hidden');
        return;
    }

    const loopWidth = cloneCardsForLoop(rail);

    let userPaused = false;
    let engaged = false;
    let onScreen = false;
    let lastInput = 0;
    let position = rail.scrollLeft;
    let previousFrame = null;

    const setPaused = (paused) => {
        userPaused = paused;
        toggle?.setAttribute('aria-pressed', String(paused));
        toggle?.setAttribute('aria-label', paused ? 'Putar geser otomatis' : 'Jeda geser otomatis');
        toggle?.querySelector('[data-label="pause"]')?.classList.toggle('hidden', paused);
        toggle?.querySelector('[data-label="play"]')?.classList.toggle('hidden', !paused);
    };

    const canRun = () => !userPaused && !engaged && onScreen && !document.hidden && Date.now() - lastInput > IDLE_AFTER_TOUCH_MS;

    const wrap = () => {
        const width = loopWidth();
        if (rail.scrollLeft >= width) rail.scrollLeft -= width;
    };

    // Scroll snapping would pull the rail back to a card edge on every frame, so it is off while drifting.
    // It comes back only for manual dragging; turning it on during any other pause would make the cards jump.
    const frame = (time) => {
        const running = canRun();
        if (running) rail.classList.add('is-drifting');

        if (running && previousFrame !== null) {
            position += DRIFT_PIXELS_PER_SECOND * Math.min(time - previousFrame, 100) / 1000;
            const width = loopWidth();
            if (position >= width) position -= width;
            rail.scrollLeft = position;
        } else {
            position = rail.scrollLeft;
        }

        previousFrame = time;
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);

    const touch = () => {
        lastInput = Date.now();
        rail.classList.remove('is-drifting');
    };

    ['pointerdown', 'wheel', 'keydown', 'touchstart'].forEach((type) => rail.addEventListener(type, touch, { passive: true }));
    rail.addEventListener('scroll', wrap, { passive: true });
    // Hovering does not stop the drift (owner request); keyboard focus still does, so a focused card never slides away.
    rail.addEventListener('focusin', () => { engaged = true; });
    rail.addEventListener('focusout', () => { engaged = false; });

    toggle?.addEventListener('click', () => setPaused(!userPaused));

    new IntersectionObserver((entries) => {
        onScreen = entries.some((entry) => entry.isIntersecting);
    }, { threshold: 0.2 }).observe(rail);
}

export function initLanding() {
    initParallax();
    initPhaseMarker();
    initRail();
}
