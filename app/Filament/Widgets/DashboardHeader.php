<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardHeader extends Widget
{
    protected string $view = 'filament.widgets.dashboard-header';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;
}
