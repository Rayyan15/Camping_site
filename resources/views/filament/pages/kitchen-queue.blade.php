@php
    use App\Enums\OrderStatus;
    use App\Models\Order;

    $sourceLabels = [
        Order::SOURCE_PREORDER => 'Pre-order',
        Order::SOURCE_QR => 'QR',
        Order::SOURCE_WALKIN => 'Walk-in',
    ];
    $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
@endphp

<x-filament-panels::page>
    <style>
        .kq-board { display: grid; gap: 1rem; grid-template-columns: repeat(4, minmax(16rem, 1fr)); overflow-x: auto; align-items: start; }
        .kq-column { background: var(--gray-100); border-radius: 0.75rem; padding: 0.75rem; min-width: 16rem; }
        .kq-column-head { display: flex; align-items: center; justify-content: space-between; padding: 0.25rem 0.25rem 0.75rem; font-weight: 700; font-size: 0.8125rem; letter-spacing: 0.06em; text-transform: uppercase; }
        .kq-count { background: var(--gray-900); color: #fff; border-radius: 999px; padding: 0 0.625rem; font-size: 0.75rem; line-height: 1.5rem; }
        .kq-card { background: #fff; border: 1px solid var(--gray-200); border-left: 4px solid var(--primary-600); border-radius: 0.5rem; padding: 0.75rem; margin-bottom: 0.625rem; }
        .kq-card[data-late="true"] { border-left-color: var(--danger-600); }
        .kq-top { display: flex; justify-content: space-between; gap: 0.5rem; align-items: baseline; }
        .kq-code { font-weight: 700; font-size: 0.875rem; font-variant-numeric: tabular-nums; }
        .kq-age { font-size: 0.75rem; color: var(--gray-600); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .kq-who { margin-top: 0.125rem; font-size: 0.8125rem; color: var(--gray-700); }
        .kq-items { margin: 0.5rem 0; padding: 0; list-style: none; font-size: 0.875rem; }
        .kq-items li { padding: 0.125rem 0; }
        .kq-note { display: block; font-size: 0.75rem; color: var(--gray-600); }
        .kq-meta { display: flex; flex-wrap: wrap; gap: 0.375rem; align-items: center; margin-bottom: 0.625rem; }
        .kq-actions { display: flex; flex-wrap: wrap; gap: 0.375rem; }
        .kq-empty { padding: 1rem 0.25rem; font-size: 0.8125rem; color: var(--gray-500); }
        .kq-done { margin-top: 1rem; }
        .kq-done summary { cursor: pointer; font-weight: 700; padding: 0.5rem 0; }
        .kq-done-list { display: grid; gap: 0.5rem; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); margin-top: 0.5rem; }
        .dark .kq-column { background: var(--gray-900); }
        .dark .kq-card { background: var(--gray-800); border-color: var(--gray-700); border-left-color: var(--primary-500); }
        .dark .kq-who, .dark .kq-age, .dark .kq-note { color: var(--gray-400); }
        .dark .kq-count { background: var(--gray-100); color: var(--gray-900); }
        @media (max-width: 1100px) { .kq-board { grid-template-columns: repeat(4, 16rem); } }
        @media (prefers-reduced-motion: no-preference) { .kq-card { animation: kq-in 0.25s ease-out; } }
        @keyframes kq-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    </style>

    <div wire:poll.5s>
        <div class="kq-board">
            @foreach($columns as $status)
                <section class="kq-column" aria-labelledby="kq-{{ $status->value }}">
                    <h2 class="kq-column-head" id="kq-{{ $status->value }}">
                        <span>{{ $status->label() }}</span>
                        <span class="kq-count">{{ $board[$status->value]->count() }}</span>
                    </h2>

                    @forelse($board[$status->value] as $order)
                        @include('filament.pages.partials.kitchen-card', ['order' => $order, 'sourceLabels' => $sourceLabels, 'rupiah' => $rupiah, 'canProcess' => $canProcess, 'lateAfterMinutes' => $lateAfterMinutes, 'showActions' => true])
                    @empty
                        <p class="kq-empty">Tidak ada pesanan.</p>
                    @endforelse
                </section>
            @endforeach
        </div>

        <details class="kq-done">
            <summary>Selesai hari ini ({{ $board[OrderStatus::Selesai->value]->count() }})</summary>
            <div class="kq-done-list">
                @foreach($board[OrderStatus::Selesai->value] as $order)
                    @include('filament.pages.partials.kitchen-card', ['order' => $order, 'sourceLabels' => $sourceLabels, 'rupiah' => $rupiah, 'canProcess' => $canProcess, 'lateAfterMinutes' => $lateAfterMinutes, 'showActions' => false])
                @endforeach
            </div>
        </details>
    </div>
</x-filament-panels::page>
