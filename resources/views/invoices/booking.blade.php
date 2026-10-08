@php
    $rupiah = fn (int $amount): string => 'Rp '.number_format($amount, 0, ',', '.');
    $lines = array_merge(
        array_map(fn ($l) => $l + ['group' => 'Akomodasi'], $unitLines),
        array_map(fn ($l) => $l + ['group' => 'Tambahan'], $addonLines),
        array_map(fn ($l) => $l + ['group' => 'Pesanan makanan dan minuman'], $foodLines),
    );
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        @page { margin: 40px 44px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2a22; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #4a5a4f; }
        .label { font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #7a6a47; }
        .brand { font-size: 20px; font-weight: bold; color: #1c3526; }
        .title { font-size: 20px; letter-spacing: 3px; color: #b3441f; text-align: right; }
        .rule { border-top: 2px solid #1c3526; margin: 14px 0 18px; }
        .meta td { vertical-align: top; padding-bottom: 12px; }
        .items th { text-align: left; font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #7a6a47; border-bottom: 1px solid #1c3526; padding: 6px 4px; }
        .items td { padding: 7px 4px; border-bottom: 1px solid #e4d6b8; vertical-align: top; }
        .group td { background: #f7f1e5; font-weight: bold; color: #1c3526; font-size: 9px; }
        .right { text-align: right; }
        .totals { width: 55%; margin-left: 45%; margin-top: 14px; }
        .totals td { padding: 4px; }
        .grand td { border-top: 2px solid #1c3526; font-weight: bold; font-size: 12px; color: #1c3526; padding-top: 8px; }
        .stamp { display: inline-block; border: 2px solid #1c3526; color: #1c3526; padding: 3px 12px; font-weight: bold; letter-spacing: 2px; font-size: 12px; }
        .stamp.unpaid { border-color: #b3441f; color: #b3441f; }
        .notes { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e4d6b8; font-size: 9px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <div class="brand">{{ $business['name'] ?? '' }}</div>
                @if(! empty($business['address']))
                    <div class="muted">{{ $business['address'] }}</div>
                @endif
                @if(! empty($business['whatsapp_number']))
                    <div class="muted">WhatsApp {{ $business['whatsapp_number'] }}</div>
                @endif
            </td>
            <td>
                <div class="title">INVOICE</div>
                <div class="right muted">{{ $invoiceNumber }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="meta">
        <tr>
            <td style="width: 34%">
                <div class="label">Ditagihkan kepada</div>
                <strong>{{ $booking->customer?->name ?? '-' }}</strong>
                @if($booking->customer?->phone)
                    <div class="muted">{{ $booking->customer->phone }}</div>
                @endif
                @if($booking->customer?->email)
                    <div class="muted">{{ $booking->customer->email }}</div>
                @endif
            </td>
            <td style="width: 33%">
                <div class="label">Kode booking</div>
                <strong>{{ $booking->code }}</strong>
                <div class="label" style="margin-top: 8px">Tamu</div>
                {{ $booking->guests }} orang
            </td>
            <td style="width: 33%">
                <div class="label">Menginap</div>
                {{ $booking->check_in->format('d/m/Y') }} sampai {{ $booking->check_out->format('d/m/Y') }}
                <div class="label" style="margin-top: 8px">Status</div>
                <span class="stamp {{ $isSettled ? '' : 'unpaid' }}">{{ $isSettled ? 'LUNAS' : 'BELUM LUNAS' }}</span>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th class="right" style="width: 8%">Jml</th>
                <th class="right" style="width: 22%">Harga</th>
                <th class="right" style="width: 22%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $currentGroup = null; @endphp
            @foreach($lines as $line)
                @if($line['group'] !== $currentGroup)
                    @php $currentGroup = $line['group']; @endphp
                    <tr class="group"><td colspan="4">{{ $currentGroup }}</td></tr>
                @endif
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td class="right">{{ $line['qty'] }}</td>
                    <td class="right">{{ $rupiah($line['price']) }}</td>
                    <td class="right">{{ $rupiah($line['subtotal']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $rupiah($subtotal) }}</td></tr>
        <tr><td>Pajak</td><td class="right">{{ $rupiah($booking->tax) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ $rupiah($grandTotal) }}</td></tr>
        <tr><td>Dibayar</td><td class="right">{{ $rupiah($booking->paid_amount) }}</td></tr>
        <tr><td>Sisa tagihan</td><td class="right">{{ $rupiah($balance) }}</td></tr>
    </table>

    <div class="notes muted">
        @if($booking->notes)
            <div><strong>Catatan:</strong> {{ $booking->notes }}</div>
        @endif
        <div>Invoice ini dibuat otomatis dan sah tanpa tanda tangan. Simpan kode booking {{ $booking->code }} untuk check-in.</div>
        <div>Terima kasih telah menginap di {{ $business['name'] ?? 'tempat kami' }}.</div>
    </div>
</body>
</html>
