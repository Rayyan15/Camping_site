<x-filament-panels::page>
    <style>
        .do-controls { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between; }
        .do-day { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .do-day-label { font-size: 1.125rem; font-weight: 700; color: var(--gray-950); }
        .do-pills { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
        .do-pill { min-height: 2.75rem; padding: 0 1rem; border-radius: 0.5rem; border: 1px solid var(--gray-300); background: #fff; font-size: 0.875rem; font-weight: 600; color: var(--gray-800); }
        .do-pill[aria-pressed="true"] { background: var(--primary-700); border-color: var(--primary-700); color: #fff; }
        .do-pill:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .do-owed { margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 0.5rem; background: var(--danger-50); color: var(--danger-800); font-size: 0.875rem; font-weight: 600; }
        .do-scroll { overflow-x: auto; border: 1px solid var(--gray-200); border-radius: 0.75rem; }
        .do-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.875rem; font-variant-numeric: tabular-nums; }
        .do-table th, .do-table td { padding: 0.75rem 0.875rem; text-align: left; vertical-align: top; border-bottom: 1px solid var(--gray-200); }
        .do-table tbody tr:last-child > * { border-bottom: 0; }
        .do-table thead th { background: var(--gray-50); font-size: 0.75rem; font-weight: 700; color: var(--gray-700); white-space: nowrap; }
        .do-table .do-num { text-align: right; white-space: nowrap; }
        .do-table .do-code { font-weight: 700; color: var(--gray-950); white-space: nowrap; }
        .do-table .do-sub { display: block; font-size: 0.8125rem; color: var(--gray-600); }
        .do-table .do-details { min-width: 14rem; max-width: 26rem; color: var(--gray-800); }
        .do-table tr.do-is-owed > td:first-child { box-shadow: inset 3px 0 0 var(--danger-600); }
        .do-table td.do-owed-cell { color: var(--danger-700); font-weight: 700; }
        .do-empty { padding: 1.5rem 0.25rem; font-size: 0.875rem; color: var(--gray-700); max-width: 36rem; }
        .dark .do-day-label, .dark .do-table .do-code { color: #fff; }
        .dark .do-pill { background: var(--gray-900); border-color: var(--gray-700); color: var(--gray-200); }
        .dark .do-pill[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
        .dark .do-owed { background: rgba(220, 38, 38, 0.15); color: var(--danger-300); }
        .dark .do-scroll, .dark .do-table th, .dark .do-table td { border-color: var(--gray-700); }
        .dark .do-table thead th { background: var(--gray-800); color: var(--gray-300); }
        .dark .do-table .do-sub, .dark .do-empty { color: var(--gray-400); }
        .dark .do-table .do-details { color: var(--gray-200); }
        .dark .do-table td.do-owed-cell { color: var(--danger-400); }
    </style>

    <x-filament::section>
        <div class="do-controls">
            <div class="do-day">
                <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="shiftDay(-1)" label-sr-only>Hari sebelumnya</x-filament::button>
                <label for="do-date" class="sr-only">Tanggal</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="do-date" type="date" wire:model.live="date" />
                </x-filament::input.wrapper>
                <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="shiftDay(1)" label-sr-only>Hari berikutnya</x-filament::button>
                @unless ($isToday)
                    <x-filament::button color="gray" wire:click="$set('date', '{{ now(config('app.timezone'))->toDateString() }}')">Kembali ke hari ini</x-filament::button>
                @endunless
            </div>
            <span class="do-day-label">{{ $dayLabel }}</span>
        </div>

        <div class="do-pills" role="group" aria-label="Saring jenis order">
            <button type="button" class="do-pill" aria-pressed="{{ $category === '' ? 'true' : 'false' }}" wire:click="$set('category', '')">Semua ({{ $total }})</button>
            @foreach ($labels as $key => $label)
                <button type="button" class="do-pill" aria-pressed="{{ $category === $key ? 'true' : 'false' }}" wire:click="$set('category', '{{ $key }}')">{{ $label }} ({{ $counts[$key] ?? 0 }})</button>
            @endforeach
        </div>

        @if ($owedCount > 0)
            <p class="do-owed" role="status">{{ $owedCount }} order belum lunas, total sisa Rp {{ number_format($owedTotal, 0, ',', '.') }}. Tagih sebelum tamu check-out.</p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <div wire:loading.delay class="do-empty">Memuat order...</div>
        <div wire:loading.delay.remove>
            @if ($rows->isEmpty())
                <p class="do-empty">
                    {{ $category === '' ? 'Tidak ada tamu menginap dan tidak ada pesanan makanan di tanggal ini.' : 'Tidak ada order jenis ini di tanggal ini.' }}
                </p>
            @else
                <div class="do-scroll" tabindex="0" role="region" aria-label="Daftar order {{ $dayLabel }}">
                    <table class="do-table">
                        <thead>
                            <tr>
                                <th scope="col">Kode</th>
                                <th scope="col">Jenis</th>
                                <th scope="col">Tenda</th>
                                <th scope="col">Rincian</th>
                                <th scope="col" class="do-num">Tagihan</th>
                                <th scope="col" class="do-num">Dibayar</th>
                                <th scope="col" class="do-num">Sisa</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="do-{{ $row['code'] }}" @class(['do-is-owed' => $row['owed'] > 0])>
                                    <td>
                                        <span class="do-code">{{ $row['code'] }}</span>
                                        <span class="do-sub">{{ $row['name'] }}</span>
                                    </td>
                                    <td>{{ $labels[$row['category']] }}</td>
                                    <td>{{ $row['tents'] }}</td>
                                    <td class="do-details">{{ $row['details'] === [] ? '-' : implode('; ', $row['details']) }}</td>
                                    <td class="do-num">Rp {{ number_format($row['bill'], 0, ',', '.') }}</td>
                                    <td class="do-num">Rp {{ number_format($row['paid'], 0, ',', '.') }}</td>
                                    <td @class(['do-num', 'do-owed-cell' => $row['owed'] > 0])>Rp {{ number_format($row['owed'], 0, ',', '.') }}</td>
                                    <td>{{ $row['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
