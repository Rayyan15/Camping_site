@php
    $rupiah = fn (int $amount): string => 'Rp '.number_format($amount, 0, ',', '.');
    $urlFor = fn (array $row): string => $row['kind'] === 'booking'
        ? \App\Filament\Resources\Bookings\BookingResource::getUrl('edit', ['record' => $row['id']])
        : \App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $row['id']]);
@endphp

<x-filament-panels::page>
    {{-- Direction: calm forest admin (design-notes). Owed money is the one focal point, so red is spent only there. --}}
    <style>
        .do-head { display: grid; gap: 1.25rem; }
        .do-bar { display: flex; flex-wrap: wrap; gap: 0.75rem 1.5rem; align-items: center; justify-content: space-between; }
        .do-nav { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .do-nav label { font-size: 0.875rem; font-weight: 600; color: var(--gray-700); }
        .do-title { display: grid; gap: 0.125rem; text-align: right; }
        .do-title strong { font-size: 1.25rem; line-height: 1.3; color: var(--gray-950); }
        .do-title span { font-size: 0.8125rem; color: var(--gray-600); }

        /* Totals for the day: what was billed, what came in, what is still owed. */
        .do-sums { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); border: 1px solid var(--gray-200); border-radius: 0.75rem; overflow: hidden; }
        .do-sum { padding: 0.875rem 1rem; background: var(--gray-50); }
        .do-sum + .do-sum { border-left: 1px solid var(--gray-200); }
        .do-sum dt { font-size: 0.75rem; font-weight: 600; color: var(--gray-600); }
        .do-sum dd { margin-top: 0.25rem; font-size: 1.125rem; font-weight: 700; color: var(--gray-950); font-variant-numeric: tabular-nums; }
        .do-sum.is-owed { background: var(--danger-50); }
        .do-sum.is-owed dt { color: var(--danger-700); }
        .do-sum.is-owed dd { color: var(--danger-700); }

        /* Category filter: a segmented row with the count set apart, so a zero reads at a glance. */
        .do-pills { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .do-pill { display: inline-flex; align-items: center; gap: 0.5rem; min-height: 2.75rem; padding: 0 0.5rem 0 0.875rem; border-radius: 0.625rem; border: 1px solid var(--gray-300); background: #fff; font-size: 0.875rem; font-weight: 600; color: var(--gray-800); transition: background-color 150ms, border-color 150ms; }
        .do-pill:hover { border-color: var(--primary-600); }
        .do-pill:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .do-pill .do-count { min-width: 1.75rem; padding: 0.125rem 0.5rem; border-radius: 0.375rem; background: var(--gray-100); font-size: 0.8125rem; text-align: center; font-variant-numeric: tabular-nums; }
        .do-pill.is-empty { color: var(--gray-600); }
        .do-pill[aria-pressed="true"] { background: var(--primary-700); border-color: var(--primary-700); color: #fff; }
        .do-pill[aria-pressed="true"] .do-count { background: rgba(255, 255, 255, 0.18); color: #fff; }

        .do-owed { display: flex; gap: 0.625rem; align-items: flex-start; padding: 0.75rem 1rem; border-radius: 0.625rem; border: 1px solid var(--danger-200); background: var(--danger-50); color: var(--danger-800); font-size: 0.875rem; font-weight: 600; }

        .do-scroll { overflow-x: auto; border: 1px solid var(--gray-200); border-radius: 0.75rem; }
        .do-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.875rem; font-variant-numeric: tabular-nums; }
        .do-table th, .do-table td { padding: 0.875rem 1rem; text-align: center; vertical-align: middle; border-bottom: 1px solid var(--gray-200); }
        .do-table tbody tr:last-child > * { border-bottom: 0; }
        .do-table thead th { position: sticky; top: 0; z-index: 1; background: var(--gray-50); font-size: 0.75rem; font-weight: 700; color: var(--gray-700); white-space: nowrap; }
        .do-table tbody tr { transition: background-color 150ms; }
        .do-table tbody tr:hover { background: var(--gray-50); }
        .do-table .do-num { white-space: nowrap; }
        .do-table .do-code { font-weight: 700; color: var(--primary-700); white-space: nowrap; text-decoration: underline; text-decoration-color: transparent; text-underline-offset: 3px; }
        .do-table .do-code:hover, .do-table .do-code:focus-visible { text-decoration-color: currentColor; outline: none; }
        .do-table .do-sub { display: block; margin-top: 0.125rem; font-size: 0.8125rem; color: var(--gray-600); }
        .do-table .do-details { min-width: 14rem; max-width: 24rem; color: var(--gray-800); line-height: 1.5; }
        .do-tag { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 0.375rem; background: var(--gray-100); color: var(--gray-800); font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .do-tag.is-food { background: var(--primary-50); color: var(--primary-800); }
        .do-status { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; white-space: nowrap; background: var(--gray-100); color: var(--gray-800); }
        .do-status.is-paid { background: var(--primary-50); color: var(--primary-800); }
        .do-status.is-owed { background: var(--danger-50); color: var(--danger-700); }
        /* The left edge marks a real state: this row still owes money. */
        .do-table tr.do-is-owed { background: color-mix(in srgb, var(--danger-50) 55%, transparent); }
        .do-table tr.do-is-owed > td:first-child { box-shadow: inset 3px 0 0 var(--danger-600); }
        .do-table td.do-owed-cell { color: var(--danger-700); font-weight: 700; }
        .do-table td.do-zero { color: var(--gray-500); }

        .do-empty { padding: 2rem 1rem; text-align: center; font-size: 0.875rem; color: var(--gray-700); }
        .do-empty strong { display: block; margin-bottom: 0.25rem; font-size: 1rem; color: var(--gray-950); }

        @media (max-width: 640px) {
            .do-title { text-align: left; }
            .do-sum + .do-sum { border-left: 0; border-top: 1px solid var(--gray-200); }
        }
        @media (prefers-reduced-motion: reduce) { .do-pill, .do-table tbody tr { transition: none; } }

        .dark .do-nav label, .dark .do-title span, .dark .do-sum dt, .dark .do-table .do-sub { color: var(--gray-400); }
        .dark .do-title strong, .dark .do-sum dd, .dark .do-empty strong { color: #fff; }
        .dark .do-sums, .dark .do-scroll, .dark .do-table th, .dark .do-table td, .dark .do-sum + .do-sum { border-color: var(--gray-700); }
        .dark .do-sum, .dark .do-table thead th { background: var(--gray-800); }
        .dark .do-table thead th { color: var(--gray-300); }
        .dark .do-sum.is-owed { background: rgba(220, 38, 38, 0.15); }
        .dark .do-sum.is-owed dt, .dark .do-sum.is-owed dd, .dark .do-table td.do-owed-cell { color: var(--danger-400); }
        .dark .do-pill { background: var(--gray-900); border-color: var(--gray-700); color: var(--gray-200); }
        .dark .do-pill .do-count, .dark .do-tag, .dark .do-status { background: var(--gray-800); color: var(--gray-200); }
        .dark .do-pill.is-empty { color: var(--gray-400); }
        .dark .do-pill[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
        .dark .do-tag.is-food, .dark .do-status.is-paid { background: rgba(127, 163, 138, 0.18); color: var(--primary-300); }
        .dark .do-status.is-owed { background: rgba(220, 38, 38, 0.18); color: var(--danger-300); }
        .dark .do-owed { background: rgba(220, 38, 38, 0.15); border-color: rgba(220, 38, 38, 0.35); color: var(--danger-300); }
        .dark .do-table tbody tr:hover { background: var(--gray-800); }
        .dark .do-table tr.do-is-owed { background: rgba(220, 38, 38, 0.08); }
        .dark .do-table .do-code { color: var(--primary-300); }
        .dark .do-table .do-details, .dark .do-empty { color: var(--gray-300); }
        .dark .do-table td.do-zero { color: var(--gray-500); }
    </style>

    <x-filament::section>
        <div class="do-head">
            <div class="do-bar">
                <div class="do-nav">
                    <x-filament::button color="gray" icon="heroicon-m-chevron-left" wire:click="shiftDay(-1)" label-sr-only>Hari sebelumnya</x-filament::button>
                    <label for="do-date">Tanggal</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="do-date" type="date" wire:model.live="date" />
                    </x-filament::input.wrapper>
                    <x-filament::button color="gray" icon="heroicon-m-chevron-right" wire:click="shiftDay(1)" label-sr-only>Hari berikutnya</x-filament::button>
                    @unless ($isToday)
                        <x-filament::button color="gray" wire:click="$set('date', '{{ now(config('app.timezone'))->toDateString() }}')">Kembali ke hari ini</x-filament::button>
                    @endunless
                </div>
                <div class="do-title">
                    <strong>{{ $dayLabel }}</strong>
                    <span>Tamu yang menginap malam ini dan makanan yang dijual tanpa booking</span>
                </div>
            </div>

            <dl class="do-sums">
                <div class="do-sum"><dt>Order</dt><dd>{{ $total }}</dd></div>
                <div class="do-sum"><dt>Total tagihan</dt><dd>{{ $rupiah($billTotal) }}</dd></div>
                <div class="do-sum"><dt>Sudah dibayar</dt><dd>{{ $rupiah($paidTotal) }}</dd></div>
                <div @class(['do-sum', 'is-owed' => $owedTotal > 0])><dt>Belum dibayar</dt><dd>{{ $rupiah($owedTotal) }}</dd></div>
            </dl>

            <div class="do-pills" role="group" aria-label="Saring jenis order">
                <button type="button" class="do-pill" aria-pressed="{{ $category === '' ? 'true' : 'false' }}" wire:click="$set('category', '')">
                    Semua <span class="do-count">{{ $total }}</span>
                </button>
                @foreach ($labels as $key => $label)
                    @php $count = $counts[$key] ?? 0; @endphp
                    <button type="button" @class(['do-pill', 'is-empty' => $count === 0]) aria-pressed="{{ $category === $key ? 'true' : 'false' }}" wire:click="$set('category', '{{ $key }}')">
                        {{ $label }} <span class="do-count">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            @if ($owedCount > 0)
                <p class="do-owed" role="status">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 shrink-0" />
                    <span>{{ $owedCount }} order belum lunas, total sisa {{ $rupiah($owedTotal) }}. Tagih sebelum tamu check-out. Klik kodenya untuk mencatat pembayaran.</span>
                </p>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section>
        <div wire:loading.delay class="do-empty">Memuat order {{ $dayLabel }}...</div>
        <div wire:loading.delay.remove>
            @if ($rows->isEmpty())
                <div class="do-empty">
                    @if ($category === '')
                        <strong>Belum ada order di tanggal ini</strong>
                        Tidak ada tamu menginap dan tidak ada pesanan makanan tanpa booking. Pilih tanggal lain dengan tombol panah.
                    @else
                        <strong>Tidak ada order {{ strtolower($labels[$category]) }}</strong>
                        Pilih Semua untuk melihat order lain di tanggal ini.
                    @endif
                </div>
            @else
                <div class="do-scroll" tabindex="0" role="region" aria-label="Daftar order {{ $dayLabel }}">
                    <table class="do-table">
                        <thead>
                            <tr>
                                <th scope="col">Kode</th>
                                <th scope="col">Jenis</th>
                                <th scope="col">Tenda</th>
                                <th scope="col">Rincian</th>
                                <th scope="col">Tagihan</th>
                                <th scope="col">Dibayar</th>
                                <th scope="col">Sisa</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="do-{{ $row['code'] }}" @class(['do-is-owed' => $row['owed'] > 0])>
                                    <td>
                                        <a class="do-code" href="{{ $urlFor($row) }}">{{ $row['code'] }}</a>
                                        <span class="do-sub">{{ $row['name'] }}</span>
                                    </td>
                                    <td><span @class(['do-tag', 'is-food' => $row['category'] !== \App\Services\DailyOrdersService::TENT && $row['category'] !== \App\Services\DailyOrdersService::TENT_ADDON])>{{ $labels[$row['category']] }}</span></td>
                                    <td>{{ $row['tents'] }}</td>
                                    <td class="do-details">{{ $row['details'] === [] ? '-' : implode('; ', $row['details']) }}</td>
                                    <td class="do-num">{{ $rupiah($row['bill']) }}</td>
                                    <td class="do-num">{{ $rupiah($row['paid']) }}</td>
                                    <td @class(['do-num', 'do-owed-cell' => $row['owed'] > 0, 'do-zero' => $row['owed'] === 0])>{{ $rupiah($row['owed']) }}</td>
                                    <td><span @class(['do-status', 'is-owed' => $row['owed'] > 0, 'is-paid' => $row['owed'] === 0 && in_array($row['status'], ['Lunas', 'Check-in', 'Check-out'], true)])>{{ $row['status'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
