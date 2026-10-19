<x-filament-panels::page>
    <style>
        .ar-controls { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; justify-content: space-between; }
        .ar-field { min-width: 14rem; }
        .ar-field label { display: block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 600; }
        .ar-rule { max-width: 42rem; font-size: 0.875rem; line-height: 1.5; color: var(--gray-700); }
        /* Counts are centred under short headers so a row reads left to right like a register; zeros fade so real days stand out. */
        .ar-scroll { overflow-x: auto; border: 1px solid var(--gray-200); border-radius: 0.75rem; }
        .ar-table { width: 100%; border-collapse: separate; border-spacing: 0; font-variant-numeric: tabular-nums; font-size: 0.875rem; }
        .ar-table caption { text-align: left; padding: 0.875rem; font-size: 1rem; font-weight: 700; color: var(--gray-950); }
        .ar-table th, .ar-table td { padding: 0.75rem 0.875rem; text-align: center; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        .ar-table tbody tr:last-child > * { border-bottom: 0; }
        .ar-table thead th { background: var(--gray-50); font-size: 0.75rem; font-weight: 700; color: var(--gray-700); }
        .ar-table thead th { padding-block: 0.625rem; vertical-align: bottom; border-bottom: 1px solid var(--gray-300); line-height: 1.25; }
        .ar-table .ar-split { border-right: 1px solid var(--gray-200); }
        .ar-table .ar-pct-cell.ar-split { border-right: 0; border-left: 1px solid var(--gray-200); }
        .ar-table .ar-name { position: sticky; left: 0; z-index: 1; text-align: left; background: #fff; font-weight: 600; color: var(--gray-950); }
        .ar-table thead .ar-name { background: var(--gray-50); z-index: 2; }
        .ar-table tbody tr:hover > * { background: var(--gray-50); }
        .ar-table td.ar-zero { color: var(--gray-500); }
        .ar-table td.ar-alpa { color: var(--danger-700); font-weight: 700; }
        .ar-table .ar-pct-cell { text-align: right; min-width: 9.5rem; }
        .ar-pct { display: inline-flex; align-items: center; gap: 0.625rem; justify-content: flex-end; font-weight: 700; color: var(--gray-950); }
        .ar-bar { width: 4.5rem; height: 0.375rem; background: var(--gray-200); border-radius: 999px; overflow: hidden; }
        .ar-bar > span { display: block; height: 100%; background: var(--primary-700); border-radius: inherit; }
        .ar-muted { font-size: 0.8125rem; color: var(--gray-600); }
        .dark .ar-scroll { border-color: var(--gray-700); }
        .dark .ar-table caption, .dark .ar-table .ar-name, .dark .ar-pct { color: #fff; }
        .dark .ar-table thead th, .dark .ar-table thead .ar-name { background: var(--gray-800); color: var(--gray-300); }
        .dark .ar-table .ar-name { background: var(--gray-900); }
        .dark .ar-table tbody tr:hover > * { background: var(--gray-800); }
        .dark .ar-table td.ar-zero, .dark .ar-muted { color: var(--gray-400); }
        .ar-empty { padding: 1.5rem 0.25rem; font-size: 0.875rem; color: var(--gray-700); max-width: 36rem; }
        .dark .ar-rule, .dark .ar-empty { color: var(--gray-300); }
        .dark .ar-table th, .dark .ar-table td, .dark .ar-table .ar-split { border-color: var(--gray-700); }
        .dark .ar-table td.ar-alpa { color: var(--danger-400); }
        .dark .ar-bar { background: var(--gray-700); }
        .dark .ar-bar > span { background: var(--primary-400); }
    </style>

    <x-filament::section>
        <div class="ar-controls">
            <div class="ar-field">
                <label for="ar-month">Periode</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="ar-month" wire:model.live="month">
                        @foreach ($monthOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <p class="ar-rule">
                Hari kerja dihitung setiap hari kalender dalam bulan, mulai dari tanggal data karyawan dibuat sampai hari ini,
                karena sistem belum menyimpan jadwal libur. Hari kerja tanpa catatan masuk dihitung alpa.
                Persentase kehadiran = (hadir + terlambat) dibagi hari kerja.
            </p>
        </div>
    </x-filament::section>

    <x-filament::section>
        @if ($rows->isEmpty())
            <p class="ar-empty">
                @if ($seesEveryone)
                    Belum ada karyawan. Tambahkan data di menu Data Karyawan, lalu rekap muncul di sini.
                @else
                    Akun Anda belum terhubung ke data karyawan, jadi belum ada rekap untuk ditampilkan. Minta pemilik menghubungkannya di Data Karyawan.
                @endif
            </p>
        @else
            <div class="ar-scroll" tabindex="0" role="region" aria-label="Tabel rekap kehadiran">
                <table class="ar-table">
                    <caption>Rekap kehadiran {{ $monthOptions[$month] ?? $month }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="ar-name">Karyawan</th>
                            <th scope="col" class="ar-split">Hari kerja</th>
                            <th scope="col">Hadir</th>
                            <th scope="col">Terlambat</th>
                            <th scope="col">Izin</th>
                            <th scope="col">Sakit</th>
                            <th scope="col">Alpa</th>
                            <th scope="col" class="ar-pct-cell ar-split">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="ar-{{ $loop->index }}">
                                <th scope="row" class="ar-name">{{ $row->employeeName }}</th>
                                <td class="ar-split">{{ $row->workdays }}</td>
                                @foreach ([$row->present, $row->late, $row->excused, $row->sick] as $count)
                                    <td @class(['ar-zero' => $count === 0])>{{ $count }}</td>
                                @endforeach
                                <td @class(['ar-alpa' => $row->absent > 0, 'ar-zero' => $row->absent === 0])>{{ $row->absent }}</td>
                                <td class="ar-pct-cell ar-split">
                                    @if ($row->attendancePercentage === null)
                                        <span class="ar-muted">Belum ada hari kerja</span>
                                    @else
                                        <span class="ar-pct">
                                            <span class="ar-bar" aria-hidden="true"><span style="width: {{ min(100, $row->attendancePercentage) }}%"></span></span>
                                            {{ number_format($row->attendancePercentage, 1, ',', '.') }}%
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
