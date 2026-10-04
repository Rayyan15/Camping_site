<x-filament-widgets::widget>
    <x-filament::section class="bg-gray-950 text-white rounded-xl shadow-lg border border-gray-800" style="background-color: #0f172a; color: white;">
        <div class="flex items-center justify-between p-2">
            <div>
                <p class="text-sm font-semibold text-gray-400 uppercase tracking-widest mb-2">
                    {{ strtoupper(now()->translatedFormat('l, d F Y')) }}
                </p>
                <h2 class="text-4xl font-bold mb-2">Halo, Owner.</h2>
                <p class="text-gray-300">Ini ringkasan operasional Raynad Camping hari ini.</p>
            </div>
            
            <div>
                <x-filament::button href="/admin/bookings/create" tag="a" color="gray" style="background-color: white; color: black;" class="rounded-full px-6 font-bold">
                    + Booking Baru
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
