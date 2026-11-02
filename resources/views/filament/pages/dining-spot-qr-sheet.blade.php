@php
    $printUrl = fn (?string $type = null) => \App\Filament\Resources\DiningSpots\DiningSpotResource::getUrl('print', array_filter(['type' => $type]));
    $activeType = $this->spot ? null : ($this->type ?? '');
@endphp

<div class="qr-print">
    <style>
        .qr-print { color-scheme: light; background: #f6f5f0; color: #1f2a24; min-height: 100vh; padding: 1.5rem 1rem 3rem; }
        .qr-head { max-width: 60rem; margin: 0 auto 1.5rem; display: grid; gap: 1rem; }
        .qr-head-top { display: flex; flex-wrap: wrap; gap: 0.75rem 1.5rem; align-items: center; justify-content: space-between; }
        .qr-back { color: #1f4d36; font-weight: 600; text-decoration: none; min-height: 44px; display: inline-flex; align-items: center; gap: 0.375rem; }
        .qr-back:hover { text-decoration: underline; }
        .qr-head h1 { font-family: Georgia, 'Times New Roman', serif; font-size: 1.75rem; margin: 0; }
        .qr-head p { margin: 0.25rem 0 0; color: #4b5563; font-size: 0.9375rem; }
        .qr-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .qr-chip { display: inline-flex; align-items: center; min-height: 44px; padding: 0 1rem; border-radius: 999px; border: 1px solid #c9cfc9; background: #fff; color: #1f2a24; font-weight: 600; font-size: 0.875rem; text-decoration: none; }
        .qr-chip[aria-current="page"] { background: #1f4d36; border-color: #1f4d36; color: #fff; }
        .qr-btn { background: #1f4d36; color: #fff; border: 0; border-radius: 0.5rem; padding: 0 1.25rem; min-height: 44px; font-weight: 600; font-size: 0.9375rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .qr-btn-ghost { background: #fff; color: #1f4d36; border: 1px solid #1f4d36; }
        .qr-print a:focus-visible, .qr-print button:focus-visible { outline: 3px solid #9a3412; outline-offset: 2px; }
        .qr-sheet { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fill, minmax(min(15rem, 100%), 17rem)); justify-content: start; max-width: 60rem; margin: 0 auto; }
        .qr-card { background: #fff; color: #1f2a24; border: 2px solid #1f4d36; border-radius: 1rem; padding: 1.25rem 1rem 1rem; text-align: center; break-inside: avoid; page-break-inside: avoid; }
        .qr-card h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 1.5rem; font-weight: 700; margin: 0; }
        .qr-card .qr-hint { margin: 0.375rem 0 0; font-weight: 700; font-size: 0.875rem; color: #9a3412; }
        .qr-card svg { width: 100%; height: auto; max-width: 12rem; margin: 0.75rem auto; display: block; }
        .qr-card .qr-brand { margin: 0; padding-top: 0.625rem; border-top: 1px dashed #c9cfc9; font-size: 0.75rem; font-weight: 600; color: #4b5563; }
        .qr-icon { width: 1.25rem; height: 1.25rem; flex: none; }
        .qr-head, .qr-head-top > * { min-width: 0; }
        .qr-print { overflow-x: hidden; }
        .qr-empty { text-align: center; color: #4b5563; padding: 3rem 1rem; }
        @media print {
            @page { margin: 10mm; }
            .qr-print { background: #fff; padding: 0; min-height: 0; }
            .qr-head { display: none; }
            .qr-sheet { grid-template-columns: repeat(2, 1fr); gap: 8mm; max-width: none; }
            .qr-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    <header class="qr-head">
        <div class="qr-head-top">
            <a class="qr-back" href="{{ \App\Filament\Resources\DiningSpots\DiningSpotResource::getUrl('index') }}">
                <x-filament::icon icon="heroicon-m-arrow-left" class="qr-icon" aria-hidden="true" />
                Kembali ke Titik QR
            </a>
            <button type="button" class="qr-btn" onclick="window.print()" @disabled($sheets->isEmpty())>
                <x-filament::icon icon="heroicon-o-printer" class="qr-icon" aria-hidden="true" />
                Cetak {{ $sheets->count() }} QR
            </button>
        </div>

        <div>
            <h1>Cetak QR</h1>
            <p>Empat kartu per halaman A4. Gunting di garis tepi, lalu tempel di meja atau tenda.</p>
        </div>

        <nav class="qr-actions" aria-label="Pilih QR yang dicetak">
            @foreach($filters as $value => $label)
                <a class="qr-chip" href="{{ $printUrl($value ?: null) }}" @if($activeType === $value) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            @if($this->spot)
                <a class="qr-btn qr-btn-ghost" href="{{ $printUrl() }}">Cetak semua QR</a>
            @endif
        </nav>
    </header>

    <div class="qr-sheet">
        @forelse($sheets as $sheet)
            <article class="qr-card">
                <h2>{{ $sheet['spot']->name }}</h2>
                <p class="qr-hint">Pindai untuk pesan makanan</p>
                {!! $sheet['svg'] !!}
                <p class="qr-brand">Halimun Highland</p>
            </article>
        @empty
            <p class="qr-empty">Belum ada titik QR. Tambahkan dulu di menu Titik QR.</p>
        @endforelse
    </div>
</div>
