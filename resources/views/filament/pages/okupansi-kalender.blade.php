@php
    $first = $grid['dates'][0];
    $last = $grid['dates'][count($grid['dates']) - 1];
    $rangeLabel = $first->translatedFormat('j M').' sampai '.$last->translatedFormat('j M Y');
    $dayCount = count($grid['dates']);
@endphp

<x-filament-panels::page>
    {{--
        Direction: a field ledger for a small campsite. Forest dominant (brand tokens), amber only for holds, rust only for
        attention states. Status is always printed as a word and a pattern, never color alone (R-25 non-color feedback).
        Tags carry their own solid label background so every text pair is checked: 8.11, 8.51, 6.41, 12.16, 7.43, 9.46, 9.32, 8.18.
    --}}
    <style>
        .oc { --oc-line: var(--gray-300); --oc-head: var(--gray-50); --oc-muted: var(--gray-600); --oc-today: color-mix(in srgb, var(--hh-forest-700) 10%, transparent); --oc-weekend: rgb(120 113 108 / 0.10); --oc-focus: var(--primary-700); --oc-open: #c9e6cf; }
        .dark .oc { --oc-line: var(--gray-700); --oc-head: var(--gray-900); --oc-muted: var(--gray-400); --oc-today: color-mix(in srgb, var(--hh-forest-300) 14%, transparent); --oc-weekend: rgb(161 161 170 / 0.12); --oc-focus: var(--primary-400); }

        /* Header: tonight's figure leads, the range controls sit under a hairline so the calendar keeps the stage. */
        .oc-header { display: grid; gap: 1rem; margin-bottom: 0.75rem; padding: 1rem 1.25rem; border: 1px solid var(--oc-line); border-radius: 0.75rem; background: #fff; }
        .dark .oc-header { background: var(--gray-900); }
        .oc-summary { display: grid; gap: 1rem 2rem; grid-template-columns: 1fr; align-items: end; }
        .oc-headline { display: grid; gap: 0.375rem; }
        .oc-meter { height: 0.375rem; border-radius: 999px; background: var(--gray-200); overflow: hidden; max-width: 16rem; }
        .oc-meter span { display: block; height: 100%; background: var(--primary-700); border-radius: inherit; }
        .dark .oc-meter { background: var(--gray-700); }
        .dark .oc-meter span { background: var(--primary-400); }
        .oc-figure { font-size: 3rem; line-height: 1; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }
        .oc-figure small { font-size: 1.25rem; font-weight: 600; }
        .oc-caption { font-size: 0.8125rem; font-weight: 600; color: var(--oc-muted); }
        .oc-facts { display: grid; gap: 0.5rem; grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr)); margin: 0; }
        .oc-facts > div { padding: 0.625rem 0.875rem; border-radius: 0.5rem; background: var(--oc-head); }
        .oc-facts > .oc-fact-alert { background: var(--warning-50); }
        .oc-fact-alert dt, .oc-fact-alert dd { color: var(--warning-800); }
        .dark .oc-facts > .oc-fact-alert { background: var(--gray-800); }
        .dark .oc-fact-alert dt, .dark .oc-fact-alert dd { color: var(--warning-300); }
        .oc-facts dt { font-size: 0.8125rem; color: var(--oc-muted); }
        .oc-facts dd { margin: 0; font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        @media (min-width: 48rem) { .oc-summary { grid-template-columns: minmax(12rem, 1fr) 2fr; } }

        .oc-bar { display: flex; flex-wrap: wrap; gap: 0.75rem 1rem; align-items: center; justify-content: space-between; padding-top: 1rem; border-top: 1px solid var(--oc-line); }
        .oc-nav { display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; align-items: center; }
        .oc-seg-group { display: inline-flex; padding: 0.25rem; gap: 0.25rem; border-radius: 0.625rem; background: var(--oc-head); border: 1px solid var(--oc-line); }
        .oc-seg-btn { min-height: 2.75rem; padding: 0 0.875rem; border-radius: 0.4375rem; font-size: 0.875rem; font-weight: 600; color: var(--gray-700); }
        .oc-seg-btn[aria-pressed="true"] { background: var(--primary-700); color: #fff; }
        .oc-seg-btn:focus-visible { outline: 2px solid var(--oc-focus); outline-offset: 2px; }
        .dark .oc-seg-btn { color: var(--gray-300); }
        .dark .oc-seg-btn[aria-pressed="true"] { background: var(--primary-400); color: var(--gray-950); }
        .oc-group { display: inline-flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .oc-range { font-size: 1.125rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--gray-950); }
        .dark .oc-range { color: #fff; }
        .oc-btn { min-height: 2.75rem; min-width: 2.75rem; }

        .oc-scroll { position: relative; overflow: auto; max-height: 72vh; border: 1px solid var(--oc-line); border-radius: 0.5rem; background: #fff; }
        .dark .oc-scroll { background: var(--gray-950); }
        .oc-scroll:focus-visible, .oc-seg:focus-visible, .oc-btn:focus-visible { outline: 2px solid var(--oc-focus); outline-offset: 2px; }
        .oc-table { border-collapse: separate; border-spacing: 0; table-layout: fixed; width: max-content; min-width: 100%; }
        .oc-table col.oc-unitcol { width: 6.5rem; }
        .oc-table col.oc-daycol { width: 3.5rem; }
        .oc-table col.oc-today { background: var(--oc-today); }
        .oc-table col.oc-weekend { background: var(--oc-weekend); }

        .oc-table thead th { position: sticky; top: 0; z-index: 3; background: var(--oc-head); border-bottom: 2px solid var(--oc-line); padding: 0.375rem 0; text-align: center; font-weight: 600; font-size: 0.75rem; line-height: 1.2; }
        .oc-table thead th.oc-corner { left: 0; z-index: 5; text-align: left; padding-left: 0.75rem; }
        .oc-dow { display: block; color: var(--oc-muted); }
        .oc-date { display: block; font-size: 1rem; font-variant-numeric: tabular-nums; }
        .oc-th-today { box-shadow: inset 0 -4px 0 var(--oc-focus); }
        .oc-th-weekend .oc-dow { font-weight: 700; }
        .oc-th-weekend { border-left: 1px dashed var(--oc-line); }
        .oc-mark { display: block; font-size: 0.6875rem; font-weight: 700; }

        .oc-table tbody th.oc-unit { position: sticky; left: 0; z-index: 2; background: #fff; text-align: left; padding: 0 0.75rem; font-weight: 600; font-size: 0.875rem; border-right: 1px solid var(--oc-line); border-bottom: 1px solid var(--oc-line); height: 2.75rem; }
        .dark .oc-table tbody th.oc-unit { background: var(--gray-950); }
        .oc-table tbody td { border-bottom: 1px solid var(--oc-line); padding: 2px; height: 2.75rem; }
        .oc-table tr.oc-type th { position: sticky; left: 0; z-index: 2; background: var(--oc-head); text-align: left; padding: 0.5rem 0.75rem; font-size: 0.8125rem; letter-spacing: 0.04em; border-bottom: 1px solid var(--oc-line); }
        .oc-table tr.oc-type td { background: var(--oc-head); border-bottom: 1px solid var(--oc-line); }
        .oc-count { font-weight: 400; color: var(--oc-muted); }

        .oc-free { color: var(--oc-muted); font-size: 0.75rem; text-align: center; }

        /* Tag palette: label background and text pairs are verified for WCAG AA with the contrast tool. */
        .oc-tag { --bg: var(--hh-cream); --fg: var(--hh-ink-soft); --pat: none; display: flex; align-items: center; justify-content: center; width: 100%; min-height: 2.5rem; padding: 2px; border: 1px solid var(--fg); border-radius: 0.375rem; background-color: var(--bg); background-image: var(--pat); color: var(--fg); overflow: hidden; }
        .oc-label { display: block; max-width: 100%; padding: 1px 5px; border-radius: 0.25rem; background: var(--bg); text-align: center; font-size: 0.75rem; line-height: 1.25; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .oc-label b { display: block; font-weight: 700; }
        .oc-label i { display: block; font-style: normal; font-size: 0.6875rem; }
        .oc-held { --bg: var(--hh-ember-soft); --fg: var(--hh-ember-dark); --pat: repeating-linear-gradient(45deg, var(--hh-ember-light) 0 5px, var(--hh-ember-soft) 5px 10px); }
        .oc-booked { --bg: var(--hh-forest-700); --fg: var(--hh-cream); --pat: none; border-color: var(--hh-forest-950); }
        .oc-staying { --bg: var(--hh-forest-950); --fg: var(--hh-cream); --pat: radial-gradient(circle, var(--hh-forest-300) 1.5px, transparent 2px); background-size: 8px 8px; }
        .oc-completed { --bg: var(--hh-forest-100); --fg: var(--hh-ink-soft); }
        .oc-blocked { --bg: var(--hh-sand); --fg: var(--hh-ink); --pat: repeating-linear-gradient(45deg, var(--hh-sand-dark) 0 2px, transparent 2px 7px), repeating-linear-gradient(-45deg, var(--hh-sand-dark) 0 2px, transparent 2px 7px); }
        .oc-needs_review { --bg: #ffffff; --fg: var(--hh-ember-dark); border: 2px dashed var(--fg); }
        .oc-unavailable { --bg: var(--hh-cream-deep); --fg: var(--hh-ink-soft); --pat: repeating-linear-gradient(0deg, var(--hh-sand) 0 1px, transparent 1px 5px); }
        .oc-free-tag { --bg: var(--oc-open); --fg: var(--hh-forest-900); border-color: var(--hh-forest-300); }

        .oc-seg { display: block; width: 100%; min-height: 2.5rem; padding: 0; border: 0; background: none; cursor: pointer; border-radius: 0.375rem; }
        .oc-seg:hover .oc-tag { filter: brightness(0.96); }

        .oc-legend { margin-top: 1rem; }
        .oc-legend h2 { font-size: 0.875rem; font-weight: 700; margin-bottom: 0.5rem; }
        .oc-legend dl { display: grid; gap: 0.5rem 1.5rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); margin: 0; }
        .oc-legend div { display: grid; grid-template-columns: 5rem 1fr; gap: 0.5rem; align-items: center; }
        .oc-legend dd { margin: 0; font-size: 0.8125rem; color: var(--oc-muted); }
        .oc-legend .oc-tag { min-height: 1.75rem; }

        .oc-empty { padding: 2rem 1rem; text-align: center; color: var(--oc-muted); }
        .oc-loading { font-size: 0.8125rem; color: var(--oc-muted); }

        .oc-dl { display: grid; grid-template-columns: 8rem 1fr; gap: 0.5rem 1rem; margin: 0; }
        .oc-dl dt { color: var(--oc-muted); }
        .oc-dl dd { margin: 0; }
        .oc-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        @media (max-width: 40rem) {
            .oc-figure { font-size: 2.5rem; }
            .oc-table col.oc-unitcol { width: 5rem; }
            .oc-table col.oc-daycol { width: 3.25rem; }
            .oc-scroll { max-height: 66vh; }
        }
        @media (forced-colors: active) { .oc-tag { border: 2px solid CanvasText; } }
    </style>

    <div class="oc">
        <header class="oc-header">
            <section class="oc-summary" aria-label="Ringkasan malam ini">
                <div class="oc-headline">
                    <p class="oc-caption">Okupansi malam ini, {{ $summary['date']->translatedFormat('l, j F Y') }}</p>
                    <div class="oc-figure">{{ $summary['percent'] }}<small>%</small></div>
                    <div class="oc-meter" aria-hidden="true"><span style="width: {{ min(100, max(0, $summary['percent'])) }}%"></span></div>
                </div>
                <dl class="oc-facts">
                    <div>
                        <dt>Unit bebas</dt>
                        <dd>{{ $summary['free'] }} <span class="oc-count">dari {{ $summary['active'] }}</span></dd>
                    </div>
                    <div>
                        <dt>Terisi atau menginap</dt>
                        <dd>{{ $summary['occupied'] }}</dd>
                    </div>
                    @if($summary['review'] > 0)
                        <div class="oc-fact-alert">
                            <dt>Perlu ditinjau</dt>
                            <dd>{{ $summary['review'] }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <div class="oc-bar">
                <div class="oc-nav">
                    <div class="oc-group" role="group" aria-label="Geser rentang tanggal">
                        <x-filament::icon-button class="oc-btn" color="gray" icon="heroicon-m-chevron-left" label="Rentang sebelumnya" wire:click="previous" />
                        <x-filament::button class="oc-btn" color="gray" outlined size="sm" wire:click="goToToday">Hari ini</x-filament::button>
                        <x-filament::icon-button class="oc-btn" color="gray" icon="heroicon-m-chevron-right" label="Rentang berikutnya" wire:click="next" />
                    </div>
                    <h2 class="oc-range" aria-live="polite">{{ $rangeLabel }}</h2>
                </div>
                <div class="oc-seg-group" role="group" aria-label="Lebar rentang">
                    @foreach($dayOptions as $option)
                        <button type="button" class="oc-seg-btn" wire:click="setDays({{ $option }})" aria-pressed="{{ $days === $option ? 'true' : 'false' }}">{{ $option }} hari</button>
                    @endforeach
                </div>
            </div>
        </header>

        <p class="oc-loading" wire:loading wire:target="previous, next, goToToday, setDays" role="status">Memuat kalender...</p>

        @if($unitCount === 0)
            <div class="oc-scroll">
                <p class="oc-empty">Belum ada unit. Tambahkan unit di menu Manajemen Tenda, lalu kalender akan terisi di sini.</p>
            </div>
        @else
            <div class="oc-scroll" role="region" aria-label="Kalender okupansi per unit, {{ $rangeLabel }}" tabindex="0">
                <table class="oc-table">
                    <colgroup>
                        <col class="oc-unitcol">
                        @foreach($grid['dates'] as $date)
                            <col class="oc-daycol {{ $date->toDateString() === $todayKey ? 'oc-today' : ($date->isWeekend() ? 'oc-weekend' : '') }}">
                        @endforeach
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="oc-corner" scope="col">Unit</th>
                            @foreach($grid['dates'] as $date)
                                @php $isToday = $date->toDateString() === $todayKey; @endphp
                                <th scope="col" class="{{ $isToday ? 'oc-th-today' : '' }} {{ $date->isWeekend() ? 'oc-th-weekend' : '' }}" @if($isToday) aria-current="date" @endif>
                                    <span class="oc-dow">{{ $date->translatedFormat('D') }}</span>
                                    <span class="oc-date">{{ $date->format('j') }}</span>
                                    @if($isToday)
                                        <span class="oc-mark">Hari ini</span>
                                    @elseif($date->day === 1 || $loop->first)
                                        <span class="oc-mark">{{ $date->translatedFormat('M') }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grid['groups'] as $group)
                            <tr class="oc-type">
                                <th scope="rowgroup" colspan="1">{{ $group['unit_type'] }} <span class="oc-count">({{ count($group['units']) }} unit)</span></th>
                                <td colspan="{{ $dayCount }}"></td>
                            </tr>
                            @foreach($group['units'] as $unit)
                                <tr wire:key="oc-unit-{{ $unit['id'] }}">
                                    <th scope="row" class="oc-unit">{{ $unit['code'] }}</th>
                                    @foreach($unit['segments'] as $segment)
                                        @php
                                            $status = $segment['status'];
                                            $isFree = $status === \App\Enums\OccupancyCellStatus::Free;
                                            $detail = $segment['booking_code'] ?? $segment['reason'];
                                            $aria = 'Unit '.$unit['code'].', '.$status->label()
                                                .($segment['booking_code'] ? ', booking '.$segment['booking_code'] : '')
                                                .($segment['guest'] ? ', '.$segment['guest'] : '')
                                                .($segment['reason'] ? ', alasan '.$segment['reason'] : '')
                                                .', mulai '.\Carbon\CarbonImmutable::parse($segment['date'])->translatedFormat('j F').', '.$segment['span'].' malam';
                                        @endphp
                                        <td colspan="{{ $segment['span'] }}" wire:key="oc-seg-{{ $unit['id'] }}-{{ $segment['date'] }}">
                                            @if($isFree)
                                                <div class="oc-tag oc-free-tag oc-free" title="{{ $status->description() }}">{{ $status->label() }}</div>
                                            @elseif($segment['booking_id'] || $status === \App\Enums\OccupancyCellStatus::Blocked)
                                                <button type="button" class="oc-seg" aria-label="{{ $aria }}. Buka ringkasan" title="{{ $status->description() }}" wire:click="mountAction('booking', @js(['unit' => $unit['id'], 'date' => $segment['date']]))">
                                                    <span class="oc-tag oc-{{ $status->value }}">
                                                        <span class="oc-label">
                                                            <b>{{ $status->shortLabel() }}</b>
                                                            @if($segment['span'] >= 2 && ($segment['guest'] ?? $segment['reason']))<i>{{ $segment['guest'] ?? $segment['reason'] }}</i>@endif
                                                        </span>
                                                    </span>
                                                </button>
                                            @else
                                                <div class="oc-tag oc-{{ $status->value }}" title="{{ $status->description() }}"><span class="oc-label"><b>{{ $status->shortLabel() }}</b></span></div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <section class="oc-legend" aria-labelledby="oc-legend-title">
            <h2 id="oc-legend-title">Legenda status</h2>
            <dl>
                @foreach($statuses as $status)
                    <div>
                        <dt><span class="oc-tag oc-{{ $status->value }} {{ $status === \App\Enums\OccupancyCellStatus::Free ? 'oc-free-tag' : '' }}"><span class="oc-label"><b>{{ $status->shortLabel() }}</b></span></span></dt>
                        <dd>{{ $status->label() }}: {{ $status->description() }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="oc-caption">Malam check-out tidak dihitung. Tamu hanya tampil sebagai nama depan dan inisial.</p>
        </section>
    </div>
</x-filament-panels::page>
