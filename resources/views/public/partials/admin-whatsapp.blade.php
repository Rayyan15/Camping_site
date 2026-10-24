@inject('whatsAppShare', 'App\Services\WhatsAppShareLink')

@php($adminLink = $whatsAppShare->forAdmin($booking))

@if($adminLink)
    <a href="{{ $adminLink }}" target="_blank" rel="noopener" class="{{ $class ?? 'btn btn-outline' }} inline-flex items-center justify-center gap-2">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
        {{ __('Tanya admin via WhatsApp') }}
        <span class="sr-only">{{ __('(membuka WhatsApp di tab baru)') }}</span>
    </a>
@endif
