<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams payment proofs from the private disk to staff who may see that payment. */
class PaymentProofController extends Controller
{
    private const DISK = 'local';

    public function __invoke(Payment $payment): StreamedResponse
    {
        Gate::authorize('viewProof', $payment);

        $disk = Storage::disk(self::DISK);
        abort_unless($payment->proof_path && $disk->exists($payment->proof_path), 404);

        return $disk->response($payment->proof_path, null, [
            'Content-Disposition' => 'inline; filename="'.basename($payment->proof_path).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
