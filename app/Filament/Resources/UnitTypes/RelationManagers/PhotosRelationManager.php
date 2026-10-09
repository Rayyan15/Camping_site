<?php

namespace App\Filament\Resources\UnitTypes\RelationManagers;

use App\Models\UnitType;
use App\Models\UnitTypePhoto;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Galeri Foto';

    protected static ?string $modelLabel = 'Foto';

    private const MAX_KILOBYTES = 2048;

    private const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageName): bool
    {
        return (bool) auth()->user()?->can('viewAny', UnitTypePhoto::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                ImageColumn::make('url')
                    ->label('Pratinjau')
                    ->disk(UnitTypePhoto::DISK)
                    ->state(fn (UnitTypePhoto $record): string => $record->url)
                    ->imageHeight(72)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover', 'alt' => 'Pratinjau foto tipe tenda']),
                TextColumn::make('sort_order')
                    ->label('Urutan'),
            ])
            ->description(fn (): ?string => $this->getOwnerRecord()->photos()->doesntExist()
                ? 'Tipe tenda ini belum punya foto. Tamu akan melihat tampilan kosong di halaman tenda, unggah minimal satu foto.'
                : 'Seret baris untuk mengubah urutan. Foto pertama menjadi foto utama di landing dan halaman tenda.')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([$this->uploadAction()])
            ->recordActions([
                DeleteAction::make()
                    ->modalDescription('Foto dihapus dari galeri dan dari penyimpanan. Tindakan ini tidak bisa dibatalkan.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('Belum ada foto')
            ->emptyStateDescription('Unggah foto tenda, interior, dan sekitarnya agar tamu bisa menilai sebelum memesan.')
            ->emptyStateIcon('heroicon-o-photo');
    }

    private function uploadAction(): Action
    {
        return Action::make('upload')
            ->label('Unggah foto')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn (): bool => (bool) auth()->user()?->can('create', UnitTypePhoto::class))
            ->modalHeading('Unggah foto tipe tenda')
            ->schema([
                FileUpload::make('paths')
                    ->label('Foto')
                    ->helperText('JPG, PNG atau WebP, maksimal 2 MB per foto. Bisa memilih beberapa sekaligus.')
                    ->multiple()
                    ->image()
                    ->disk(UnitTypePhoto::DISK)
                    ->visibility('public')
                    ->directory(fn (): string => 'unit-types/'.$this->getOwnerRecord()->slug)
                    ->acceptedFileTypes(self::MIME_TYPES)
                    ->maxSize(self::MAX_KILOBYTES)
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file): string => Str::random(40).'.'.$file->guessExtension(),
                    )
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var UnitType $unitType */
                $unitType = $this->getOwnerRecord();
                $nextOrder = (int) $unitType->photos()->max('sort_order') + 1;

                foreach (array_values($data['paths']) as $offset => $path) {
                    $unitType->photos()->create(['path' => $path, 'sort_order' => $nextOrder + $offset]);
                }

                Notification::make()->title(count($data['paths']).' foto ditambahkan')->success()->send();
            });
    }
}
