@props(['name', 'label', 'value' => 0, 'min' => 0, 'max' => 10, 'price' => null, 'perNight' => false, 'role' => null])

<div class="inline-flex items-center rounded-full border border-sand bg-[#fffdf8]" data-stepper>
    <button type="button" data-step="-1" class="grid size-11 place-items-center rounded-full text-forest-800 transition hover:bg-forest-100 disabled:opacity-35" aria-label="Kurangi {{ $label }}">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M5 12h14"/></svg>
    </button>
    <input type="number" name="{{ $name }}" value="{{ $value }}" min="{{ $min }}" max="{{ $max }}" inputmode="numeric"
           aria-label="Jumlah {{ $label }}"
           @if($role) data-role="{{ $role }}" @endif
           @if($price !== null) data-price="{{ $price }}" @endif
           @if($perNight) data-per-night @endif
           class="w-10 appearance-none border-0 bg-transparent p-0 text-center font-bold text-forest-900 [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
    <button type="button" data-step="1" class="grid size-11 place-items-center rounded-full text-forest-800 transition hover:bg-forest-100 disabled:opacity-35" aria-label="Tambah {{ $label }}">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
    </button>
</div>
