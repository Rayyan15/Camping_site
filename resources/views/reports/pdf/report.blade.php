@php
    $forest = config('brand.forest.700');
    $display = fn ($type, $value) => ($type === \App\Enums\ReportValueType::Money ? 'Rp ' : '').$type->format($value).($type === \App\Enums\ReportValueType::Percent ? '%' : '');
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $table->title() }}</title>
    <style>
        /* Print layout: a forest rule under the letterhead, summary figures before the rows, and a footer on every page. */
        @page { margin: 30px 32px 44px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1a1a1a; }
        .letterhead { width: 100%; border-collapse: collapse; border-bottom: 2px solid {{ $forest }}; margin-bottom: 12px; }
        .letterhead td { border: 0; padding: 0 0 8px; vertical-align: bottom; }
        .business { font-size: 10px; font-weight: bold; color: {{ $forest }}; }
        h1 { font-size: 16px; margin: 2px 0 0; }
        .period { text-align: right; font-size: 9px; color: #333333; }
        .period strong { display: block; font-size: 10px; color: #1a1a1a; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
        .summary td { background: #f1f6f2; border: 1px solid #c4d7c9; padding: 6px 8px; vertical-align: top; }
        .summary .label { font-size: 7.5px; color: #333333; }
        .summary .value { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .data { width: 100%; border-collapse: collapse; }
        .data th { background: {{ $forest }}; color: #ffffff; text-align: center; padding: 5px 6px; font-size: 8px; }
        .data td { border-bottom: 1px solid #dddddd; padding: 5px 6px; vertical-align: top; text-align: center; }
        .data tbody tr:nth-child(even) td { background: #f6f7f6; }
        .data td:first-child { font-weight: bold; }
        .num { white-space: nowrap; }
        .count { margin-top: 6px; color: #333333; }
        .note { margin-top: 10px; padding-top: 6px; border-top: 1px solid #dddddd; color: #333333; }
        .footer { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 7.5px; color: #555555; border-top: 1px solid #dddddd; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="footer">{{ $business['name'] ?? config('app.name') }} &middot; {{ $table->title() }} &middot; Dicetak {{ $printedAt }}</div>

    <table class="letterhead">
        <tr>
            <td>
                <div class="business">{{ $business['name'] ?? config('app.name') }}</div>
                <h1>{{ $table->title() }}</h1>
            </td>
            <td class="period">Periode<strong>{{ $table->period->label() }}</strong></td>
        </tr>
    </table>

    @if ($table->summary !== [])
        <table class="summary">
            <tr>
                @foreach ($table->summary as $line)
                    <td>
                        <div class="label">{{ $line['label'] }}</div>
                        <div class="value">{{ $display($line['type'], $line['value']) }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

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
                        <td @class(['num' => $table->columns[$index]['type']->isNumeric()])>{{ $display($table->columns[$index]['type'], $value) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($table->columns) }}">Tidak ada data pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($table->rows !== [])
        <p class="count">{{ count($table->rows) }} baris</p>
    @endif

    @if ($table->definition !== '')
        <p class="note">{{ $table->definition }}</p>
    @endif
</body>
</html>
