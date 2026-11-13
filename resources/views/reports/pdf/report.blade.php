@php
    $brand = config('brand');
    $forest = $brand['forest']['700'];
    $ember = $brand['ember'];
    $ink = $brand['ink'];
    // Print-safe greys: muted text stays dark enough for office laser printers, rules dark enough not to vanish.
    $muted = $brand['neutral']['700'];
    $rule = $brand['neutral']['500'];
    // Labels carry the unit ("(Rp)", "(%)"), so figures print bare like a financial table.
    $figure = fn ($type, $value) => $type->format($value);
    $lead = $table->summary[0] ?? null;
    $stats = array_slice($table->summary, 1);
    $business = $business['name'] ?? config('app.name');
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $table->title() }}</title>
    <style>
        /*
         * Page 1 is the summary the owner reads first; the detail rows start on page 2 with the
         * header repeated on every page. Sizes and greys are chosen to survive a black and white print.
         */
        @page { margin: 30px 34px 50px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: {{ $ink }}; line-height: 1.4; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; border-top: 1px solid {{ $rule }}; padding-top: 5px; font-size: 8px; color: {{ $muted }}; }
        .footer table { width: 100%; border-collapse: collapse; }
        .footer td { padding: 0; border: 0; }
        .pagenum:before { content: counter(page); }

        .masthead { width: 100%; border-collapse: collapse; border-top: 5px solid {{ $ink }}; }
        .masthead td { padding: 6px 0 0; vertical-align: top; border: 0; }
        .brand { font-size: 9px; font-weight: bold; color: {{ $forest }}; }
        .stamp { text-align: right; font-size: 8.5px; color: {{ $muted }}; }
        h1 { font-family: 'DejaVu Serif', serif; font-size: 26px; line-height: 1.1; margin: 10px 0 4px; }
        .dek { font-size: 11px; color: {{ $muted }}; margin: 0 0 12px; }
        .period { border-top: 1px solid {{ $ink }}; border-bottom: 1px solid {{ $ink }}; padding: 5px 0; margin-bottom: 20px; font-size: 10px; }

        .label { font-size: 9px; font-weight: bold; color: {{ $ember }}; }
        .lead { border-top: 5px solid {{ $forest }}; padding-top: 6px; margin-bottom: 22px; }
        .lead .figure { font-size: 42px; font-weight: bold; line-height: 1.05; color: {{ $forest }}; margin-top: 4px; }

        .stats { width: 100%; border-collapse: collapse; border-top: 2px solid {{ $ink }}; margin-top: 4px; }
        .stats td { padding: 7px 0; border-bottom: 1px solid {{ $rule }}; font-size: 11px; }
        .stats td.value { text-align: right; font-family: 'DejaVu Sans Mono', monospace; font-weight: bold; font-size: 12px; white-space: nowrap; }

        .note { margin-top: 24px; border-top: 2px solid {{ $ink }}; padding-top: 6px; }
        .note p { margin: 4px 0 0; font-size: 10px; }

        .detail { page-break-before: always; }
        .detail-head { border-top: 5px solid {{ $ink }}; padding-top: 6px; margin-bottom: 10px; }
        .detail-head h2 { font-family: 'DejaVu Serif', serif; font-size: 16px; margin: 4px 0 0; }
        .data { width: 100%; border-collapse: collapse; }
        .data thead { display: table-header-group; }
        .data tr { page-break-inside: avoid; }
        .data th { font-size: 9px; text-align: center; padding: 6px 5px; border-top: 2px solid {{ $ink }}; border-bottom: 1.5px solid {{ $ink }}; vertical-align: middle; }
        .data td { font-size: 9.5px; text-align: center; padding: 6px 5px; border-bottom: 1px solid {{ $rule }}; vertical-align: middle; }
        .data td.num { font-family: 'DejaVu Sans Mono', monospace; white-space: nowrap; }
        .data td:first-child { font-weight: bold; }
        .empty { padding: 16px 0; }
        .count { margin-top: 6px; font-size: 9px; color: {{ $muted }}; }
    </style>
</head>
<body>
    <div class="footer">
        <table>
            <tr>
                <td>{{ $business }} &middot; {{ $table->title() }} &middot; {{ $table->period->label() }}</td>
                <td style="text-align: right">Halaman <span class="pagenum"></span></td>
            </tr>
        </table>
    </div>

    <table class="masthead">
        <tr>
            <td class="brand">{{ $business }} &middot; Laporan</td>
            <td class="stamp">Dicetak {{ $printedAt }}</td>
        </tr>
    </table>
    <h1>{{ $table->type->label() }}</h1>
    <p class="dek">{{ $table->type->question() }}</p>
    <div class="period">Periode <strong>{{ $table->period->label() }}</strong></div>

    @if ($lead)
        <div class="lead">
            <div class="label">{{ $lead['label'] }}</div>
            <div class="figure">{{ $figure($lead['type'], $lead['value']) }}</div>
        </div>
    @endif

    @if ($stats !== [])
        <div class="label">Ringkasan</div>
        <table class="stats">
            @foreach ($stats as $line)
                <tr>
                    <td>{{ $line['label'] }}</td>
                    <td class="value">{{ $figure($line['type'], $line['value']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($table->definition !== '')
        <div class="note">
            <div class="label">Catatan</div>
            <p>{{ $table->definition }}</p>
        </div>
    @endif

    <div class="detail">
        <div class="detail-head">
            <div class="label">Rincian</div>
            <h2>{{ $table->type->label() }}, {{ $table->period->label() }}</h2>
        </div>
        <table class="data">
            <thead>
                <tr>
                    @foreach ($table->columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($table->rows as $row)
                    <tr>
                        @foreach ($row as $index => $value)
                            <td @class(['num' => $table->columns[$index]['type']->isNumeric()])>{{ $figure($table->columns[$index]['type'], $value) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="empty" colspan="{{ count($table->columns) }}">Tidak ada data pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($table->rows !== [])
            <p class="count">{{ count($table->rows) }} baris{{ $table->truncated ? ', dipotong. Unduh Excel untuk data lengkap.' : '' }}</p>
        @endif
    </div>
</body>
</html>
