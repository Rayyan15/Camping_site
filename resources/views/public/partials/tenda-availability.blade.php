{{-- Calendar and "Cek ketersediaan" form. The form works alone; the calendar is an enhancement over the same two date fields. --}}
<section id="ketersediaan" aria-labelledby="judul-ketersediaan" class="mt-20 lg:mt-28">
    <div class="max-w-2xl" data-reveal>
        <p class="font-display text-xl italic text-ember-dark">Pilih malam</p>
        <h2 id="judul-ketersediaan" class="font-display mt-2 text-4xl font-semibold leading-[1.05] sm:text-5xl">Kapan {{ $unitType->name }} masih kosong?</h2>
        <p class="mt-4 text-ink-soft">Tanggal yang dicoret sudah lewat. Tanggal berpola garis penuh untuk semua {{ $unitType->units_count }} unit, baik karena dipesan, ditahan {{ config('booking.hold_minutes') }} menit saat checkout, maupun diblokir pengelola.</p>
    </div>

    <div class="mt-10 grid gap-8 lg:grid-cols-[1.6fr_1fr] lg:items-start lg:gap-10">
        <div
            data-avail-cal
            data-endpoint="{{ route('tenda.ketersediaan', ['slug' => $unitType->slug]) }}"
            data-today="{{ $today }}"
            data-max-advance="{{ $maxAdvanceDays }}"
            data-max-nights="{{ $maxNights }}"
            data-check-in-id="check_in"
            data-check-out-id="check_out"
            data-estimate="[data-avail-estimate]"
            class="avail-cal rounded-3xl border border-sand bg-[#fffdf8] p-4 sm:p-6"
            data-reveal
        >
            <noscript>
                <p class="text-sm font-semibold text-ink-soft">Kalender membutuhkan JavaScript. Isi kolom check-in dan check-out pada formulir di samping, lalu tekan Cek dan pesan. Hasilnya sama.</p>
            </noscript>

            <div class="avail-cal-ui">
                <div class="flex items-center justify-between gap-3">
                    <button type="button" data-cal-prev class="avail-nav" aria-label="Bulan sebelumnya">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <p class="text-center text-sm font-semibold text-ink-soft" data-cal-detail>Arahkan atau fokuskan ke sebuah tanggal untuk melihat sisa unitnya.</p>
                    <button type="button" data-cal-next class="avail-nav" aria-label="Bulan berikutnya">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>

                <div class="avail-months mt-4" data-cal-months></div>

                <p class="mt-3 text-sm font-semibold text-ink-soft" data-cal-loading hidden>Memuat ketersediaan...</p>
                <div class="mt-3 flex flex-wrap items-center gap-3 rounded-xl bg-ember-soft px-4 py-3 text-sm font-semibold text-ember-dark" data-cal-error hidden>
                    <span>Ketersediaan belum bisa dimuat. Tanggal tetap bisa dipilih, dan pengecekan dilakukan saat Anda menekan Cek dan pesan.</span>
                    <button type="button" data-cal-retry class="btn btn-outline !min-h-11 !px-4">Coba lagi</button>
                </div>

                <p class="mt-4 min-h-6 text-sm font-semibold text-forest-900" role="status" data-cal-status></p>

                <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-t border-sand pt-4 text-xs font-semibold text-ink-soft" aria-label="Keterangan kalender">
                    <li class="flex items-center gap-2"><span class="avail-key"></span>Tersedia</li>
                    <li class="flex items-center gap-2"><span class="avail-key avail-key-limited"></span>Sisa {{ \App\Services\AvailabilityCalendarService::LIMITED_STOCK_THRESHOLD }} unit atau kurang</li>
                    <li class="flex items-center gap-2"><span class="avail-key avail-key-full"></span>Penuh</li>
                    <li class="flex items-center gap-2"><span class="avail-key avail-key-today"></span>Hari ini</li>
                    <li class="flex items-center gap-2"><span class="avail-key avail-key-picked"></span>Dipilih</li>
                </ul>
            </div>
        </div>

        <form action="{{ route('booking.cek') }}" method="GET" class="rounded-3xl border border-sand bg-[#fffdf8] p-5 sm:p-6 lg:sticky lg:top-24" data-reveal data-reveal-delay="100">
            <h3 class="font-display text-2xl font-semibold text-forest-900">Cek ketersediaan</h3>
            <input type="hidden" name="unit_type_id" value="{{ $unitType->id }}">

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <label for="check_in" class="field-label">Check-in</label>
                    <input id="check_in" type="date" name="check_in" value="{{ request('check_in', $today) }}" min="{{ $today }}" max="{{ \Carbon\Carbon::parse($today)->addDays($maxAdvanceDays)->toDateString() }}" required class="field-input">
                </div>
                <div>
                    <label for="check_out" class="field-label">Check-out</label>
                    <input id="check_out" type="date" name="check_out" value="{{ request('check_out', $tomorrow) }}" min="{{ $tomorrow }}" required class="field-input">
                </div>
            </div>
            <div class="mt-3">
                <label for="guests" class="field-label">Jumlah tamu</label>
                <input id="guests" type="number" name="guests" min="1" max="{{ $unitType->capacity + 2 }}" value="{{ request('guests', 2) }}" required aria-describedby="guests-hint" class="field-input">
                <p id="guests-hint" class="mt-2 text-xs text-ink-soft">Kapasitas dasar {{ $unitType->capacity }} orang. Lebih dari itu perlu extra bed.</p>
            </div>

            <div class="mt-4 rounded-2xl bg-cream-deep px-4 py-3" role="status" data-avail-estimate data-state="hint">
                <p class="text-sm font-bold text-forest-900" data-est-main>Pilih check-in dan check-out untuk melihat estimasi sewa.</p>
                <p class="mt-1 text-xs text-ink-soft" data-est-note></p>
            </div>

            @if($errors->any())
                <ul class="mt-3 rounded-xl bg-ember-soft px-4 py-3 text-sm font-semibold text-ember-dark" role="alert">
                    @foreach($errors->unique() as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
            <button type="submit" class="btn btn-primary mt-5 w-full">Cek dan pesan</button>
        </form>
    </div>
</section>
