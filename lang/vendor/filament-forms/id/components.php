<?php

// Filament's Indonesian form file lacks these keys, so screen readers announced the raw keys.
return [
    'date_time_picker' => [
        'month_select' => ['label' => 'Bulan'],
        'year_input' => ['label' => 'Tahun'],
        'hour_input' => ['label' => 'Jam'],
        'minute_input' => ['label' => 'Menit'],
        'second_input' => ['label' => 'Detik'],
    ],
    'file_upload' => [
        'actions' => [
            'download' => ['label' => 'Unduh'],
            'open' => ['label' => 'Buka di tab baru'],
        ],
        'editor' => ['label' => 'Editor gambar'],
    ],
    'key_value' => [
        'columns' => [
            'actions' => ['label' => 'Aksi'],
            'reorder' => ['label' => 'Ubah urutan'],
        ],
    ],
    'repeater' => [
        'columns' => [
            'actions' => ['label' => 'Aksi'],
            'reorder' => ['label' => 'Ubah urutan'],
        ],
    ],
    'select' => [
        'actions' => [
            'clear' => ['label' => 'Hapus pilihan'],
            'remove_option' => ['label' => 'Hapus :label'],
        ],
        'search_label' => 'Cari',
    ],
    'tags_input' => [
        'tag_added' => 'Ditambahkan: :tag',
        'tag_removed' => 'Dihapus: :tag',
    ],
];
