<?php

namespace App\Filament\Pages;

use App\Enums\PaymentMethod;
use App\Exceptions\MenuItemUnavailableException;
use App\Http\Requests\Public\StoreQrOrderRequest;
use App\Models\DiningSpot;
use App\Models\MenuItem;
use App\Services\OrderService;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class WalkInOrder extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    private const PAY_LATER = 'later';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Order Walk-in';

    protected static ?string $title = 'Order Walk-in';

    protected static ?string $slug = 'order-walk-in';

    protected string $view = 'filament.pages.walk-in-order';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create_walkin_orders') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['payment' => self::PAY_LATER, 'items' => [['qty' => 1]]]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Pesanan')
                    ->schema([
                        Repeater::make('items')
                            ->label('Menu')
                            ->addActionLabel('Tambah menu')
                            ->minItems(1)
                            ->maxItems(StoreQrOrderRequest::MAX_LINES)
                            ->defaultItems(1)
                            ->required()
                            ->columns(3)
                            ->schema([
                                Select::make('menu_item_id')
                                    ->label('Menu')
                                    ->options(fn () => $this->menuOptions())
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->columnSpan(2),
                                TextInput::make('qty')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->maxValue(StoreQrOrderRequest::MAX_QTY)
                                    ->required(),
                                TextInput::make('notes')
                                    ->label('Catatan')
                                    ->maxLength(StoreQrOrderRequest::MAX_NOTE_LENGTH)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Pemesan')
                    ->columns(3)
                    ->schema([
                        TextInput::make('customer_name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(80),
                        Select::make('dining_spot_id')
                            ->label('Meja atau lokasi (opsional)')
                            ->options(fn () => DiningSpot::orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                        Select::make('payment')
                            ->label('Pembayaran')
                            ->options([
                                self::PAY_LATER => 'Bayar nanti',
                                PaymentMethod::Cash->value => 'Tunai, sudah dibayar',
                                PaymentMethod::Manual->value => 'QRIS, sudah dibayar',
                            ])
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    public function create(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $paidWith = $state['payment'] === self::PAY_LATER ? null : PaymentMethod::from($state['payment']);

        try {
            $order = app(OrderService::class)->createWalkinOrder(
                array_values($state['items']),
                $state['customer_name'],
                $state['dining_spot_id'] ?? null,
                $paidWith,
                auth()->id(),
            );
        } catch (MenuItemUnavailableException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Pesanan '.$order->code.' masuk antrian dapur')->success()->send();
        $this->form->fill(['payment' => self::PAY_LATER, 'items' => [['qty' => 1]]]);
    }

    /**
     * @return array<int, string>
     */
    private function menuOptions(): array
    {
        return MenuItem::available()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (MenuItem $item) => [$item->id => $item->name.' - Rp '.number_format($item->price, 0, ',', '.')])
            ->all();
    }
}
