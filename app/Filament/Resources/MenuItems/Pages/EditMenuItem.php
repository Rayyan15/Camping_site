<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Models\MenuItem;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMenuItem extends EditRecord
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A dish that was sold is turned off with Tersedia instead, so its sales stay in bills and reports.
            DeleteAction::make()->visible(fn (MenuItem $record): bool => $record->canBeDeleted()),
        ];
    }
}
