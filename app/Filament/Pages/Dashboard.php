<?php

namespace App\Filament\Pages;

use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function getColumns(): int|array
    {
        return 4;
    }

    /** The page title lives in the header card of TodayActions, so Filament's own heading is left empty. */
    public function getHeading(): string|Htmlable
    {
        return '';
    }
}
