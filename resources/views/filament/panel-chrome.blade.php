{{--
    Panel chrome after the owner's references (2026-10-12): sidebar and topbar share one plain surface
    (white, forest-950 in dark) above a stone-50 content area, and forest is the only accent.
    Ember is left to status colors, so nothing in the frame competes with the content.
    The sidebar still scrolls by wheel, touch and keyboard; only the visible bar is hidden.
--}}
<style>
    /* Plus Jakarta Sans runs wider than Filament's default face, so the panel scales text one step down.
       Tap targets below are set in px so they stay at 44 px. */
    html {
        font-size: 93.75%;
    }

    /* Filament focuses the main region after navigation for screen readers; it is not a control,
       so the browser ring around the whole page is suppressed there only. */
    #fi-main-content:focus:not(:focus-visible) {
        outline: none;
    }

    .fi-sidebar,
    .fi-topbar {
        --hh-chrome-bg: #ffffff;
        --hh-chrome-line: rgb(31 42 34 / 0.08);
        --hh-chrome-text: var(--hh-ink);
        --hh-chrome-muted: var(--hh-ink-soft);
        --hh-chrome-hover: var(--hh-forest-50);
        --hh-chrome-active-bg: var(--hh-forest-700);
        --hh-chrome-active-text: #ffffff;
        --hh-chrome-focus: var(--hh-forest-600);
    }

    .dark .fi-sidebar,
    .dark .fi-topbar {
        --hh-chrome-bg: var(--gray-950);
        --hh-chrome-line: rgb(223 233 225 / 0.08);
        --hh-chrome-text: var(--hh-forest-100);
        --hh-chrome-muted: var(--hh-forest-300);
        --hh-chrome-hover: var(--hh-forest-900);
        --hh-chrome-active-bg: var(--hh-forest-700);
        --hh-chrome-focus: var(--hh-forest-300);
    }

    .fi-sidebar,
    .fi-sidebar .fi-sidebar-header,
    .fi-topbar {
        background-color: var(--hh-chrome-bg);
    }

    .fi-sidebar {
        border-inline-end: 1px solid var(--hh-chrome-line);
    }

    .fi-sidebar .fi-sidebar-header,
    .fi-topbar {
        box-shadow: none;
        --tw-ring-shadow: 0 0 #0000;
        border-block-end: 1px solid var(--hh-chrome-line);
    }

    .fi-topbar .fi-icon-btn {
        color: var(--hh-chrome-muted);
    }

    .fi-sidebar .fi-sidebar-nav {
        scrollbar-width: none;
    }

    .fi-sidebar .fi-sidebar-nav::-webkit-scrollbar {
        display: none;
    }

    .fi-sidebar .fi-sidebar-nav-groups {
        row-gap: 1rem;
    }

    .fi-sidebar .fi-sidebar-group-label {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--hh-chrome-muted);
    }

    .fi-sidebar .fi-sidebar-group-btn,
    .fi-sidebar .fi-sidebar-item-btn {
        min-height: 44px;
        border-radius: 0.75rem;
    }

    .fi-sidebar .fi-sidebar-item-btn {
        transition: background-color 150ms ease;
    }

    .fi-sidebar .fi-sidebar-item-label {
        color: var(--hh-chrome-text);
        font-weight: 500;
    }

    .fi-sidebar .fi-sidebar-item-icon {
        color: var(--hh-chrome-muted);
    }

    .fi-sidebar .fi-sidebar-item-btn:hover,
    .fi-sidebar .fi-sidebar-group-btn:hover {
        background-color: var(--hh-chrome-hover);
    }

    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--hh-chrome-active-bg);
    }

    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon {
        color: var(--hh-chrome-active-text);
        font-weight: 600;
    }

    .fi-sidebar .fi-sidebar-item-btn:focus-visible,
    .fi-sidebar .fi-sidebar-group-btn:focus-visible {
        outline: 2px solid var(--hh-chrome-focus);
        outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-sidebar .fi-sidebar-item-btn {
            transition: none;
        }
    }
</style>
