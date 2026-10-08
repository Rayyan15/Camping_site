@inject('whatsAppShare', 'App\Services\WhatsAppShareLink')

<div class="flex flex-col gap-3 sm:flex-row">
    @if($booking->isInvoiceable())
        <a href="{{ route('booking.invoice', $booking->code) }}" class="btn btn-primary inline-flex items-center justify-center gap-2">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            Unduh invoice
        </a>
    @else
        <div>
            <span class="inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-full border border-sand bg-cream-deep px-5 py-3 font-semibold text-sand-dark" role="link" aria-disabled="true" aria-describedby="invoice-hint">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                Unduh invoice
            </span>
            <p id="invoice-hint" class="mt-1 text-xs text-ink-soft">Tersedia setelah pembayaran dikonfirmasi.</p>
        </div>
    @endif

    <a href="{{ $whatsAppShare->forBooking($booking) }}" target="_blank" rel="noopener" class="btn btn-outline inline-flex items-center justify-center gap-2">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
        Bagikan ke WhatsApp
    </a>
</div>
