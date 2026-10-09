<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\MenuItemUnavailableException;
use App\Exceptions\UnitUnavailableException;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly OrderService $orders,
        private readonly BookingCodeGenerator $codes,
        private readonly UnitNightLedger $ledger,
    ) {}

    /**
     * Active units of a type that are free for the whole stay.
     *
     * @return Collection<int, Unit>
     */
    public function checkAvailability(int $unitTypeId, string $checkIn, string $checkOut): Collection
    {
        $checkIn = $this->dateString($checkIn);
        $checkOut = $this->dateString($checkOut);

        return Unit::where('unit_type_id', $unitTypeId)
            ->where('status', Unit::STATUS_ACTIVE)
            ->freeBetween($checkIn, $checkOut)
            ->get();
    }

    /**
     * Options shown next to the booking form: bookable add-ons and the available menu.
     *
     * @return array{addons: Collection<int, Addon>, menuCategories: Collection<int, MenuCategory>}
     */
    public function formOptions(): array
    {
        return [
            'addons' => Addon::where('is_active', true)->orderBy('name')->get(),
            'menuCategories' => MenuCategory::with(['items' => fn ($q) => $q->where('is_available', true)->orderBy('name')])
                ->orderBy('sort_order')
                ->get()
                ->filter(fn (MenuCategory $category) => $category->items->isNotEmpty())
                ->values(),
        ];
    }

    /**
     * Whole checkout use case: customer, unit selection and booking in one go.
     *
     * @param  array{customer_name: string, customer_email: string, customer_phone: string, check_in: string, check_out: string, guests: int, unit_type_id: int, quantity: int, notes?: string|null, addons?: array<int, int>, preorder?: array<int, array<string, mixed>>}  $data
     *
     * @throws UnitUnavailableException
     * @throws MenuItemUnavailableException
     */
    public function createFromCheckout(array $data): Booking
    {
        $attempts = (int) config('booking.db_transaction_attempts');

        // Under MySQL REPEATABLE READ the free-unit query reads the snapshot taken before a concurrent
        // checkout committed, so the loser can pick the unit the winner just took and fail on the ledger.
        // A fresh transaction sees the committed rows and picks the next free unit; when none is left,
        // pickFreeUnitIds throws again and the guest gets the honest "sold out" answer.
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->createFromCheckoutOnce($data);
            } catch (UnitUnavailableException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws UnitUnavailableException
     * @throws MenuItemUnavailableException
     */
    private function createFromCheckoutOnce(array $data): Booking
    {
        // One transaction so the units picked here stay locked until the booking and ledger rows exist.
        return DB::transaction(function () use ($data) {
            $customer = $this->resolveCustomer($data['customer_name'], $data['customer_email'], $data['customer_phone']);

            $unitIds = $this->pickFreeUnitIds(
                $data['unit_type_id'],
                $this->dateString($data['check_in']),
                $this->dateString($data['check_out']),
                $data['quantity'],
            );

            return $this->createBooking(
                $customer->id,
                $data['check_in'],
                $data['check_out'],
                $data['guests'],
                $unitIds,
                $data['addons'] ?? [],
                $data['notes'] ?? null,
                $data['preorder'] ?? [],
            );
        }, (int) config('booking.db_transaction_attempts'));
    }

    /**
     * Email alone is not an identity: a different phone means a different person, so that guest gets
     * their own record instead of inheriting (or overwriting) someone else's booking history.
     */
    private function resolveCustomer(string $name, string $email, string $phone): Customer
    {
        $customer = Customer::firstOrCreate(
            ['email' => $email, 'phone' => $phone],
            ['name' => $name],
        );

        if ($customer->name !== $name) {
            $customer->update(['name' => $name]);
        }

        return $customer;
    }

    /**
     * Locks the lowest free units so a concurrent checkout waits here. On MySQL the waiting checkout may
     * still see a stale snapshot; createFromCheckout retries in a new transaction for that case.
     *
     * @return array<int, int>
     *
     * @throws UnitUnavailableException
     */
    private function pickFreeUnitIds(int $unitTypeId, string $checkIn, string $checkOut, int $quantity): array
    {
        $unitIds = Unit::where('unit_type_id', $unitTypeId)
            ->where('status', Unit::STATUS_ACTIVE)
            ->freeBetween($checkIn, $checkOut)
            ->orderBy('id')
            ->limit($quantity)
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if (count($unitIds) < $quantity) {
            throw UnitUnavailableException::alreadyBooked();
        }

        return $unitIds;
    }

    /**
     * Creates a pending_payment booking holding the units, with prices computed server side.
     *
     * @param  array<int, int>  $unitIds
     * @param  array<int, int>  $addonQuantities  addon id => quantity
     * @param  array<int, array{menu_item_id: int, qty: int, serve_date: string, serve_time: string}>  $preorders
     *
     * @throws UnitUnavailableException
     * @throws MenuItemUnavailableException
     */
    public function createBooking(
        int $customerId,
        string $checkIn,
        string $checkOut,
        int $guests,
        array $unitIds,
        array $addonQuantities = [],
        ?string $notes = null,
        array $preorders = [],
    ): Booking {
        $checkIn = $this->dateString($checkIn);
        $checkOut = $this->dateString($checkOut);

        return DB::transaction(function () use ($customerId, $checkIn, $checkOut, $guests, $unitIds, $addonQuantities, $notes, $preorders) {
            // Locking the unit rows serialises concurrent bookings of the same units.
            $units = Unit::with('unitType')->whereIn('id', $unitIds)->lockForUpdate()->get();

            $this->assertUnitsAvailable($units, $unitIds, $checkIn, $checkOut);

            $nights = $this->pricing->nightsBetween($checkIn, $checkOut);
            $unitLines = $units->map(fn (Unit $unit) => $this->unitLine($unit, $checkIn, $checkOut, $nights));
            $addonLines = $this->pricing->addonLines($addonQuantities, $nights);
            $foodTotal = array_sum(array_column($this->pricing->menuLines($preorders), 'subtotal'));

            $subtotal = $unitLines->sum('subtotal') + array_sum(array_column($addonLines, 'subtotal')) + $foodTotal;
            $tax = $this->pricing->taxFor($subtotal);

            $booking = Booking::create([
                'code' => $this->codes->generate(),
                'customer_id' => $customerId,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guests' => $guests,
                'status' => BookingStatus::PendingPayment,
                'hold_expires_at' => now()->addMinutes(config('booking.hold_minutes')),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'notes' => $notes,
            ]);

            $lines = $booking->bookingUnits()->createMany($unitLines->all());
            $booking->addons()->createMany($addonLines);
            $this->createPreorders($booking, $preorders);

            // Last step: a conflict rolls back the whole booking, including pre-orders.
            $this->ledger->claim($lines);

            return $booking;
        }, (int) config('booking.db_transaction_attempts'));
    }

    /**
     * One kitchen order per serve slot, so each delivery can be prepared on its own.
     *
     * @param  array<int, array{menu_item_id: int, qty: int, serve_date: string, serve_time: string}>  $preorders
     */
    private function createPreorders(Booking $booking, array $preorders): void
    {
        $slots = collect($preorders)->groupBy(fn (array $item) => $item['serve_date'].' '.$item['serve_time']);

        foreach ($slots as $slot => $items) {
            $this->orders->createOrder(
                Order::SOURCE_PREORDER,
                $items->all(),
                $booking->id,
                null,
                $this->serveMoment($slot),
            );
        }
    }

    private function serveMoment(string $slot): CarbonImmutable
    {
        // Wall-clock time in the property timezone, which is the application timezone.
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $slot);
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @param  array<int, int>  $requestedIds
     */
    private function assertUnitsAvailable(Collection $units, array $requestedIds, string $checkIn, string $checkOut): void
    {
        $distinctIds = array_unique($requestedIds);
        $free = Unit::whereIn('id', $distinctIds)->freeBetween($checkIn, $checkOut)->count();

        $allUsable = $units->count() === count($distinctIds)
            && $units->every(fn (Unit $unit) => $unit->status === Unit::STATUS_ACTIVE)
            && $free === count($distinctIds);

        if (! $allUsable) {
            throw UnitUnavailableException::alreadyBooked();
        }
    }

    /**
     * @return array{unit_id: int, check_in: string, check_out: string, price_per_night: int, nights: int, subtotal: int}
     */
    private function unitLine(Unit $unit, string $checkIn, string $checkOut, int $nights): array
    {
        $subtotal = $this->pricing->stayTotal($unit->unitType, $checkIn, $checkOut);

        return [
            'unit_id' => $unit->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            // Average, because weekday/weekend/special nights can differ within one stay.
            'price_per_night' => intdiv($subtotal, max($nights, 1)),
            'nights' => $nights,
            'subtotal' => $subtotal,
        ];
    }

    private function dateString(string $date): string
    {
        return CarbonImmutable::parse($date)->toDateString();
    }
}
