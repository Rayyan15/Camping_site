<?php

// Filament's Indonesian table file lacks these keys, so raw keys showed in headers and screen readers.
return [
    'result_count' => '{0} Tidak ada hasil|[1,*] :count hasil',
    'loading' => 'Memuat...',
    'column_manager' => [
        'actions' => [
            'reorder' => [
                'label' => 'Ubah urutan kolom',
            ],
        ],
    ],
    'columns' => [
        'icon' => [
            'boolean' => [
                'true' => 'Ya',
                'false' => 'Tidak',
            ],
        ],
    ],
    'actions' => [
        'reorder_record' => [
            'label' => 'Ubah urutan :key',
        ],
        'toggle_record_content' => [
            'label' => 'Buka/tutup :key',
        ],
    ],
];
