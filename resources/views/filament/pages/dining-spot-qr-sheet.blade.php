<div class="qr-print">
    <style>
        .qr-print { color-scheme: light; background: #fff; color: #1f2a24; min-height: 100vh; padding: 1.5rem 1rem; }
        .qr-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between; max-width: 60rem; margin: 0 auto 1.25rem; }
        .qr-toolbar a { color: #1f4d36; font-weight: 600; text-decoration: underline; min-height: 44px; display: inline-flex; align-items: center; }
        .qr-toolbar a:focus-visible, .qr-toolbar button:focus-visible { outline: 3px solid #1f4d36; outline-offset: 2px; }
        .qr-toolbar button { background: #1f4d36; color: #fff; border: 0; border-radius: 0.5rem; padding: 0 1.25rem; min-height: 44px; font-weight: 600; cursor: pointer; }
        .qr-sheet { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); max-width: 60rem; margin: 0 auto; }
        .qr-card { background: #fff; color: #1f2a24; border: 2px solid #1f4d36; border-radius: 1rem; padding: 1.25rem; text-align: center; break-inside: avoid; page-break-inside: avoid; }
        .qr-card h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 1.5rem; font-weight: 700; margin: 0 0 0.25rem; }
        .qr-card p { margin: 0.5rem 0 0; font-size: 0.8125rem; }
        .qr-card .qr-hint { font-weight: 700; font-size: 0.8125rem; color: #9a3412; }
        .qr-card .qr-url { word-break: break-all; font-size: 0.6875rem; color: #4b5563; }
        .qr-card svg { width: 100%; height: auto; max-width: 13rem; margin: 0.75rem auto 0; display: block; }
        @media print {
            @page { margin: 10mm; }
            .qr-print { padding: 0; min-height: 0; }
            .qr-toolbar { display: none; }
            .qr-sheet { grid-template-columns: repeat(2, 1fr); gap: 8mm; }
            .qr-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    <div class="qr-toolbar">
        <a href="{{ \App\Filament\Resources\DiningSpots\DiningSpotResource::getUrl('index') }}">Kembali ke Titik QR</a>
        <button type="button" onclick="window.print()">Cetak</button>
    </div>

    <div class="qr-sheet">
        @forelse($sheets as $sheet)
            <article class="qr-card">
                <h2>{{ $sheet['spot']->name }}</h2>
                <p class="qr-hint">Pindai untuk pesan makanan</p>
                {!! $sheet['svg'] !!}
                <p class="qr-url">{{ $sheet['spot']->orderUrl() }}</p>
            </article>
        @empty
            <p>Belum ada titik QR.</p>
        @endforelse
    </div>
</div>
