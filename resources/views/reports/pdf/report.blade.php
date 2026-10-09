<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $table->title() }}</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1a1a1a; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        p { margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; border-bottom: 1px solid #1a1a1a; padding: 4px; }
        td { border-bottom: 1px solid #cfcfcf; padding: 4px; vertical-align: top; }
        .num { text-align: right; }
        .note { margin-top: 8px; color: #444444; }
    </style>
</head>
<body>
    <h1>{{ $table->title() }}</h1>
    <p>{{ $business['name'] ?? config('app.name') }}</p>
    <p>Periode {{ $table->period->label() }}</p>

    <table>
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
                        <td @class(['num' => $table->columns[$index]['type']->isNumeric()])>{{ $table->columns[$index]['type']->format($value) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($table->columns) }}">Tidak ada data pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($table->summary !== [])
        <table style="width: 50%;">
            <tbody>
                @foreach ($table->summary as $line)
                    <tr>
                        <td>{{ $line['label'] }}</td>
                        <td class="num">{{ $line['type']->format($line['value']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="note">{{ $table->definition }}</p>
    <p class="note">Dicetak {{ $printedAt }}</p>
</body>
</html>
