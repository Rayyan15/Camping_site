<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** One chip per category, in the order guests see them on the QR menu. */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Semua')->badge(MenuItem::count())];

        foreach (MenuCategory::withCount('items')->orderBy('sort_order')->get() as $category) {
            $tabs['category-'.$category->id] = Tab::make($category->name)
                ->badge($category->items_count)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('category_id', $category->id));
        }

        return $tabs;
    }
}
