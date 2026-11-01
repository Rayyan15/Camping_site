<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CleaningLogs\CleaningLogResource;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends \Filament\Pages\Dashboard
{
    // OB and kitchen have no dashboard data to see, so each lands on its only work list.
    public function mount(): void
    {
        $user = auth()->user();

        if ($user?->hasRole(User::ROLE_CLEANING)) {
            $this->redirect(CleaningLogResource::getUrl('index'));
        } elseif ($user?->hasRole(User::ROLE_KITCHEN)) {
            $this->redirect(KitchenQueue::getUrl());
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->hasAnyRole([User::ROLE_CLEANING, User::ROLE_KITCHEN]);
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
