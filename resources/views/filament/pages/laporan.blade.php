<x-filament-panels::page>
    @include('filament.widgets.partials.dashboard-style')

    {{--
        Client asked for pill filters after the 17 Oct demo. Report type is the larger first row because it
        changes the whole table; the period row is smaller and shows the chosen range in words beside it.
        Same surfaces as the dashboard (white card on the gray canvas, forest as the only accent).
    --}}
    <style>
        .lap { display: grid; gap: 1.25rem; }
        .lap-filters { display: grid; gap: 1rem; }
        .lap-group { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .lap-group-label { width: 100%; font-size: 0.75rem; font-weight: 600; color: var(--gray-600); }
        .dark .lap-group-label { color: var(--gray-300); }

        .lap-pill {
            display: inline-flex; align-items: center; min-height: 44px;
            padding: 0 1rem; border-radius: 999px;
            border: 1px solid var(--gray-300); background: #fff;
            font-size: 0.875rem; font-weight: 600; color: var(--gray-700);
            cursor: pointer; transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
        }
        .lap-pill:hover { border-color: var(--primary-400); color: var(--primary-800); }
        .lap-pill:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .lap-pill[aria-pressed="true"] { background: var(--primary-700); border-color: var(--primary-700); color: #fff; }
        .dark .lap-pill { background: var(--gray-900); border-color: var(--gray-700); color: var(--gray-200); }
        .dark .lap-pill:hover { border-color: var(--primary-500); color: #fff; }
        .dark .lap-pill[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
        .lap-pill-sm { padding: 0 0.875rem; font-size: 0.8125rem; }

        .lap-range { font-size: 0.875rem; color: var(--gray-600); margin-left: 0.25rem; }
        .dark .lap-range { color: var(--gray-300); }
        /* Phones: the anchor dissolves so the panel takes a full row under the pills. */
        .lap-custom-anchor { display: contents; }
        .lap-custom-panel {
            flex-basis: 100%; margin-top: 0.5rem; padding: 1rem;
            border: 1px solid var(--gray-200); border-radius: 1rem; background: #fff;
        }
        .dark .lap-custom-panel { background: var(--gray-900); border-color: var(--gray-700); }
        .lap-custom-actions { display: flex; justify-content: flex-end; margin-top: 0.75rem; }
        .lap-pill.lap-pill-solid { background: var(--primary-700); border-color: var(--primary-700); color: #fff; }
        .dark .lap-pill.lap-pill-solid { background: var(--primary-600); border-color: var(--primary-600); }
        /* Wide screens: a panel under the pill, the only raised surface on the page, so it carries the one shadow. */
        @media (min-width: 768px) {
            .lap-custom-anchor { display: block; position: relative; }
            .lap-custom-panel {
                position: absolute; top: 100%; left: 0; z-index: 20; width: 26rem;
                box-shadow: 0 12px 24px -12px rgb(0 0 0 / 0.25);
            }
        }
        .lap-reset {
            margin-left: 0.5rem; min-height: 44px; padding: 0 0.25rem;
            background: none; border: 0; cursor: pointer;
            font-weight: 600; color: var(--primary-700); text-decoration: underline; text-underline-offset: 3px;
        }
        .lap-reset:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; border-radius: 0.25rem; }
        .dark .lap-reset { color: var(--primary-300); }

        .lap-table-card { padding: 0; overflow: hidden; }
        .lap-table-wrap { overflow-x: auto; }
        .lap-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .lap-table th {
            text-align: left; padding: 0.75rem 1rem; white-space: nowrap;
            font-size: 0.8125rem; font-weight: 600; color: var(--gray-700);
            background: var(--gray-50); border-bottom: 1px solid var(--gray-200);
        }
        .lap-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-950); }
        .lap-table tbody tr:last-child td { border-bottom: 0; }
        .lap-table tbody tr:hover td { background: var(--gray-50); }
        .dark .lap-table th { background: var(--gray-800); color: var(--gray-200); border-color: var(--gray-700); }
        .dark .lap-table td { color: #fff; border-color: var(--gray-800); }
        .dark .lap-table tbody tr:hover td { background: var(--gray-800); }
        .lap-num { text-align: right !important; font-variant-numeric: tabular-nums; }

        .lap-state { display: grid; justify-items: start; gap: 0.75rem; padding: 2rem 1.25rem; }
        .lap-state-title { font-weight: 600; color: var(--gray-950); }
        .dark .lap-state-title { color: #fff; }
        .lap-error { color: var(--danger-700); }
        .dark .lap-error { color: var(--danger-400); }
        .lap-note { max-width: 70ch; font-size: 0.8125rem; line-height: 1.5; }
        .lap-loading { font-size: 0.8125rem; }

        @media (prefers-reduced-motion: reduce) { .lap-pill { transition: none; } }
    </style>

    <div class="lap">
        <section class="hh-card lap-filters" aria-label="Pilih laporan">
            <div class="lap-group" role="group" aria-labelledby="lap-type-label">
                <span id="lap-type-label" class="lap-group-label">Jenis laporan</span>
                @foreach ($types as $value => $label)
                    <button type="button" class="lap-pill" wire:click="selectType('{{ $value }}')" aria-pressed="{{ $activeType->value === $value ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="lap-group" role="group" aria-labelledby="lap-period-label">
                <span id="lap-period-label" class="lap-group-label">Periode</span>
                @foreach ($presets as $value => $label)
                    @if ($value === \App\Enums\ReportPreset::Custom->value)
                        {{-- The date inputs hang under the Kustom pill on wide screens (borrowed from the reference's
                             advanced filter panel) so the table does not jump; on phones they sit in the flow. --}}
                        <div class="lap-custom-anchor" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                            <button type="button" class="lap-pill lap-pill-sm" wire:click="selectPreset('{{ $value }}')" x-on:click="open = true"
                                aria-pressed="{{ $isCustom ? 'true' : 'false' }}" aria-controls="lap-custom-panel" x-bind:aria-expanded="open.toString()">{{ $label }}</button>
                            @if ($isCustom)
                                <div id="lap-custom-panel" class="lap-custom-panel" x-show="open" x-on:click.outside="open = false" role="group" aria-label="Rentang tanggal kustom">
                                    {{ $this->form }}
                                    <div class="lap-custom-actions">
                                        <button type="button" class="lap-pill lap-pill-sm lap-pill-solid" x-on:click="open = false">Selesai</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <button type="button" class="lap-pill lap-pill-sm" wire:click="selectPreset('{{ $value }}')" aria-pressed="{{ $activePreset->value === $value ? 'true' : 'false' }}">{{ $label }}</button>
                    @endif
                @endforeach
                @if ($report)
                    <span class="lap-range">
                        {{ $report->period->from->locale('id')->translatedFormat('j F Y') }} sampai {{ $report->period->to->locale('id')->translatedFormat('j F Y') }}
                        @if ($isCustom)
                            <button type="button" class="lap-reset" wire:click="selectPreset('{{ \App\Enums\ReportPreset::ThisMonth->value }}')">Reset ke bulan ini</button>
                        @endif
                    </span>
                @endif
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
                <section class="hh-card hh-strip" aria-label="Ringkasan {{ $report->title() }}">
                    @foreach ($report->summary as $line)
                        <div class="hh-mini">
                            <div class="hh-mini-text">
                                <p class="hh-mini-value">{{ $line['type']->format($line['value']) }}</p>
                                <p class="hh-muted hh-xsmall">{{ $line['label'] }}</p>
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
                                            <td @class(['lap-num' => $report->columns[$index]['type']->isNumeric()])>{{ $report->columns[$index]['type']->format($value) }}</td>
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
