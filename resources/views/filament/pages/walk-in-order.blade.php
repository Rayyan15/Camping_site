@php
    $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
    $total = collect($cart)->map(fn ($line, $id) => ($menuById[$id]->price ?? 0) * $line['qty'])->sum();
    $itemCount = collect($cart)->sum('qty');
@endphp

<x-filament-panels::page>
    <style>
        /* Cashier layout: menu to tap on the left, the running ticket on the right, so the order is never out of sight. */
        .wo { display: grid; gap: 1rem; align-items: start; }
        @media (min-width: 64rem) { .wo { grid-template-columns: minmax(0, 1fr) 24rem; } .wo-ticket { position: sticky; top: 5rem; max-height: calc(100vh - 6rem); overflow-y: auto; } }
        .wo-panel { background: #fff; border: 1px solid var(--gray-200); border-radius: 0.75rem; padding: 1rem; }
        .wo-tools { display: grid; gap: 0.75rem; margin-bottom: 1rem; }
        .wo-searchbar { position: relative; }
        .wo-search-icon { position: absolute; left: 0.875rem; top: 50%; translate: 0 -50%; width: 1.25rem; height: 1.25rem; color: var(--gray-600); pointer-events: none; }
        .wo-search { width: 100%; min-height: 3rem; padding: 0 1rem 0 2.75rem; border-radius: 999px; background: var(--gray-50); border: 1px solid var(--gray-300); font-size: 0.9375rem; color: var(--gray-950); }
        .wo-search:focus-visible, .wo-field:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 1px; }
        .wo-pills { display: flex; gap: 0.375rem; overflow-x: auto; padding-bottom: 0.125rem; }
        .wo-pill { flex-shrink: 0; min-height: 2.75rem; padding: 0 0.875rem; border-radius: 999px; border: 1px solid var(--gray-300); background: #fff; font-size: 0.875rem; font-weight: 600; color: var(--gray-700); }
        .wo-pill[aria-pressed="true"] { background: var(--primary-700); border-color: var(--primary-700); color: #fff; }
        .wo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); gap: 0.625rem; }
        .wo-item { position: relative; display: grid; grid-template-rows: auto 1fr; text-align: left; border: 1px solid var(--gray-200); border-radius: 0.625rem; background: #fff; overflow: hidden; transition: border-color 0.15s; }
        .wo-item:hover { border-color: var(--primary-400); }
        .wo-item:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .wo-item[data-picked] { border-color: var(--primary-600); box-shadow: 0 0 0 1px var(--primary-600); }
        .wo-photo { aspect-ratio: 4 / 3; width: 100%; object-fit: cover; background: var(--gray-100); }
        .wo-photo-empty { display: grid; place-items: center; color: var(--gray-700); font-size: 0.75rem; }
        .wo-item-body { display: grid; gap: 0.125rem; padding: 0.5rem 0.625rem 0.625rem; }
        .wo-item-name { font-size: 0.875rem; font-weight: 600; line-height: 1.3; color: var(--gray-950); }
        .wo-item-price { font-size: 0.8125rem; color: var(--gray-700); font-variant-numeric: tabular-nums; }
        .wo-count { position: absolute; top: 0.375rem; right: 0.375rem; min-width: 1.75rem; height: 1.75rem; padding: 0 0.375rem; display: grid; place-items: center; border-radius: 999px; background: var(--primary-700); color: #fff; font-size: 0.8125rem; font-weight: 700; }
        .wo-empty { padding: 2rem 1rem; text-align: center; color: var(--gray-700); font-size: 0.875rem; }
        .wo-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.75rem; }
        .wo-head h2 { font-size: 1rem; font-weight: 700; color: var(--gray-950); }
        .wo-head span { font-size: 0.8125rem; color: var(--gray-700); }
        .wo-lines { list-style: none; margin: 0; padding: 0; display: grid; }
        .wo-line { display: grid; grid-template-columns: 1fr auto; gap: 0.375rem 0.75rem; padding: 0.75rem 0; border-bottom: 1px solid var(--gray-100); }
        .wo-line-name { font-size: 0.9375rem; font-weight: 600; color: var(--gray-950); }
        .wo-line-sub { font-size: 0.8125rem; color: var(--gray-700); font-variant-numeric: tabular-nums; }
        .wo-step { display: inline-flex; align-items: center; border: 1px solid var(--gray-300); border-radius: 0.5rem; }
        .wo-step button { width: 2.75rem; height: 2.75rem; display: grid; place-items: center; color: var(--gray-800); }
        .wo-step button:focus-visible { outline: 2px solid var(--primary-600); outline-offset: -2px; border-radius: 0.5rem; }
        .wo-step output { min-width: 1.75rem; text-align: center; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--gray-950); }
        .wo-note { grid-column: 1 / -1; }
        .wo-field { width: 100%; min-height: 2.75rem; padding: 0 0.75rem; border: 1px solid var(--gray-300); border-radius: 0.5rem; background: #fff; font-size: 0.875rem; color: var(--gray-950); }
        .wo-total { display: flex; justify-content: space-between; align-items: baseline; padding: 0.875rem 0; font-variant-numeric: tabular-nums; }
        .wo-total span { font-size: 0.875rem; color: var(--gray-700); }
        .wo-total strong { font-size: 1.375rem; color: var(--gray-950); }
        .wo-form { display: grid; gap: 0.75rem; }
        .wo-label { display: block; margin-bottom: 0.25rem; font-size: 0.8125rem; font-weight: 600; color: var(--gray-800); }
        .wo-pay { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.375rem; border: 0; margin: 0; padding: 0; }
        .wo-pay label { display: grid; place-items: center; min-height: 2.75rem; padding: 0 0.25rem; border: 1px solid var(--gray-300); border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: var(--gray-800); cursor: pointer; }
        .wo-pay input { position: absolute; opacity: 0; pointer-events: none; }
        .wo-pay input:checked + label { background: var(--primary-50); border-color: var(--primary-700); color: var(--primary-800); }
        .wo-pay input:focus-visible + label { outline: 2px solid var(--primary-600); outline-offset: 1px; }
        .wo-error { margin-top: 0.25rem; font-size: 0.8125rem; font-weight: 600; color: var(--danger-700); }
        .wo-submit { width: 100%; min-height: 3rem; }
        .dark .wo-panel, .dark .wo-item, .dark .wo-search, .dark .wo-pill, .dark .wo-field { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .wo-item-name, .dark .wo-head h2, .dark .wo-line-name, .dark .wo-total strong, .dark .wo-step output, .dark .wo-search, .dark .wo-field { color: #fff; }
        .dark .wo-item-price, .dark .wo-head span, .dark .wo-line-sub, .dark .wo-total span, .dark .wo-empty, .dark .wo-pill, .dark .wo-label, .dark .wo-pay label, .dark .wo-step button { color: var(--gray-300); }
        .dark .wo-line { border-color: var(--gray-800); }
        .dark .wo-step, .dark .wo-pay label { border-color: var(--gray-700); }
        .dark .wo-pay input:checked + label { background: var(--gray-800); border-color: var(--primary-400); color: var(--primary-300); }
        .dark .wo-photo { background: var(--gray-800); }
        @media (prefers-reduced-motion: reduce) { .wo-item { transition: none; } }
    </style>

    <div class="wo">
        <section class="wo-panel" aria-labelledby="wo-menu-title">
            <h2 id="wo-menu-title" class="sr-only">Pilih menu</h2>
            <div class="wo-tools">
                <label for="wo-search" class="sr-only">Cari menu</label>
                <div class="wo-searchbar">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass" class="wo-search-icon" />
                    <input id="wo-search" type="search" class="wo-search" placeholder="Cari menu, misal kopi" autocomplete="off" wire:model.live.debounce.250ms="search">
                </div>
                @if($categories->count() > 1)
                    <div class="wo-pills" role="group" aria-label="Kategori">
                        <button type="button" class="wo-pill" aria-pressed="{{ $categoryId === null ? 'true' : 'false' }}" wire:click="$set('categoryId', null)">Semua</button>
                        @foreach($categories as $category)
                            <button type="button" class="wo-pill" aria-pressed="{{ $categoryId === $category->id ? 'true' : 'false' }}" wire:click="$set('categoryId', {{ $category->id }})">{{ $category->name }}</button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div wire:loading.delay wire:target="search,categoryId" class="wo-empty" role="status">Memuat menu...</div>
            <div wire:loading.remove wire:target="search,categoryId">
                @if($menu->isEmpty())
                    <p class="wo-empty" role="status">
                        @if($menuById->isEmpty())
                            Belum ada menu yang tersedia. Aktifkan menu di halaman Menu.
                        @else
                            Tidak ada menu yang cocok. Ubah kata kunci atau pilih kategori lain.
                        @endif
                    </p>
                @else
                    <div class="wo-grid">
                        @foreach($menu as $item)
                            @php $picked = $cart[$item->id]['qty'] ?? 0; @endphp
                            <button type="button" class="wo-item" wire:key="menu-{{ $item->id }}" wire:click="add({{ $item->id }})" @if($picked) data-picked @endif aria-label="Tambah {{ $item->name }}, {{ $rupiah($item->price) }}{{ $picked ? ', sudah '.$picked : '' }}">
                                @if($item->photoUrl())
                                    <img src="{{ $item->photoUrl() }}" alt="" class="wo-photo" loading="lazy">
                                @else
                                    <span class="wo-photo wo-photo-empty" aria-hidden="true">Tanpa foto</span>
                                @endif
                                <span class="wo-item-body">
                                    <span class="wo-item-name">{{ $item->name }}</span>
                                    <span class="wo-item-price">{{ $rupiah($item->price) }}</span>
                                </span>
                                @if($picked)
                                    <span class="wo-count" aria-hidden="true">{{ $picked }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <aside class="wo-panel wo-ticket" aria-labelledby="wo-ticket-title">
            <div class="wo-head">
                <h2 id="wo-ticket-title">Pesanan</h2>
                <span>{{ $itemCount }} item</span>
            </div>

            <form wire:submit="create">
                @if($cart === [])
                    <p class="wo-empty">Ketuk menu di sebelah untuk menambahkan.</p>
                @else
                    <ul class="wo-lines">
                        @foreach($cart as $id => $line)
                            @php $item = $menuById[$id] ?? null; @endphp
                            @continue(! $item)
                            <li class="wo-line" wire:key="line-{{ $id }}">
                                <div>
                                    <p class="wo-line-name">{{ $item->name }}</p>
                                    <p class="wo-line-sub">{{ $rupiah($item->price * $line['qty']) }}</p>
                                </div>
                                <div class="wo-step">
                                    <button type="button" wire:click="decrement({{ $id }})" aria-label="{{ $line['qty'] === 1 ? 'Hapus' : 'Kurangi' }} {{ $item->name }}">
                                        <x-filament::icon :icon="$line['qty'] === 1 ? \Filament\Support\Icons\Heroicon::OutlinedTrash : \Filament\Support\Icons\Heroicon::Minus" class="size-4" />
                                    </button>
                                    <output aria-live="polite">{{ $line['qty'] }}</output>
                                    <button type="button" wire:click="increment({{ $id }})" aria-label="Tambah {{ $item->name }}" @disabled($line['qty'] >= $maxQty)>
                                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::Plus" class="size-4" />
                                    </button>
                                </div>
                                <div class="wo-note">
                                    <label for="note-{{ $id }}" class="sr-only">Catatan {{ $item->name }}</label>
                                    <input id="note-{{ $id }}" type="text" class="wo-field" maxlength="{{ $maxNote }}" placeholder="Catatan, misal tidak pedas" wire:model.blur="cart.{{ $id }}.notes">
                                    @error('cart.'.$id.'.notes')<p class="wo-error">{{ $message }}</p>@enderror
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @error('cart')<p class="wo-error" role="alert">{{ $message }}</p>@enderror

                <div class="wo-total">
                    <span>Total</span>
                    <strong>{{ $rupiah($total) }}</strong>
                </div>

                <div class="wo-form">
                    <div>
                        <label for="wo-name" class="wo-label">Nama pemesan</label>
                        <input id="wo-name" type="text" class="wo-field" maxlength="80" autocomplete="off" wire:model="customerName" @error('customerName') aria-invalid="true" aria-describedby="wo-name-error" @enderror>
                        @error('customerName')<p id="wo-name-error" class="wo-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="wo-spot" class="wo-label">Meja atau lokasi (opsional)</label>
                        <select id="wo-spot" class="wo-field" wire:model="diningSpotId">
                            <option value="">Tanpa meja</option>
                            @foreach($diningSpots as $spotId => $spotName)
                                <option value="{{ $spotId }}">{{ $spotName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <fieldset class="wo-pay">
                        <legend class="wo-label">Pembayaran</legend>
                        @foreach($paymentOptions as $value => $label)
                            <div>
                                <input id="pay-{{ $value }}" type="radio" value="{{ $value }}" wire:model="payment">
                                <label for="pay-{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </fieldset>

                    <x-filament::button type="submit" size="lg" class="wo-submit" :disabled="$cart === []" wire:loading.attr="disabled" wire:target="create">
                        <span wire:loading.remove wire:target="create">Kirim ke dapur</span>
                        <span wire:loading wire:target="create">Mengirim...</span>
                    </x-filament::button>
                </div>
            </form>
        </aside>
    </div>
</x-filament-panels::page>
