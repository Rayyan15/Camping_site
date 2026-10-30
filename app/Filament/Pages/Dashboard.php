<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CleaningLogs\CleaningLogResource;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends \Filament\Pages\Dashboard
{
    // OB has no dashboard data to see, so its home is the cleaning report list.
    public function mount(): void
    {
        if (auth()->user()?->hasRole(User::ROLE_CLEANING)) {
            $this->redirect(CleaningLogResource::getUrl('index'));
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->hasRole(User::ROLE_CLEANING);
    }

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
