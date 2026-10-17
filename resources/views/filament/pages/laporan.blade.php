<x-filament-panels::page>
    @include('filament.widgets.partials.dashboard-style')

    {{--
        Client asked for pill filters after the 17 Oct demo. Report type is the larger first row because it
        changes the whole table; the period row is smaller and shows the chosen range in words beside it.
        Colour pass after the user found the first version plain (2026-10-17, user direction overrides the
        earlier one-accent rule for this page): the filter card sits on cream, summary tiles carry the
        palette by meaning (forest for counts, ember for money, sand for percentages, the headline number
        solid forest), and status cells use the same tones as the panel badges.
    --}}
    <style>
        .lap { display: grid; gap: 1.25rem; }
        .lap-filters { display: grid; gap: 1rem; background: var(--hh-cream); border-color: var(--hh-sand); }
        .dark .lap-filters { background: var(--primary-900); border-color: var(--primary-800); }

        .lap-pill {
            display: inline-flex; align-items: center; min-height: 44px;
            padding: 0 1rem; border-radius: 999px;
            border: 1px solid var(--hh-sand); background: #fff;
            font-size: 0.875rem; font-weight: 600; color: var(--primary-800);
            cursor: pointer; transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
        }
        .lap-pill:hover { border-color: var(--primary-400); color: var(--primary-800); }
        .lap-pill:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .lap-pill[aria-pressed="true"] { background: var(--primary-700); border-color: var(--primary-700); color: #fff; }
        .dark .lap-pill { background: var(--primary-950); border-color: var(--primary-800); color: var(--primary-100); }
        .dark .lap-pill:hover { border-color: var(--primary-500); color: #fff; }
        .dark .lap-pill[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
        .lap-pill-sm { padding: 0 0.875rem; font-size: 0.8125rem; }


        .lap-table-card { padding: 0; overflow: hidden; }
        .lap-table-wrap { overflow-x: auto; }
        .lap-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .lap-table th {
            text-align: left; padding: 0.75rem 1rem; white-space: nowrap;
            font-size: 0.8125rem; font-weight: 600; color: var(--primary-800);
            background: var(--primary-100); border-bottom: 1px solid var(--primary-200);
        }
        .lap-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-950); }
        .lap-table tbody tr:last-child td { border-bottom: 0; }
        .lap-table tbody tr:hover td { background: var(--gray-50); }
        .dark .lap-table th { background: var(--primary-800); color: var(--primary-100); border-color: var(--primary-700); }
        .dark .lap-table td { color: #fff; border-color: var(--gray-800); }
        .dark .lap-table tbody tr:hover td { background: var(--gray-800); }
        .lap-num { text-align: right !important; font-variant-numeric: tabular-nums; }

        .lap-badge { display: inline-flex; align-items: center; padding: 0.125rem 0.625rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .lap-badge-success { background: var(--primary-100); color: var(--primary-800); }
        .lap-badge-warning { background: var(--hh-sand); color: var(--hh-ink); }
        .lap-badge-danger { background: var(--hh-ember-soft); color: var(--hh-ember-dark); }
        .lap-badge-info { background: var(--hh-cream-deep); color: var(--hh-ink); }
        .lap-badge-gray { background: var(--gray-100); color: var(--gray-700); }
        .dark .lap-badge-success { background: var(--primary-800); color: var(--primary-100); }
        .dark .lap-badge-warning { background: var(--primary-800); color: var(--hh-sand); }
        .dark .lap-badge-danger { background: var(--primary-900); color: var(--hh-ember-light); }
        .dark .lap-badge-info, .dark .lap-badge-gray { background: var(--gray-800); color: var(--gray-200); }

        .lap-stats { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); }
        .lap-stat { display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border-radius: 1rem; border: 1px solid var(--gray-200); background: #fff; min-width: 0; }
        .dark .lap-stat { background: var(--gray-900); border-color: var(--gray-800); }
        .lap-stat-tile { display: grid; place-items: center; flex-shrink: 0; width: 2.5rem; height: 2.5rem; border-radius: 0.75rem; }
        .lap-stat-tile svg { width: 1.25rem; height: 1.25rem; }
        .lap-stat-value { font-family: inherit; font-size: 1.375rem; font-weight: 700; line-height: 1.2; color: var(--gray-950); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .dark .lap-stat-value { color: #fff; }
        .lap-stat-label { font-size: 0.8125rem; color: var(--hh-ink-soft); }
        .dark .lap-stat-label { color: var(--gray-300); }
        .lap-tone-count .lap-stat-tile { background: var(--primary-50); color: var(--primary-700); }
        .lap-tone-money .lap-stat-tile { background: var(--hh-ember-soft); color: var(--hh-ember-dark); }
        .lap-tone-percent .lap-stat-tile { background: var(--hh-cream); color: var(--hh-sand-dark); }
        .lap-tone-success .lap-stat-tile { background: var(--primary-100); color: var(--primary-800); }
        .lap-tone-warning .lap-stat-tile { background: var(--hh-sand); color: var(--hh-ink); }
        .lap-tone-danger .lap-stat-tile { background: var(--hh-ember-soft); color: var(--hh-ember-dark); }
        .lap-tone-info .lap-stat-tile, .lap-tone-gray .lap-stat-tile { background: var(--hh-cream-deep); color: var(--hh-ink); }
        .dark .lap-tone-warning .lap-stat-tile { color: var(--hh-sand); }
        .dark .lap-tone-danger .lap-stat-tile { color: var(--hh-ember-light); }
        .dark .lap-stat-tile { background: var(--primary-800); color: var(--primary-200); }
        .dark .lap-tone-money .lap-stat-tile { color: var(--hh-ember-light); }
        .dark .lap-tone-percent .lap-stat-tile { color: var(--hh-sand); }
        /* The first summary line is the headline number of every report, so it is the one solid forest tile. */
        .lap-stat.is-headline { background: var(--primary-700); border-color: var(--primary-700); }
        .lap-stat.is-headline .lap-stat-value { color: #fff; }
        .lap-stat.is-headline .lap-stat-label { color: var(--primary-100); }
        .lap-stat.is-headline .lap-stat-tile { background: var(--primary-800); color: #fff; }
        .dark .lap-stat.is-headline { background: var(--primary-600); border-color: var(--primary-600); }
        .dark .lap-stat.is-headline .lap-stat-tile { background: var(--primary-700); }

        .lap-state { display: grid; justify-items: start; gap: 0.75rem; padding: 2rem 1.25rem; }
        .lap-state-title { font-weight: 600; color: var(--gray-950); }
        .dark .lap-state-title { color: #fff; }
        .lap-error { color: var(--danger-700); }
        .dark .lap-error { color: var(--danger-400); }
        .lap-note { max-width: 70ch; font-size: 0.8125rem; line-height: 1.5; }
        .lap-loading { font-size: 0.8125rem; }


        /* Report picker cards */
        .lap-types { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(11.5rem, 1fr)); }
        .lap-type {
            display: grid; align-content: start; justify-items: start; gap: 0.375rem;
            min-height: 44px; padding: 1rem; text-align: left; cursor: pointer;
            border: 1px solid var(--hh-sand); border-radius: 1rem; background: #fff;
            transition: border-color 150ms ease, background-color 150ms ease;
        }
        .lap-type:hover { border-color: var(--primary-400); }
        .lap-type:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .lap-type-tile { margin-bottom: 0.25rem; display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: 0.75rem; background: var(--primary-50); color: var(--primary-700); }
        .lap-type-tile svg { width: 1.25rem; height: 1.25rem; }
        .lap-type-name { font-size: 0.9375rem; font-weight: 600; color: var(--primary-900); line-height: 1.3; }
        .lap-type-question { font-size: 0.75rem; line-height: 1.4; color: var(--hh-ink-soft); }
        .lap-type[aria-pressed="true"] { background: var(--primary-700); border-color: var(--primary-700); }
        .lap-type[aria-pressed="true"] .lap-type-tile { background: var(--primary-800); color: #fff; }
        .lap-type[aria-pressed="true"] .lap-type-name { color: #fff; }
        .lap-type[aria-pressed="true"] .lap-type-question { color: var(--primary-100); }
        .dark .lap-type { background: var(--primary-950); border-color: var(--primary-800); }
        .dark .lap-type-tile { background: var(--primary-800); color: var(--primary-200); }
        .dark .lap-type-name { color: #fff; }
        .dark .lap-type-question { color: var(--primary-300); }
        .dark .lap-type[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); }
        .dark .lap-type[aria-pressed="true"] .lap-type-tile { background: var(--primary-700); }

        /* Period: month shortcuts in one segmented track, the two dates always beside it */
        .lap-periodbar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.75rem 1rem; }
        .lap-range-inputs { flex: 1 1 20rem; max-width: 28rem; }
        .lap-seg { display: inline-flex; flex-wrap: wrap; gap: 0.25rem; padding: 0.25rem; border-radius: 999px; background: var(--hh-cream-deep); }
        .dark .lap-seg { background: var(--primary-950); }
        .lap-seg-btn {
            min-height: 44px; padding: 0 1.125rem; border: 0; border-radius: 999px; cursor: pointer;
            background: transparent; font-size: 0.875rem; font-weight: 600; color: var(--primary-900);
            transition: background-color 150ms ease, color 150ms ease;
        }
        .lap-seg-btn:hover { background: #fff; }
        .lap-seg-btn:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .lap-seg-btn[aria-pressed="true"] { background: var(--primary-700); color: #fff; }
        .dark .lap-seg-btn { color: var(--primary-100); }
        .dark .lap-seg-btn:hover { background: var(--primary-800); }
        .dark .lap-seg-btn[aria-pressed="true"] { background: var(--primary-600); color: #fff; }

        /* Phones: two compact columns, names only, so the table is not pushed a whole screen down. */
        @media (max-width: 639px) {
            .lap-types { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.5rem; }
            .lap-type { padding: 0.75rem; }
            .lap-type-question { display: none; }
            .lap-periodbar { display: grid; }
            .lap-seg { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .lap-seg-btn { padding: 0 0.5rem; }
        }

        @media (prefers-reduced-motion: reduce) { .lap-pill, .lap-type, .lap-seg-btn { transition: none; } }
    </style>

    <div class="lap">
        <section class="hh-card lap-filters" aria-label="Pilih laporan">
            {{-- Report picker: one card per report with the question it answers, so the owner picks by need,
                 not by a short label. The selected card is the one solid forest surface in this block. --}}
            <div class="lap-types" role="group" aria-label="Jenis laporan">
                @foreach ($types as $type)
                    <button type="button" class="lap-type" wire:click="selectType('{{ $type->value }}')" aria-pressed="{{ $activeType === $type ? 'true' : 'false' }}">
                        <span class="lap-type-tile" aria-hidden="true"><x-filament::icon :icon="$type->icon()" /></span>
                        <span class="lap-type-name">{{ $type->label() }}</span>
                        <span class="lap-type-question">{{ $type->question() }}</span>
                    </button>
                @endforeach
            </div>

            <div class="lap-periodbar">
                <div class="lap-seg" role="group" aria-label="Periode cepat">
                    @foreach ($presets as $value => $label)
                        <button type="button" class="lap-seg-btn" wire:click="selectPreset('{{ $value }}')" aria-pressed="{{ $activePreset->value === $value ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="lap-range-inputs">{{ $this->form }}</div>
            </div>

            <p wire:loading.delay class="hh-muted lap-loading" role="status">Memuat laporan...</p>
        </section>

        @if ($error)
            <section class="hh-card lap-state" role="alert">
                <p class="lap-state-title lap-error">Laporan belum bisa ditampilkan</p>
                <p class="hh-muted hh-small">{{ $error }}</p>
            </section>
        @else
            @if ($report->summary !== [] && $report->rows !== [])
                <section class="lap-stats" aria-label="Ringkasan {{ $report->title() }}">
                    @foreach ($report->summary as $line)
                        @php
                            // A summary line named after a status (Lunas, Dibatalkan) takes that status tone.
                            $statusTone = $statusTones[$line['label']] ?? null;
                            [$tone, $icon] = match (true) {
                                $statusTone === 'success' => ['success', 'heroicon-o-check-circle'],
                                $statusTone === 'warning' => ['warning', 'heroicon-o-clock'],
                                $statusTone === 'danger' => ['danger', 'heroicon-o-x-circle'],
                                $statusTone !== null => [$statusTone, 'heroicon-o-information-circle'],
                                $line['type'] === \App\Enums\ReportValueType::Money => ['money', 'heroicon-o-banknotes'],
                                $line['type'] === \App\Enums\ReportValueType::Percent => ['percent', 'heroicon-o-chart-pie'],
                                default => ['count', 'heroicon-o-rectangle-stack'],
                            };
                        @endphp
                        <div @class(['lap-stat', 'lap-tone-'.$tone, 'is-headline' => $loop->first])>
                            <span class="lap-stat-tile" aria-hidden="true"><x-filament::icon :icon="$icon" /></span>
                            <div style="min-width: 0;">
                                <p class="lap-stat-value">{{ $line['type']->format($line['value']) }}</p>
                                <p class="lap-stat-label">{{ $line['label'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="hh-card lap-table-card" aria-label="Tabel {{ $report->title() }}">
                @if ($report->rows === [])
                    <div class="lap-state">
                        <p class="lap-state-title">Belum ada data di periode ini</p>
                        <p class="hh-muted hh-small">{{ $report->definition }}</p>
                        @if ($activePreset !== \App\Enums\ReportPreset::LastMonth)
                            <button type="button" class="lap-pill lap-pill-sm" wire:click="selectPreset('{{ \App\Enums\ReportPreset::LastMonth->value }}')">Lihat bulan lalu</button>
                        @endif
                    </div>
                @else
                    <div class="lap-table-wrap">
                        <table class="lap-table">
                            <thead>
                                <tr>
                                    @foreach ($report->columns as $column)
                                        <th scope="col" @class(['lap-num' => $column['type']->isNumeric()])>{{ $column['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report->rows as $row)
                                    <tr>
                                        @foreach ($row as $index => $value)
                                            @php $tone = $report->columns[$index]['label'] === 'Status' ? ($statusTones[$value] ?? 'gray') : null; @endphp
                                            <td @class(['lap-num' => $report->columns[$index]['type']->isNumeric()])>
                                                @if ($tone)
                                                    <span class="lap-badge lap-badge-{{ $tone }}">{{ $value }}</span>
                                                @else
                                                    {{ $report->columns[$index]['type']->format($value) }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if ($report->rows !== [])
                <p class="hh-muted lap-note">{{ $report->definition }}</p>
            @endif
        @endif
    </div>
</x-filament-panels::page>
