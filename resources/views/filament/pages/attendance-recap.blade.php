<x-filament-panels::page>
    <style>
        .ar-controls { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; justify-content: space-between; }
        .ar-field { min-width: 14rem; }
        .ar-field label { display: block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 600; }
        .ar-rule { max-width: 42rem; font-size: 0.875rem; line-height: 1.5; color: var(--gray-700); }
        .ar-scroll { overflow-x: auto; }
        .ar-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .ar-table caption { text-align: left; padding-bottom: 0.5rem; font-size: 0.875rem; color: var(--gray-700); }
        .ar-table th, .ar-table td { padding: 0.625rem 0.75rem; text-align: right; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        .ar-table th:first-child, .ar-table td:first-child { text-align: left; }
        .ar-table th { font-size: 0.8125rem; font-weight: 700; color: var(--gray-700); }
        .ar-table td.ar-alpa { color: var(--danger-700); font-weight: 700; }
        .ar-pct { display: inline-flex; align-items: center; gap: 0.5rem; justify-content: flex-end; }
        .ar-bar { width: 4.5rem; height: 0.375rem; background: var(--gray-200); border-radius: 0.125rem; overflow: hidden; }
        .ar-bar > span { display: block; height: 100%; background: var(--primary-700); }
        .ar-empty { padding: 1.5rem 0.25rem; font-size: 0.875rem; color: var(--gray-700); max-width: 36rem; }
        .dark .ar-rule, .dark .ar-table caption, .dark .ar-table th, .dark .ar-empty { color: var(--gray-300); }
        .dark .ar-table th, .dark .ar-table td { border-bottom-color: var(--gray-700); }
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
                            <th scope="col">Karyawan</th>
                            <th scope="col">Hari kerja</th>
                            <th scope="col">Hadir</th>
                            <th scope="col">Terlambat</th>
                            <th scope="col">Izin</th>
                            <th scope="col">Sakit</th>
                            <th scope="col">Alpa</th>
                            <th scope="col">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <th scope="row">{{ $row->employeeName }}</th>
                                <td>{{ $row->workdays }}</td>
                                <td>{{ $row->present }}</td>
                                <td>{{ $row->late }}</td>
                                <td>{{ $row->excused }}</td>
                                <td>{{ $row->sick }}</td>
                                <td class="ar-alpa">{{ $row->absent }}</td>
                                <td>
                                    @if ($row->attendancePercentage === null)
                                        Belum ada hari kerja
                                    @else
                                        <span class="ar-pct">
                                            <span class="ar-bar" aria-hidden="true"><span style="width: {{ $row->attendancePercentage }}%"></span></span>
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
