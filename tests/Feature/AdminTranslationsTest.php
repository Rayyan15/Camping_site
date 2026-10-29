<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminTranslationsTest extends TestCase
{
    public function test_indonesian_validation_and_table_strings_are_translated(): void
    {
        app()->setLocale('id');

        $this->assertSame('Nama wajib diisi.', __('validation.required', ['attribute' => 'Nama']));
        $this->assertSame('3 hasil', trans_choice('filament-tables::table.result_count', 3));
    }
}
