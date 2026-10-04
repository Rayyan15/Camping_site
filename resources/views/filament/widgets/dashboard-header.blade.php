<x-filament-widgets::widget>
    <div class="flex items-center justify-between mb-4 mt-2">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Rencanakan, prioritaskan, dan kelola reservasi operasional dengan mudah.</p>
        </div>
        
        <div class="flex space-x-3 gap-3">
            <x-filament::button href="/admin/bookings/create" tag="a" color="primary" class="rounded-full px-6 shadow-sm">
                + Booking Baru
            </x-filament::button>
            <x-filament::button href="/admin/bookings" tag="a" color="gray" class="rounded-full px-6 shadow-sm bg-white text-gray-900 border border-gray-200">
                Data Lengkap
            </x-filament::button>
        </div>
    </div>
</x-filament-widgets::widget>