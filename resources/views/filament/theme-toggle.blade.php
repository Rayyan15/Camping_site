{{-- One button instead of Filament's three-way switcher: it flips between light and dark, starting from whatever the device currently shows. --}}
<button
    type="button"
    x-data="{
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = ! this.dark
            $dispatch('theme-changed', this.dark ? 'dark' : 'light')
        },
    }"
    x-on:click="toggle()"
    x-bind:aria-label="dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'"
    x-bind:aria-pressed="dark ? 'true' : 'false'"
    x-tooltip="{ content: dark ? 'Mode terang' : 'Mode gelap', theme: $store.theme }"
    class="fi-icon-btn fi-size-md"
>
    <span x-show="! dark">{{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedMoon) }}</span>
    <span x-show="dark" x-cloak>{{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedSun) }}</span>
</button>
