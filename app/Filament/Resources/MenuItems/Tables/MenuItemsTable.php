<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Models\MenuItem;
use App\Services\MenuStockService;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MenuItemsTable
{
    private const MAX_PORTIONS = 10000;

    private const PREORDER_NOTE = 'Pesanan saat booking ikut memotong stok sejak dipesan, walau disajikan hari lain. Untuk paket BBQ yang dipesan jauh hari, kosongkan stoknya.';

    private static function reservedNote(MenuItem $record): string
    {
        $reserved = app(MenuStockService::class)->reservedFor($record);

        return $reserved > 0 ? "{$reserved} porsi sudah disisihkan untuk pesanan booking yang belum disajikan. " : '';
    }

    public static function stockLabel(?int $left): string
    {
        return match (true) {
            $left === null => 'Tidak dibatasi',
            $left === 0 => 'Habis',
            default => "{$left} porsi",
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('Foto')->disk('public')->square(),
                TextColumn::make('name')->label('Menu')->searchable()->weight('bold'),
                TextColumn::make('category.name')->label('Kategori')->sortable(),
                TextColumn::make('price')->label('Harga')->money('IDR', locale: 'id', decimalPlaces: 0)->sortable(),
                ToggleColumn::make('is_available')
                    ->label('Tersedia')
                    ->disabled(fn () => ! (auth()->user()?->can('manage_menu') || auth()->user()?->can('toggle_menu_availability'))),
                TextColumn::make('stock_left')
                    ->label('Sisa stok')
                    ->state(fn (MenuItem $record): string => self::stockLabel(once(fn () => app(MenuStockService::class)->remaining())[$record->id] ?? null))
                    ->badge()
                    ->tooltip('Habis berarti menu tidak muncul di QR, form booking, dan walk-in.')
                    ->color(fn (string $state): string => match ($state) {
                        'Habis' => 'danger',
                        'Tidak dibatasi' => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('sort_order')->label('Urutan')->sortable()->toggleable(),
            ])
            ->recordActions([
                Action::make('add_stock')
                    ->label('Tambah stok')
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('manage_menu_stock'))
                    ->modalSubmitActionLabel('Tambah')
                    ->modalDescription(fn (MenuItem $record): string => 'Sisa sekarang: '.self::stockLabel(app(MenuStockService::class)->remainingFor($record)).'. Isi jumlah porsi yang baru disiapkan. '.self::reservedNote($record).self::PREORDER_NOTE)
                    ->schema([
                        TextInput::make('portions')->label('Tambah berapa porsi')->integer()->minValue(1)->maxValue(self::MAX_PORTIONS)->required(),
                    ])
                    ->action(function (MenuItem $record, array $data): void {
                        app(MenuStockService::class)->add($record, (int) $data['portions']);
                        Notification::make()->title("Stok {$record->name} ditambah")->success()->send();
                    }),
                Action::make('set_stock')
                    ->label('Atur stok')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('gray')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('manage_menu_stock'))
                    ->modalSubmitActionLabel('Simpan stok')
                    ->modalDescription(fn (MenuItem $record): string => 'Angka berarti stok dihitung dan menu hilang dari pesanan saat 0. Kosong berarti tidak dibatasi. '.self::reservedNote($record).self::PREORDER_NOTE)
                    ->fillForm(fn (MenuItem $record): array => ['portions' => app(MenuStockService::class)->remainingFor($record)])
                    ->schema([
                        TextInput::make('portions')
                            ->label('Sisa yang masih bisa dijual')
                            ->helperText('Hitung porsi yang siap, kurangi yang sudah dipesan lewat booking, lalu ketik angkanya. Kosongkan bila menu ini tidak memakai stok.')
                            ->integer()->minValue(0)->maxValue(self::MAX_PORTIONS),
                    ])
                    ->action(function (MenuItem $record, array $data): void {
                        app(MenuStockService::class)->setRemaining($record, filled($data['portions']) ? (int) $data['portions'] : null);
                        Notification::make()->title("Stok {$record->name} disimpan")->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([DeleteBulkAction::make()])
            // Sold dishes cannot be picked for delete; see MenuItem::canBeDeleted.
            ->modifyQueryUsing(fn ($query) => $query->withExists('orderItems'))
            ->checkIfRecordIsSelectableUsing(fn (MenuItem $record): bool => $record->canBeDeleted())
            // Urutan restarts at 1 in every category; the category chips on the list page keep that readable.
            ->defaultSort(fn ($query) => $query->orderBy('category_id')->orderBy('sort_order'))
            // Guests see items ordered by sort_order inside each category, so dragging here sets that order.
            ->reorderable('sort_order', fn (): bool => (bool) auth()->user()?->can('manage_menu'))
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering) => $action
                ->button()
                ->label($isReordering ? 'Selesai mengurutkan' : 'Ubah urutan menu'))
            ->emptyStateHeading('Belum ada menu');
    }
}
