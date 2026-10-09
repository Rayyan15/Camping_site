<?php

namespace App\Http\Controllers\Public;

use App\Enums\OrderStatus;
use App\Enums\QrPaymentChoice;
use App\Exceptions\MenuItemUnavailableException;
use App\Exceptions\OrderBillingException;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreQrOrderRequest;
use App\Models\DiningSpot;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Services\DiningSpotService;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QrOrderController extends Controller
{
    public function __construct(
        private readonly DiningSpotService $diningSpots,
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
    ) {}

    public function show(string $token): View
    {
        $spot = $this->spotOrFail($token);

        $categories = MenuCategory::query()
            ->with(['items' => fn ($items) => $items->available()->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (MenuCategory $category) => $category->items->isNotEmpty());

        return view('public.qr-menu', [
            'spot' => $spot,
            'categories' => $categories,
            'canBillToBooking' => $this->diningSpots->activeBooking($spot) !== null,
            'choices' => QrPaymentChoice::cases(),
        ]);
    }

    public function store(StoreQrOrderRequest $request, string $token): RedirectResponse
    {
        $spot = $this->spotOrFail($token);

        try {
            $order = $this->orders->createQrOrder(
                $spot,
                $request->validated('items'),
                $request->validated('customer_name'),
                $request->validated('customer_phone'),
                $request->paymentChoice(),
            );
        } catch (MenuItemUnavailableException|OrderBillingException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        if ($order->awaitsOnlinePayment()) {
            return $this->sendToPayment($spot, $order);
        }

        return redirect()->route('qr.track', [$spot->qr_token, $order->code]);
    }

    /**
     * Retry from the tracking page when the first online payment was closed or failed.
     */
    public function pay(string $token, string $code): RedirectResponse
    {
        $spot = $this->spotOrFail($token);
        $order = $this->orderOrFail($spot, $code);

        if (! $order->awaitsOnlinePayment()) {
            return redirect()->route('qr.track', [$spot->qr_token, $order->code]);
        }

        return $this->sendToPayment($spot, $order);
    }

    /**
     * Gateway detail stays in the log; the guest gets fixed copy and can still pay at the cashier.
     */
    private function sendToPayment(DiningSpot $spot, Order $order): RedirectResponse
    {
        try {
            return redirect()->away($this->payments->initiateForOrder($order)->redirectUrl);
        } catch (PaymentException $e) {
            Log::warning('QR order online payment could not be started.', ['order' => $order->code, 'reason' => $e->getMessage()]);

            return redirect()->route('qr.track', [$spot->qr_token, $order->code])
                ->with('error', 'Pembayaran online belum bisa dibuka. Coba lagi sebentar lagi, atau bayar ke kasir.');
        }
    }

    public function track(Request $request, string $token, string $code): View|JsonResponse
    {
        $spot = $this->spotOrFail($token);
        $order = $this->orderOrFail($spot, $code);

        if ($request->wantsJson()) {
            return response()->json($this->progress($order));
        }

        return view('public.qr-track', [
            'spot' => $spot,
            'order' => $order,
            'steps' => OrderStatus::cases(),
            'progress' => $this->progress($order),
        ]);
    }

    private function spotOrFail(string $token): DiningSpot
    {
        return $this->diningSpots->findByToken($token) ?? abort(404);
    }

    private function orderOrFail(DiningSpot $spot, string $code): Order
    {
        return Order::with('items.menuItem')
            ->where('code', $code)
            ->where('dining_spot_id', $spot->id)
            ->firstOrFail();
    }

    /**
     * @return array{status: string, label: string, step: int, paid: bool, billed_to_booking: bool}
     */
    private function progress(Order $order): array
    {
        $status = $order->statusEnum();

        return [
            'status' => $status->value,
            'label' => $status->label(),
            'step' => array_search($status, OrderStatus::cases(), true),
            'paid' => $order->isPaid(),
            'billed_to_booking' => $order->bill_to_booking,
        ];
    }
}
