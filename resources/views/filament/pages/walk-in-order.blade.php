<x-filament-panels::page>
    <form wire:submit="create" class="fi-form">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit" size="lg">Kirim ke dapur</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
