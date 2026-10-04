<?php
namespace App\Filament\Widgets;
use Filament\Widgets\Widget;
class TimeTrackerWidget extends Widget
{
    protected string $view = 'filament.widgets.tracker';
    protected static ?int $sort = 8;
    protected int | string | array $columnSpan = 2;
}