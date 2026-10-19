<?php

namespace App\Filament\Pages;

use App\Enums\PaymentMethod;
use App\Exceptions\MenuItemUnavailableException;
use App\Http\Requests\Public\StoreQrOrderRequest;
use App\Models\DiningSpot;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\OrderService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use UnitEnum;

class WalkInOrder extends Page
{
    private const PAY_LATER = 'later';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Order Walk-in';

    protected static ?string $title = 'Order Walk-in';

    protected static ?string $slug = 'order-walk-in';

    protected string $view = 'filament.pages.walk-in-order';

    /** @var array<int|string, array{qty: int, notes: string}> keyed by menu item id */
    public array $cart = [];

    public string $search = '';

    public ?int $categoryId = null;

    public string $customerName = '';

    public ?int $diningSpotId = null;

    public string $payment = self::PAY_LATER;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create_walkin_orders') ?? false;
    }

    public function add(int $menuItemId): void
    {
        if (isset($this->cart[$menuItemId])) {
            $this->increment($menuItemId);

            return;
        }

        if (count($this->cart) >= StoreQrOrderRequest::MAX_LINES) {
            return;
        }

        $this->cart[$menuItemId] = ['qty' => 1, 'notes' => ''];
    }

    public function increment(int $menuItemId): void
    {
        if (isset($this->cart[$menuItemId])) {
            $this->cart[$menuItemId]['qty'] = min(StoreQrOrderRequest::MAX_QTY, $this->cart[$menuItemId]['qty'] + 1);
        }
    }

    public function decrement(int $menuItemId): void
    {
        if (! isset($this->cart[$menuItemId])) {
            return;
        }

        if ($this->cart[$menuItemId]['qty'] <= 1) {
            unset($this->cart[$menuItemId]);

            return;
        }

        $this->cart[$menuItemId]['qty']--;
    }

    public function create(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->validate([
            'cart' => ['required', 'array', 'min:1', 'max:'.StoreQrOrderRequest::MAX_LINES],
            'cart.*.qty' => ['required', 'integer', 'between:1,'.StoreQrOrderRequest::MAX_QTY],
            'cart.*.notes' => ['nullable', 'string', 'max:'.StoreQrOrderRequest::MAX_NOTE_LENGTH],
            'customerName' => ['required', 'string', 'max:80'],
            'diningSpotId' => ['nullable', 'integer', 'exists:dining_spots,id'],
            'payment' => ['required', Rule::in(array_keys($this->paymentOptions()))],
        ], [
            'cart.required' => 'Pilih minimal satu menu.',
            'cart.min' => 'Pilih minimal satu menu.',
            'customerName.required' => 'Isi nama pemesan.',
        ]);

        $items = collect($this->cart)
            ->map(fn (array $line, int|string $id) => ['menu_item_id' => (int) $id, 'qty' => $line['qty'], 'notes' => $line['notes'] ?: null])
            ->values()
            ->all();
        $paidWith = $this->payment === self::PAY_LATER ? null : PaymentMethod::from($this->payment);

        try {
            $order = app(OrderService::class)->createWalkinOrder($items, $this->customerName, $this->diningSpotId, $paidWith, auth()->id());
        } catch (MenuItemUnavailableException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Pesanan '.$order->code.' masuk antrian dapur')->success()->send();
        $this->reset('cart', 'customerName', 'diningSpotId', 'payment');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $menu = MenuItem::available()->orderBy('sort_order')->orderBy('name')->get();

        return [
            'categories' => MenuCategory::whereIn('id', $menu->pluck('category_id'))->orderBy('sort_order')->get(),
            'menu' => $this->filterMenu($menu),
            'menuById' => $menu->keyBy('id'),
            'diningSpots' => DiningSpot::orderBy('name')->pluck('name', 'id'),
            'paymentOptions' => $this->paymentOptions(),
            'maxQty' => StoreQrOrderRequest::MAX_QTY,
            'maxNote' => StoreQrOrderRequest::MAX_NOTE_LENGTH,
        ];
    }

    /**
     * @param  Collection<int, MenuItem>  $menu
     * @return Collection<int, MenuItem>
     */
    private function filterMenu(Collection $menu): Collection
    {
        $term = mb_strtolower(trim($this->search));

        return $menu
            ->when($this->categoryId, fn (Collection $items) => $items->where('category_id', $this->categoryId))
            ->when($term !== '', fn (Collection $items) => $items->filter(fn (MenuItem $item) => str_contains(mb_strtolower($item->name), $term)))
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function paymentOptions(): array
    {
        return [
            self::PAY_LATER => 'Bayar nanti',
            PaymentMethod::Cash->value => 'Tunai lunas',
            PaymentMethod::Manual->value => 'QRIS lunas',
        ];
    }
}
