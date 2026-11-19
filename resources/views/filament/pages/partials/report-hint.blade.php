@if ($hint)
    {{-- A mouse opens it on hover and a click keeps it open; touch and keyboard toggle it, since hover is not there. --}}
    <span class="lap-hint" x-data="{ open: false }"
          x-on:pointerenter="if ($event.pointerType === 'mouse') open = true"
          x-on:pointerleave="if ($event.pointerType === 'mouse') open = false"
          x-on:click.outside="open = false"
          x-on:keydown.escape.stop="open = false">
        <button type="button" class="lap-hint-btn" x-on:click="open = $event.pointerType === 'mouse' ? true : ! open" x-bind:aria-expanded="open.toString()" aria-expanded="false" aria-label="Asal angka {{ $label }}">
            <x-filament::icon icon="heroicon-m-information-circle" />
        </button>
        <span class="lap-hint-card" role="tooltip" x-show="open" x-cloak>{{ $hint }}</span>
    </span>
@endif
