<x-filament-panels::page>
    <style>
        .qr-sheet { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); }
        .qr-card { background: #fff; color: #1f2a22; border: 2px solid #1c3526; border-radius: 1rem; padding: 1.25rem; text-align: center; break-inside: avoid; }
        .qr-card h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 1.5rem; font-weight: 700; margin: 0 0 0.25rem; }
        .qr-card p { margin: 0.5rem 0 0; font-size: 0.8125rem; }
        .qr-card .qr-hint { font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; font-size: 0.75rem; color: #b3441f; }
        .qr-card .qr-url { word-break: break-all; font-size: 0.6875rem; color: #4a5a4f; }
        .qr-card svg { width: 100%; height: auto; max-width: 13rem; margin: 0.75rem auto 0; display: block; }
        @media print {
            .fi-sidebar, .fi-topbar, .fi-header, .fi-breadcrumbs, .qr-toolbar { display: none !important; }
            .fi-main-ctn, .fi-main { margin: 0 !important; padding: 0 !important; max-width: none !important; }
            .qr-sheet { grid-template-columns: repeat(2, 1fr); }
        }
    </style>

    <div class="qr-toolbar">
        <x-filament::button icon="heroicon-o-printer" onclick="window.print()">Cetak</x-filament::button>
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
</x-filament-panels::page>
