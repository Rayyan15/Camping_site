<?php

$resources = [
    'Employees/EmployeeResource.php' => ['modelLabel' => 'Karyawan', 'pluralModelLabel' => 'Data Karyawan', 'navigationGroup' => 'SDM & Karyawan', 'navigationIcon' => 'heroicon-o-user-group', 'sort' => 1],
    'Evaluations/EvaluationResource.php' => ['modelLabel' => 'Penilaian Karyawan', 'pluralModelLabel' => 'Rating & Evaluasi', 'navigationGroup' => 'SDM & Karyawan', 'navigationIcon' => 'heroicon-o-star', 'sort' => 2],
];

foreach ($resources as $file => $data) {
    $path = 'app/Filament/Resources/' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        $newInjection = "
    protected static ?string \$modelLabel = '{$data['modelLabel']}';
    protected static ?string \$pluralModelLabel = '{$data['pluralModelLabel']}';
    public static function getNavigationGroup(): ?string { return '{$data['navigationGroup']}'; }
    public static function getNavigationSort(): ?int { return {$data['sort']}; }
    public static function getNavigationIcon(): string|\Illuminate\View\ComponentAttributeBag { return '{$data['navigationIcon']}'; }
";
        
        if (!str_contains($content, 'getNavigationGroup')) {
            $content = preg_replace('/(protected static \?string \$model = [^;]+;)/', "$1\n".$newInjection, $content);
            file_put_contents($path, $content);
            echo "Updated $file\n";
        }
    }
}
