<?php

namespace App\Http\Controllers\Public;

use App\Enums\OrderStatus;
use App\Enums\QrPaymentChoice;
use App\Exceptions\MenuItemUnavailableException;
use App\Exceptions\OrderBillingException;
use App\Exceptions\TransferProofException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreQrOrderRequest;
use App\Http\Requests\Public\UploadTransferProofRequest;
use App\Models\DiningSpot;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Services\DiningSpotService;
use App\Services\OrderService;
use App\Services\Payment\OrderTransferProofService;
use App\Support\BankAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QrOrderController extends Controller
{
    public function __construct(
        private readonly DiningSpotService $diningSpots,
        private readonly OrderService $orders,
        private readonly OrderTransferProofService $proofs,
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
            'choices' => $this->diningSpots->paymentChoicesFor($spot),
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

        return redirect()->route('qr.track', [$spot->qr_token, $order->code]);
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
            'steps' => OrderStatus::steps(),
            'progress' => $this->progress($order),
            'awaitingTransfer' => $order->payment_choice === QrPaymentChoice::Transfer && ! $order->isPaid() && $order->statusEnum() !== OrderStatus::Dibatalkan,
            'proof' => $this->proofs->latestProof($order),
            'bank' => BankAccount::current(),
        ]);
    }

    public function uploadProof(UploadTransferProofRequest $request, string $token, string $code): RedirectResponse
    {
        $spot = $this->spotOrFail($token);
        $order = $this->orderOrFail($spot, $code);

        try {
            $this->proofs->submit($order, $request->file('proof'));
        } catch (TransferProofException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('qr.track', [$spot->qr_token, $order->code])
            ->with('status', 'Bukti transfer terkirim. Dapur mengecek, lalu pesanan mulai dimasak.');
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
            'step' => $status === OrderStatus::Dibatalkan ? -1 : (int) array_search($status, OrderStatus::steps(), true),
            'paid' => $order->isPaid(),
            'billed_to_booking' => $order->bill_to_booking,
        ];
    }
}
