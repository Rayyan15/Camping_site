@php
    $brand = config('brand');
    $forest = $brand['forest']['700'];
    $ember = $brand['ember'];
    $ink = $brand['ink'];
    $muted = $brand['neutral']['600'];
    $rule = $brand['neutral']['300'];
    // Labels carry the unit ("(Rp)", "(%)"), so figures print bare and line up like a financial table.
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
         * Financial print layout: a heavy ink masthead rule, a serif headline over sans data,
         * one lead figure, a ruled key-stats strip, and a table with right-aligned monospaced figures
         * so columns of rupiah line up digit for digit. Ember marks section labels only.
         */
        @page { margin: 28px 32px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: {{ $ink }}; line-height: 1.35; }
        .footer { position: fixed; bottom: -26px; left: 0; right: 0; border-top: 0.75px solid {{ $rule }}; padding-top: 5px; font-size: 7px; color: {{ $muted }}; }
        .footer table { width: 100%; border-collapse: collapse; }
        .footer td { padding: 0; border: 0; }
        .pagenum:before { content: counter(page); }

        .masthead { width: 100%; border-collapse: collapse; border-top: 4px solid {{ $ink }}; }
        .masthead td { padding: 6px 0 0; vertical-align: top; border: 0; }
        .brand { font-size: 8px; font-weight: bold; color: {{ $forest }}; letter-spacing: 0.4px; }
        .stamp { text-align: right; font-size: 7.5px; color: {{ $muted }}; }
        h1 { font-family: 'DejaVu Serif', serif; font-size: 22px; line-height: 1.1; margin: 8px 0 3px; }
        .dek { font-size: 9px; color: {{ $muted }}; margin: 0 0 10px; }
        .period { border-top: 0.75px solid {{ $ink }}; border-bottom: 0.75px solid {{ $ink }}; padding: 4px 0; margin-bottom: 14px; font-size: 8px; }
        .period strong { color: {{ $ink }}; }

        .label { font-size: 7px; font-weight: bold; color: {{ $ember }}; letter-spacing: 0.3px; }
        .overview { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .overview > tbody > tr > td, .overview > tr > td { border: 0; padding: 0; vertical-align: top; }
        .lead { width: 24%; padding-right: 14px !important; border-top: 4px solid {{ $forest }} !important; padding-top: 5px !important; }
        .lead .figure { font-size: 30px; color: {{ $forest }}; font-weight: bold; line-height: 1; margin-top: 3px; }

        .stats { width: 100%; border-collapse: collapse; border-top: 1.5px solid {{ $ink }}; }
        .stats td { width: 33%; padding: 5px 10px 6px 0; border-bottom: 0.75px solid {{ $rule }}; vertical-align: top; }
        .stats .name { font-size: 7.5px; color: {{ $muted }}; }
        .stats .value { font-family: 'DejaVu Sans Mono', monospace; font-size: 11px; font-weight: bold; margin-top: 2px; }

        .section { margin-bottom: 4px; }
        .data { width: 100%; border-collapse: collapse; }
        .data th { font-size: 7px; text-align: left; padding: 5px 6px 4px; border-top: 1.5px solid {{ $ink }}; border-bottom: 0.75px solid {{ $ink }}; vertical-align: bottom; }
        .data td { padding: 4px 6px; border-bottom: 0.5px solid {{ $rule }}; vertical-align: top; }
        .data th:first-child, .data td:first-child { padding-left: 0; }
        .data th.num, .data td.num { text-align: right; white-space: nowrap; }
        .data td.num { font-family: 'DejaVu Sans Mono', monospace; }
        .data td:first-child { font-weight: bold; }
        .empty { padding: 14px 0; color: {{ $muted }}; }
        .count { margin-top: 5px; font-size: 7.5px; color: {{ $muted }}; }

        .note { margin-top: 14px; border-top: 1.5px solid {{ $ink }}; padding-top: 5px; width: 75%; }
        .note p { margin: 3px 0 0; color: {{ $ink }}; }
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
        <table class="overview">
            <tr>
                <td class="lead">
                    <div class="label">{{ $lead['label'] }}</div>
                    <div class="figure">{{ $figure($lead['type'], $lead['value']) }}</div>
                </td>
                <td>
                    @if ($stats !== [])
                        <table class="stats">
                            @foreach (array_chunk($stats, 3) as $chunk)
                                <tr>
                                    @foreach ($chunk as $line)
                                        <td>
                                            <div class="name">{{ $line['label'] }}</div>
                                            <div class="value">{{ $figure($line['type'], $line['value']) }}</div>
                                        </td>
                                    @endforeach
                                    @for ($i = count($chunk); $i < 3; $i++)
                                        <td></td>
                                    @endfor
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <div class="label section">Rincian</div>
    <table class="data">
        <thead>
            <tr>
                @foreach ($table->columns as $column)
                    <th @class(['num' => $column['type']->isNumeric()])>{{ $column['label'] }}</th>
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

    @if ($table->definition !== '')
        <div class="note">
            <div class="label">Catatan</div>
            <p>{{ $table->definition }}</p>
        </div>
    @endif
</body>
</html>
