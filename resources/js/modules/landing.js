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
    if (!sections.length || !links.length || !('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            links.forEach((link) => {
                const active = link.dataset.phaseLink === entry.target.dataset.phase;
                link.setAttribute('aria-current', active ? 'true' : 'false');
            });
        });
    }, { rootMargin: '-45% 0px -45% 0px' });

    sections.forEach((section) => observer.observe(section));
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

const AUTOPLAY_INTERVAL_MS = 4500;
const IDLE_AFTER_TOUCH_MS = 8000;

/**
 * Moves the rail one card at a time. It never fights the visitor: it stops while the rail is hovered,
 * focused, touched or off screen, waits after any manual input, and has a pause button.
 * With reduced motion it does not run and the pause button is hidden.
 */
function initRailAutoplay(rail) {
    const toggle = document.querySelector(`[data-rail-toggle="${rail.dataset.rail}"]`);

    if (reducedMotion()) {
        toggle?.classList.add('hidden');
        return;
    }

    let userPaused = false;
    let engaged = false;
    let onScreen = false;
    let lastInput = 0;

    const setPaused = (paused) => {
        userPaused = paused;
        toggle?.setAttribute('aria-pressed', String(paused));
        toggle?.setAttribute('aria-label', paused ? 'Putar geser otomatis' : 'Jeda geser otomatis');
        toggle?.querySelector('[data-label="pause"]')?.classList.toggle('hidden', paused);
        toggle?.querySelector('[data-label="play"]')?.classList.toggle('hidden', !paused);
    };

    const advance = () => {
        const maxLeft = rail.scrollWidth - rail.clientWidth;

        if (rail.scrollLeft >= maxLeft - 4) {
            rail.scrollTo({ left: 0, behavior: 'smooth' });
            return;
        }

        // At scroll 0 the first card sits one list padding in from the edge, so that padding counts as "already here".
        const railLeft = rail.getBoundingClientRect().left;
        const lead = parseFloat(getComputedStyle(rail.firstElementChild).paddingLeft) || 0;
        const next = [...rail.querySelectorAll(':scope > ul > li')].find((card) => card.getBoundingClientRect().left - railLeft > lead + 8);
        if (!next) return;

        rail.scrollTo({
            left: Math.min(maxLeft, rail.scrollLeft + next.getBoundingClientRect().left - railLeft),
            behavior: 'smooth',
        });
    };

    const canRun = () => !userPaused && !engaged && onScreen && !document.hidden && Date.now() - lastInput > IDLE_AFTER_TOUCH_MS;

    setInterval(() => {
        if (canRun()) advance();
    }, AUTOPLAY_INTERVAL_MS);

    const touch = () => { lastInput = Date.now(); };

    ['pointerdown', 'wheel', 'keydown', 'touchstart'].forEach((type) => rail.addEventListener(type, touch, { passive: true }));
    rail.addEventListener('pointerenter', () => { engaged = true; });
    rail.addEventListener('pointerleave', () => { engaged = false; });
    rail.addEventListener('focusin', () => { engaged = true; });
    rail.addEventListener('focusout', () => { engaged = false; });

    toggle?.addEventListener('click', () => setPaused(!userPaused));

    new IntersectionObserver((entries) => {
        onScreen = entries.some((entry) => entry.isIntersecting);
    }, { threshold: 0.4 }).observe(rail);
}

export function initLanding() {
    initParallax();
    initPhaseMarker();
    initRail();
}
