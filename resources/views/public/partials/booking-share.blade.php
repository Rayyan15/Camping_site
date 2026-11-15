@inject('whatsAppShare', 'App\Services\WhatsAppShareLink')

{{-- One action row: invoice leads, the two WhatsApp links follow with distinct icons so they do not read as duplicates. --}}
<div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start">
    @if($booking->isInvoiceable())
        <a href="{{ route('booking.invoice', $booking->access_token) }}" class="btn btn-primary inline-flex items-center justify-center gap-2">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            {{ __('Unduh invoice') }}
        </a>
    @else
        <div>
            <span class="inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-full border border-sand bg-cream-deep px-5 py-3 font-semibold text-sand-dark" role="link" aria-disabled="true" aria-describedby="invoice-hint">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                {{ __('Unduh invoice') }}
            </span>
            <p id="invoice-hint" class="mt-1 text-xs text-ink-soft">{{ __('Tersedia setelah pembayaran dikonfirmasi.') }}</p>
        </div>
    @endif

    <a href="{{ $whatsAppShare->forBooking($booking) }}" target="_blank" rel="noopener" class="btn btn-outline inline-flex items-center justify-center gap-2">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z"/></svg>
        {{ __('Bagikan ke WhatsApp') }}
    </a>

    @include('public.partials.admin-whatsapp', ['booking' => $booking])
</div>
