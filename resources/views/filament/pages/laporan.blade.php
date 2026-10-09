<x-filament-panels::page>
    <style>
        .lap-table-wrap { overflow-x: auto; border: 1px solid var(--gray-200); border-radius: 0.5rem; }
        .lap-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .lap-table th { text-align: left; padding: 0.625rem 0.75rem; font-weight: 600; border-bottom: 1px solid var(--gray-300); white-space: nowrap; }
        .lap-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--gray-100); }
        .lap-num { text-align: right !important; font-variant-numeric: tabular-nums; }
        .lap-summary { display: flex; flex-wrap: wrap; gap: 0.5rem 2rem; margin-top: 1rem; }
        .lap-summary dt { font-size: 0.75rem; color: var(--gray-600); }
        .lap-summary dd { margin: 0; font-size: 1.125rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        .lap-note { margin-top: 1rem; max-width: 70ch; font-size: 0.8125rem; color: var(--gray-600); line-height: 1.5; }
        .lap-empty, .lap-error { padding: 1.5rem 1rem; font-size: 0.875rem; }
        .lap-error { color: var(--danger-700); }
        .dark .lap-table-wrap, .dark .lap-table th, .dark .lap-table td { border-color: var(--gray-700); }
        .dark .lap-summary dt, .dark .lap-note { color: var(--gray-400); }
        .dark .lap-error { color: var(--danger-400); }
    </style>

    {{ $this->form }}

    <div wire:loading.delay class="lap-note" role="status">Memuat laporan...</div>

    @if ($error)
        <p class="lap-error" role="alert">{{ $error }}</p>
    @else
        <p class="lap-note" style="margin-top: 0;">{{ $report->title() }}, periode {{ $report->period->label() }}</p>

        <div class="lap-table-wrap">
            <table class="lap-table">
                <thead>
                    <tr>
                        @foreach ($report->columns as $column)
                            <th scope="col" @class(['lap-num' => $column['type']->isNumeric()])>{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report->rows as $row)
                        <tr>
                            @foreach ($row as $index => $value)
                                <td @class(['lap-num' => $report->columns[$index]['type']->isNumeric()])>{{ $report->columns[$index]['type']->format($value) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($report->columns) }}" class="lap-empty">Tidak ada data pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($report->summary !== [])
            <dl class="lap-summary">
                @foreach ($report->summary as $line)
                    <div>
                        <dt>{{ $line['label'] }}</dt>
                        <dd>{{ $line['type']->format($line['value']) }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <p class="lap-note">{{ $report->definition }}</p>
    @endif
</x-filament-panels::page>
