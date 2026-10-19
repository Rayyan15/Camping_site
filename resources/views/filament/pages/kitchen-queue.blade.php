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
        /* The time chip is the one thing a cook reads first, so it carries the urgency colour instead of a border stripe. */
        .kq-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 0.75rem; padding: 0.875rem; margin-bottom: 0.625rem; display: grid; gap: 0.625rem; }
        .kq-time { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.25rem 0.5rem; margin: 0; padding: 0.375rem 0.5rem 0.375rem 0.625rem; border-radius: 0.5rem; background: var(--gray-100); font-variant-numeric: tabular-nums; }
        .kq-time-main { display: inline-flex; align-items: center; gap: 0.375rem; font-weight: 700; font-size: 0.8125rem; white-space: nowrap; color: var(--gray-950); }
        .kq-time-icon { width: 1rem; height: 1rem; flex-shrink: 0; }
        .kq-time-hint { font-size: 0.6875rem; font-weight: 600; white-space: nowrap; padding: 0.125rem 0.375rem; border-radius: 0.25rem; background: rgb(255 255 255 / 0.7); color: var(--gray-700); }
        .kq-card[data-tone="soon"] .kq-time { background: var(--warning-50); }
        .kq-card[data-tone="soon"] .kq-time-hint { color: var(--warning-800); }
        .kq-card[data-tone="late"] { border-color: var(--danger-300); }
        .kq-card[data-tone="late"] .kq-time { background: var(--danger-50); }
        .kq-card[data-tone="late"] .kq-time-main, .kq-card[data-tone="late"] .kq-time-hint { color: var(--danger-800); }
        .kq-code { font-size: 0.75rem; font-weight: 600; color: var(--gray-600); font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .kq-who { display: grid; gap: 0.125rem; font-size: 1rem; color: var(--gray-950); }
        .kq-place { font-size: 0.8125rem; color: var(--gray-600); }
        .kq-items { margin: 0; padding: 0.5rem 0; list-style: none; border-block: 1px solid var(--gray-100); display: grid; gap: 0.375rem; }
        .kq-items li { display: grid; grid-template-columns: 2.25rem 1fr; gap: 0.5rem; align-items: baseline; font-size: 0.9375rem; font-weight: 600; color: var(--gray-950); }
        .kq-qty { justify-self: start; padding: 0.0625rem 0.375rem; border-radius: 0.375rem; font-size: 0.8125rem; font-variant-numeric: tabular-nums; background: var(--primary-50); color: var(--primary-800); font-weight: 700; }
        .kq-note { display: block; font-size: 0.75rem; font-weight: 400; color: var(--gray-700); }
        .kq-meta { display: flex; flex-wrap: wrap; gap: 0.375rem; align-items: center; }
        .kq-actions { display: grid; grid-template-columns: 3fr 2fr; gap: 0.5rem; align-items: stretch; }
        .kq-actions > :only-child { grid-column: 1 / -1; }
        .kq-primary { width: 100%; min-height: 2.75rem; }
        .kq-secondary { display: flex; flex-wrap: wrap; gap: 0.375rem; }
        .kq-secondary:empty { display: none; }
        .kq-secondary > * { flex: 1 1 0; min-height: 2.75rem; }
        .dark .kq-card { background: var(--gray-800); border-color: var(--gray-700); }
        .dark .kq-time { background: var(--gray-900); }
        .dark .kq-time-main, .dark .kq-who, .dark .kq-items li { color: #fff; }
        .dark .kq-time-hint, .dark .kq-place, .dark .kq-note, .dark .kq-code { color: var(--gray-400); }
        .dark .kq-items { border-color: var(--gray-700); }
        .dark .kq-qty { background: var(--gray-900); color: var(--primary-300); }
        .dark .kq-time-hint { background: var(--gray-800); }
        .dark .kq-card[data-tone="late"] { border-color: var(--danger-700); }
        .dark .kq-card[data-tone="late"] .kq-time, .dark .kq-card[data-tone="soon"] .kq-time { background: var(--gray-900); }
        .dark .kq-card[data-tone="late"] .kq-time-main, .dark .kq-card[data-tone="late"] .kq-time-hint { color: var(--danger-300); }
        .dark .kq-card[data-tone="soon"] .kq-time-hint { color: var(--warning-300); }
        .kq-empty { padding: 1rem 0.25rem; font-size: 0.8125rem; color: var(--gray-500); }
        .kq-done { margin-top: 1rem; }
        .kq-done summary { cursor: pointer; font-weight: 700; padding: 0.5rem 0; }
        .kq-done-list { display: grid; gap: 0.5rem; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); margin-top: 0.5rem; }
        .dark .kq-column { background: var(--gray-900); }
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

        {{-- The board polls every 5 s; ignore.self keeps the open state the cook chose. --}}
        <details class="kq-done" wire:ignore.self>
            <summary>Selesai hari ini ({{ $board[OrderStatus::Selesai->value]->count() }})</summary>
            <div class="kq-done-list">
                @foreach($board[OrderStatus::Selesai->value] as $order)
                    @include('filament.pages.partials.kitchen-card', ['order' => $order, 'sourceLabels' => $sourceLabels, 'rupiah' => $rupiah, 'canProcess' => $canProcess, 'lateAfterMinutes' => $lateAfterMinutes, 'showActions' => false])
                @endforeach
            </div>
        </details>
    </div>
</x-filament-panels::page>
