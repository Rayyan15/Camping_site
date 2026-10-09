<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Services\CustomerSpendService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        $canViewSpend = fn (): bool => (bool) auth()->user()?->can('viewSpend', Customer::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $canViewSpend()
                ? app(CustomerSpendService::class)->withSummary($query)
                : $query)
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('phone')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('view_customer_contact'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('view_customer_contact'))
                    ->searchable(),
                TextColumn::make('visit_count')
                    ->label('Jumlah kunjungan')
                    ->numeric()
                    ->visible($canViewSpend)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('visit_count', $direction)),
                TextColumn::make('total_spend')
                    ->label('Total belanja')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->visible($canViewSpend)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('total_spend', $direction)),
                TextColumn::make('last_visit_at')
                    ->label('Kunjungan terakhir')
                    ->date('d M Y')
                    ->placeholder('Belum pernah menginap')
                    ->visible($canViewSpend)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('last_visit_at', $direction)),
                TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('has_stayed')
                    ->label('Pernah menginap')
                    ->toggle()
                    ->visible($canViewSpend)
                    ->query(fn (Builder $query): Builder => app(CustomerSpendService::class)->whereHasVisited($query)),
                Filter::make('min_spend')
                    ->label('Belanja minimal')
                    ->visible($canViewSpend)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Belanja minimal')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['amount'] ?? null)
                        ? app(CustomerSpendService::class)->whereSpendAtLeast($query, (int) $data['amount'])
                        : $query)
                    ->indicateUsing(fn (array $data): ?string => filled($data['amount'] ?? null)
                        ? 'Belanja minimal Rp '.number_format((int) $data['amount'], 0, ',', '.')
                        : null),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading('Belum ada data')
            ->emptyStateDescription('Data akan muncul di sini setelah ditambahkan.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->striped()
            ->defaultSort('created_at', 'desc');
    }
}
