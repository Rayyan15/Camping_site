<x-filament-widgets::widget>
    <x-filament::section
        heading="Ringkasan operasional"
        description="Check-in, okupansi, dan pesanan yang berjalan hari ini."
    >
        <x-slot name="afterHeader">
            <x-filament::button :href="\App\Filament\Resources\Bookings\BookingResource::getUrl()" tag="a" color="gray">
                Lihat daftar booking
            </x-filament::button>
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
