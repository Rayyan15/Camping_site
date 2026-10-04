<?php

$dir = new RecursiveDirectoryIterator('app/Filament/Resources');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && strpos($file->getPathname(), 'Tables') !== false) {
        $content = file_get_contents($file);
        
        $injection = "
            ->emptyStateHeading('Belum ada data')
            ->emptyStateDescription('Data akan muncul di sini setelah ditambahkan.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->striped()
            ->defaultSort('created_at', 'desc')";
        
        // Ensure we don't inject multiple times
        if (strpos($content, 'emptyStateHeading') === false) {
            $content = preg_replace('/(\->filters\(\[.*\]\))/s', "$1".$injection, $content);
            file_put_contents($file, $content);
            echo "Updated " . $file->getPathname() . "\n";
        }
    }
}

// Also update the LatestBookings Widget table
$latestWidget = 'app/Filament/Widgets/LatestBookings.php';
if (file_exists($latestWidget)) {
    $content = file_get_contents($latestWidget);
    if (strpos($content, 'emptyStateHeading') === false) {
        $injection = "
            ->emptyStateHeading('Belum ada booking')
            ->emptyStateDescription('Daftar booking terbaru akan muncul di sini.')
            ->emptyStateIcon('heroicon-o-bookmark-slash')
            ->striped()";
        $content = preg_replace('/(\->columns\(\[.*\]\))/s', "$1".$injection, $content);
        file_put_contents($latestWidget, $content);
        echo "Updated $latestWidget\n";
    }
}
