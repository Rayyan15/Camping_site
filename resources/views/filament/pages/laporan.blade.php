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
        /* The first column names the row (code or name), so it stays pinned and bold while wide reports scroll sideways. */
        .lap-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.875rem; }
        .lap-table th {
            text-align: center; padding: 0.625rem 1rem; white-space: nowrap; vertical-align: bottom;
            font-size: 0.75rem; font-weight: 700; color: var(--gray-700);
            background: var(--gray-50); border-bottom: 1px solid var(--gray-300);
        }
        .lap-table td { text-align: center; padding: 0.75rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-950); vertical-align: middle; white-space: nowrap; }
        .lap-table tbody tr:last-child td { border-bottom: 0; }
        .lap-table tbody tr:hover td { background: var(--gray-50); }
        .lap-table tr > :first-child { position: sticky; left: 0; z-index: 1; font-weight: 600; background: #fff; }
        .lap-table thead tr > :first-child { background: var(--gray-50); z-index: 2; }
        .lap-table td.lap-num { font-weight: 600; }
        .lap-rowcount { padding: 0.625rem 1rem; border-top: 1px solid var(--gray-200); font-size: 0.8125rem; color: var(--gray-700); }
        .dark .lap-table th, .dark .lap-table thead tr > :first-child { background: var(--gray-800); color: var(--gray-300); border-color: var(--gray-700); }
        .dark .lap-table td { color: #fff; border-color: var(--gray-800); }
        .dark .lap-table tr > :first-child { background: var(--gray-900); }
        .dark .lap-table tbody tr:hover td { background: var(--gray-800); }
        .dark .lap-rowcount { color: var(--gray-300); border-color: var(--gray-700); }
        .lap-num { text-align: center !important; font-variant-numeric: tabular-nums; }

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


        .lap-state { display: grid; justify-items: start; gap: 0.75rem; padding: 2rem 1.25rem; }
        .lap-state-title { font-weight: 600; color: var(--gray-950); }
        .dark .lap-state-title { color: #fff; }
        .lap-error { color: var(--danger-700); }
        .dark .lap-error { color: var(--danger-400); }
        .lap-note { max-width: 70ch; font-size: 0.8125rem; line-height: 1.5; }
        .lap-loading { font-size: 0.8125rem; }


        /* Summary band: the headline number big on the left, the rest as a list, and a composition bar when
           the rest are statuses that add up to the headline (bookings, refunds). */
        .lap-band { display: grid; gap: 1.25rem; padding: 1.25rem; border-radius: 1rem; background: #fff; border: 1px solid var(--gray-200); }
        .dark .lap-band { background: var(--gray-900); border-color: var(--gray-800); }
        @media (min-width: 768px) { .lap-band { grid-template-columns: minmax(12rem, 16rem) 1fr; align-items: center; padding: 1.5rem; } }
        .lap-head { display: grid; gap: 0.25rem; padding: 1.25rem; border-radius: 0.875rem; background: var(--primary-700); color: #fff; }
        .dark .lap-head { background: var(--primary-600); }
        .lap-head-label { font-size: 0.8125rem; font-weight: 600; color: var(--primary-100); }
        .lap-head-value { font-size: 2.5rem; font-weight: 700; line-height: 1.05; letter-spacing: -0.02em; overflow-wrap: anywhere; }
        .lap-head-value.is-long { font-size: 1.75rem; }
        .lap-lines { display: grid; gap: 0.25rem 1.5rem; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); margin: 0; }
        .lap-line { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 0 0.625rem; padding: 0.625rem 0; border-bottom: 1px solid var(--gray-100); }
        .dark .lap-line { border-color: var(--gray-800); }
        .lap-line dt { grid-column: 2; font-size: 0.8125rem; color: var(--hh-ink-soft); }
        .dark .lap-line dt { color: var(--gray-300); }
        .lap-line dd { grid-column: 2; margin: 0; font-size: 1.125rem; font-weight: 700; color: var(--gray-950); }
        .dark .lap-line dd { color: #fff; }
        .lap-line-share { margin-left: 0.375rem; font-size: 0.75rem; font-weight: 600; color: var(--hh-ink-soft); }
        .dark .lap-line-share { color: var(--gray-400); }
        .lap-mark { grid-row: span 2; width: 0.75rem; height: 2.25rem; border-radius: 999px; }
        .lap-mark-icon { grid-row: span 2; display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: 0.625rem; }
        .lap-mark-icon svg { width: 1.125rem; height: 1.125rem; }
        .lap-bar { grid-column: 1 / -1; display: flex; height: 0.75rem; overflow: hidden; border-radius: 999px; background: var(--gray-100); }
        .dark .lap-bar { background: var(--gray-800); }
        .lap-bar span { display: block; height: 100%; }
        .lap-bar span + span { border-left: 2px solid #fff; }
        .dark .lap-bar span + span { border-color: var(--gray-900); }
        /* Status and value-type tones, from the brand palette. */
        .lap-fill-success { background: var(--primary-600); }
        .lap-fill-warning { background: var(--hh-sand-dark); }
        .lap-fill-danger { background: var(--hh-ember); }
        .lap-fill-info { background: var(--primary-800); }
        .lap-fill-gray { background: var(--gray-500); }
        .lap-icon-count { background: var(--primary-50); color: var(--primary-700); }
        .lap-icon-money { background: var(--hh-ember-soft); color: var(--hh-ember-dark); }
        .lap-icon-percent { background: var(--hh-cream); color: var(--hh-sand-dark); }
        .dark .lap-icon-count, .dark .lap-icon-money, .dark .lap-icon-percent { background: var(--primary-800); }
        .dark .lap-icon-count { color: var(--primary-200); }
        .dark .lap-icon-money { color: var(--hh-ember-light); }
        .dark .lap-icon-percent { color: var(--hh-sand); }
        .dark .lap-fill-success { background: var(--primary-400); }
        .dark .lap-fill-warning { background: var(--hh-sand); }
        .dark .lap-fill-danger { background: var(--hh-ember-light); }
        .dark .lap-fill-info { background: var(--primary-200); }
        .dark .lap-fill-gray { background: var(--gray-300); }

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
                @php
                    $head = $report->summary[0];
                    $rest = array_slice($report->summary, 1);
                    $headValue = $head['type']->format($head['value']);
                    // Status lines that share the headline's unit add up to it, so they get a composition bar.
                    $isBreakdown = $head['type'] === \App\Enums\ReportValueType::Count && $head['value'] > 0 && $rest !== []
                        && collect($rest)->every(fn ($line) => isset($statusTones[$line['label']]));
                @endphp
                <section class="lap-band" aria-label="Ringkasan {{ $report->title() }}">
                    <div class="lap-head">
                        <p class="lap-head-label">{{ $head['label'] }}</p>
                        <p @class(['lap-head-value', 'is-long' => mb_strlen($headValue) > 9])>{{ $headValue }}</p>
                    </div>

                    @if ($rest !== [])
                        <dl class="lap-lines">
                            @foreach ($rest as $line)
                                @php
                                    $statusTone = $statusTones[$line['label']] ?? null;
                                    [$iconTone, $icon] = match ($line['type']) {
                                        \App\Enums\ReportValueType::Money => ['money', 'heroicon-o-banknotes'],
                                        \App\Enums\ReportValueType::Percent => ['percent', 'heroicon-o-chart-pie'],
                                        default => ['count', 'heroicon-o-rectangle-stack'],
                                    };
                                @endphp
                                <div class="lap-line">
                                    @if ($statusTone)
                                        <span class="lap-mark lap-fill-{{ $statusTone }}" aria-hidden="true"></span>
                                    @else
                                        <span class="lap-mark-icon lap-icon-{{ $iconTone }}" aria-hidden="true"><x-filament::icon :icon="$icon" /></span>
                                    @endif
                                    <dt>{{ $line['label'] }}</dt>
                                    <dd>
                                        {{ $line['type']->format($line['value']) }}
                                        @if ($isBreakdown)
                                            <span class="lap-line-share">{{ round($line['value'] / $head['value'] * 100) }}%</span>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if ($isBreakdown)
                        <div class="lap-bar" role="img" aria-label="Komposisi status: {{ collect($rest)->map(fn ($line) => $line['label'].' '.$line['value'])->implode(', ') }}">
                            @foreach ($rest as $line)
                                @if ($line['value'] > 0)
                                    <span class="lap-fill-{{ $statusTones[$line['label']] }}" style="width: {{ $line['value'] / $head['value'] * 100 }}%"></span>
                                @endif
                            @endforeach
                        </div>
                    @endif
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
                    <p class="lap-rowcount">{{ count($report->rows) }} baris</p>
                @endif
            </section>

            @if ($report->rows !== [])
                <p class="hh-muted lap-note">{{ $report->definition }}</p>
            @endif
        @endif
    </div>
</x-filament-panels::page>
