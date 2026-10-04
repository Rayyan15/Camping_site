<?php

$resources = [
    'UnitTypes/UnitTypeResource.php' => ['modelLabel' => 'Tipe Tenda', 'pluralModelLabel' => 'Tipe Tenda', 'navigationGroup' => 'Kelola Tenda', 'navigationIcon' => 'heroicon-o-home-modern', 'sort' => 1],
    'Units/UnitResource.php' => ['modelLabel' => 'Tenda', 'pluralModelLabel' => 'Daftar Tenda', 'navigationGroup' => 'Kelola Tenda', 'navigationIcon' => 'heroicon-o-home', 'sort' => 2],
    'Addons/AddonResource.php' => ['modelLabel' => 'Layanan Tambahan', 'pluralModelLabel' => 'Layanan Tambahan', 'navigationGroup' => 'Kelola Tenda', 'navigationIcon' => 'heroicon-o-sparkles', 'sort' => 3],
    'Bookings/BookingResource.php' => ['modelLabel' => 'Booking', 'pluralModelLabel' => 'Daftar Booking', 'navigationGroup' => 'Operasional', 'navigationIcon' => 'heroicon-o-calendar-days', 'sort' => 1],
    'Orders/OrderResource.php' => ['modelLabel' => 'Pesanan Makanan', 'pluralModelLabel' => 'Pesanan Makanan', 'navigationGroup' => 'Operasional', 'navigationIcon' => 'heroicon-o-shopping-cart', 'sort' => 2],
    'Customers/CustomerResource.php' => ['modelLabel' => 'Pelanggan', 'pluralModelLabel' => 'Pelanggan', 'navigationGroup' => 'Operasional', 'navigationIcon' => 'heroicon-o-users', 'sort' => 3],
    'Refunds/RefundResource.php' => ['modelLabel' => 'Pengembalian Dana', 'pluralModelLabel' => 'Pengembalian Dana', 'navigationGroup' => 'Keuangan', 'navigationIcon' => 'heroicon-o-banknotes', 'sort' => 1],
    'CleaningLogs/CleaningLogResource.php' => ['modelLabel' => 'Laporan Kebersihan', 'pluralModelLabel' => 'Laporan Kebersihan', 'navigationGroup' => 'Laporan', 'navigationIcon' => 'heroicon-o-clipboard-document-check', 'sort' => 1],
];

foreach ($resources as $file => $data) {
    $path = 'app/Filament/Resources/' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        $injection = "
    protected static ?string \$modelLabel = '{$data['modelLabel']}';
    protected static ?string \$pluralModelLabel = '{$data['pluralModelLabel']}';
    protected static \BackedEnum|string|null \$navigationGroup = '{$data['navigationGroup']}';
    protected static ?int \$navigationSort = {$data['sort']};
";
        
        // Remove previous faulty injection
        $content = preg_replace('/protected static \?string \$modelLabel.*?;/s', '', $content);
        $content = preg_replace('/protected static \?string \$pluralModelLabel.*?;/s', '', $content);
        $content = preg_replace('/protected static \?string \$navigationGroup.*?;/s', '', $content);
        $content = preg_replace('/protected static \?int \$navigationSort.*?;/s', '', $content);
        $content = preg_replace('/protected static \?string \$navigationIcon.*?;/s', '', $content);
        $content = preg_replace('/protected static string\|BackedEnum\|null \$navigationIcon.*?;/s', '', $content);
        $content = preg_replace('/protected static \\\BackedEnum\|string\|null \$navigationGroup.*?;/s', '', $content);
        $content = preg_replace('/protected static \\\BackedEnum\|string\|null \$navigationIcon.*?;/s', '', $content);

        // Remove empty lines
        $content = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $content);
        
        $newInjection = "
    protected static ?string \$modelLabel = '{$data['modelLabel']}';
    protected static ?string \$pluralModelLabel = '{$data['pluralModelLabel']}';
    public static function getNavigationGroup(): ?string { return '{$data['navigationGroup']}'; }
    public static function getNavigationSort(): ?int { return {$data['sort']}; }
    public static function getNavigationIcon(): string|\Illuminate\View\ComponentAttributeBag { return '{$data['navigationIcon']}'; }
";
        
        $content = preg_replace('/(protected static \?string \$model = [^;]+;)/', "$1\n".$newInjection, $content);
        
        file_put_contents($path, $content);
        echo "Updated $file\n";
    }
}
