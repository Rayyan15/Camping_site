@props(['search' => [], 'today'])

@php
    $checkIn = old('check_in', $search['check_in'] ?? '');
    $checkOut = old('check_out', $search['check_out'] ?? '');
    $guests = old('guests', $search['guests'] ?? 2);
@endphp

<form id="cari" action="{{ route('home') }}#tenda" method="GET" novalidate
      data-stay-search data-today="{{ $today }}" data-check-in="{{ $checkIn }}" data-check-out="{{ $checkOut }}" data-guests="{{ $guests }}"
      class="relative w-full max-w-3xl text-ink" aria-label="Cek ketersediaan tenda">
    @if($errors->any())
        <ul class="mb-3 rounded-2xl bg-ember-soft px-4 py-3 text-sm font-semibold text-ember-dark" role="alert">
            @foreach($errors->unique() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <input type="hidden" name="check_in" value="{{ $checkIn }}" data-ss-input="check_in">
    <input type="hidden" name="check_out" value="{{ $checkOut }}" data-ss-input="check_out">
    <input type="hidden" name="guests" value="{{ $guests }}" data-ss-input="guests">

    <noscript>
        <div class="grid gap-3 rounded-3xl bg-cream p-5 sm:grid-cols-3">
            <div>
                <label for="ns_check_in" class="field-label">Check-in</label>
                <input id="ns_check_in" type="date" name="check_in" min="{{ $today }}" value="{{ $checkIn }}" class="field-input">
            </div>
            <div>
                <label for="ns_check_out" class="field-label">Check-out</label>
                <input id="ns_check_out" type="date" name="check_out" value="{{ $checkOut }}" class="field-input">
            </div>
            <div>
                <label for="ns_guests" class="field-label">Tamu</label>
                <input id="ns_guests" type="number" name="guests" min="1" max="50" value="{{ $guests }}" class="field-input">
            </div>
            <button type="submit" class="btn btn-primary sm:col-span-3">Cari tenda kosong</button>
        </div>
    </noscript>

    <div class="stay-search-ui">
        <div class="flex flex-col gap-1 rounded-3xl bg-cream p-2 shadow-2xl shadow-forest-950/40 md:flex-row md:items-center md:gap-0 md:rounded-full">
            <button type="button" data-ss-trigger="check_in" aria-haspopup="dialog" aria-expanded="false" aria-controls="ss-dates" class="ss-segment md:flex-1">
                <span class="ss-label">Check-in</span>
                <span class="ss-value" data-ss-value="check_in"></span>
            </button>
            <span class="mx-4 h-px bg-sand md:mx-0 md:h-8 md:w-px" aria-hidden="true"></span>
            <button type="button" data-ss-trigger="check_out" aria-haspopup="dialog" aria-expanded="false" aria-controls="ss-dates" class="ss-segment md:flex-1">
                <span class="ss-label">Check-out</span>
                <span class="ss-value" data-ss-value="check_out"></span>
            </button>
            <span class="mx-4 h-px bg-sand md:mx-0 md:h-8 md:w-px" aria-hidden="true"></span>
            <button type="button" data-ss-trigger="guests" aria-haspopup="dialog" aria-expanded="false" aria-controls="ss-guests" class="ss-segment md:flex-1">
                <span class="ss-label">Tamu</span>
                <span class="ss-value" data-ss-value="guests"></span>
            </button>
            <button type="submit" class="mt-1 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-ember font-bold text-white transition hover:bg-ember-dark md:ml-2 md:mt-0 md:w-12 md:shrink-0">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
                <span class="md:sr-only">Cari tenda</span>
            </button>
        </div>

        <div id="ss-dates" data-ss-popover="dates" role="dialog" aria-label="Pilih tanggal menginap" hidden class="ss-popover ss-popover-dates">
            <div class="cal-nav">
                <button type="button" data-ss-prev class="ss-nav" aria-label="Bulan sebelumnya">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                </button>
                <button type="button" data-ss-next class="ss-nav" aria-label="Bulan berikutnya">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>
            <div data-ss-months class="cal-months"></div>
            <div class="mt-3 flex items-center justify-between border-t border-sand pt-3 text-sm">
                <p class="font-semibold text-ink-soft" data-ss-nights aria-live="polite"></p>
                <button type="button" data-ss-clear class="font-bold text-forest-800 underline underline-offset-4">Hapus tanggal</button>
            </div>
        </div>

        <div id="ss-guests" data-ss-popover="guests" role="dialog" aria-label="Jumlah tamu" hidden class="ss-popover ss-popover-guests">
            <div class="flex items-center justify-between gap-6">
                <div>
                    <p class="font-bold text-forest-900">Tamu</p>
                    <p class="text-sm text-ink-soft">Maksimal 12</p>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" data-ss-minus class="ss-step" aria-label="Kurangi tamu">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M5 12h14"/></svg>
                    </button>
                    <output data-ss-guest-count class="w-6 text-center text-lg font-bold" aria-live="polite"></output>
                    <button type="button" data-ss-plus class="ss-step" aria-label="Tambah tamu">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
