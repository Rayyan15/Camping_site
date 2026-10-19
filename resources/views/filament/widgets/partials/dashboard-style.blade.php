@once
    {{--
        Dashboard after the owner's reference: header card, four equal stat cards, a compact strip, then charts.
        Surfaces come from Filament's gray scale (the forest-tinted neutral), forest is the only accent,
        and the warning tint appears only on a card whose count needs someone to act.
    --}}
    <style>
        .hh-dash { display: grid; gap: 1.25rem; }
        .hh-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 1rem;
            padding: 1.25rem;
        }
        .dark .hh-card { background: var(--gray-900); border-color: var(--gray-800); }

        .hh-muted { color: var(--gray-500); }
        .dark .hh-muted { color: var(--gray-400); }
        .hh-small { font-size: 0.8125rem; }
        .hh-xsmall { font-size: 0.75rem; }

        .hh-tile {
            display: grid; place-items: center; flex-shrink: 0;
            width: 2.5rem; height: 2.5rem; border-radius: 0.75rem;
            background: var(--primary-50); color: var(--primary-700);
        }
        .dark .hh-tile { background: var(--gray-800); color: var(--primary-300); }
        .hh-tile-lg { width: 2.75rem; height: 2.75rem; }
        .hh-tile-icon { width: 1.375rem; height: 1.375rem; }

        .hh-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1rem; }
        .hh-head-text { flex: 1 1 12rem; min-width: 0; }
        .hh-head-title { font-size: 1.125rem; font-weight: 600; line-height: 1.3; color: var(--gray-950); }
        .dark .hh-head-title { color: #fff; }
        .hh-head-text .hh-muted { font-size: 0.875rem; }

        .hh-cards { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 640px) { .hh-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .hh-cards { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

        .hh-stat {
            display: flex; align-items: flex-start; gap: 0.875rem;
            text-decoration: none;
            transition: border-color 150ms ease;
        }
        .hh-stat:hover { border-color: var(--primary-300); }
        .dark .hh-stat:hover { border-color: var(--primary-700); }
        .hh-stat:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .hh-stat-text { display: grid; gap: 0.125rem; min-width: 0; }
        .hh-stat-label { font-size: 0.875rem; color: var(--gray-600); }
        .dark .hh-stat-label { color: var(--gray-300); }
        .hh-stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; color: var(--gray-950); font-variant-numeric: tabular-nums; }
        .dark .hh-stat-value { color: #fff; }

        .hh-stat.is-alert .hh-tile { background: var(--warning-50); color: var(--warning-700); }
        .dark .hh-stat.is-alert .hh-tile { background: var(--gray-800); color: var(--warning-400); }
        .hh-stat.is-alert .hh-stat-value { color: var(--warning-700); }
        .dark .hh-stat.is-alert .hh-stat-value { color: var(--warning-400); }

        .hh-strip { display: grid; gap: 0.75rem; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 1rem; }
        @media (min-width: 768px) { .hh-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .hh-mini {
            display: flex; align-items: center; gap: 0.625rem; min-width: 0;
            padding: 0.75rem; border-radius: 0.75rem; background: var(--gray-50);
        }
        .dark .hh-mini { background: var(--gray-800); }
        .hh-mini-text { min-width: 0; }
        .hh-mini-icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; color: var(--primary-600); }
        .dark .hh-mini-icon { color: var(--primary-300); }
        .hh-mini-value { font-size: 0.9375rem; font-weight: 600; color: var(--gray-950); font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dark .hh-mini-value { color: #fff; }

        @media (prefers-reduced-motion: reduce) { .hh-stat { transition: none; } }
    </style>
@endonce
