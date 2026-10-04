<?php

$dir = new RecursiveDirectoryIterator('app/Filament/Resources');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);
        
        $newContent = str_replace(
            '\Filament\Forms\Components\Section',
            '\Filament\Schemas\Components\Section',
            $content
        );
        $newContent = str_replace(
            '\Filament\Forms\Components\Grid',
            '\Filament\Schemas\Components\Grid',
            $newContent
        );
        
        if ($newContent !== $content) {
            file_put_contents($file, $newContent);
            echo "Updated $file\n";
        }
    }
}
