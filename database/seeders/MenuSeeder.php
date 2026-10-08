<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Database\Seeders\Support\SeedImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    /**
     * Placeholder menu. The owner must confirm item names and prices before launch.
     * The last element of each row is the bundled photo under database/seeders/images/menu.
     */
    public function run(): void
    {
        $menu = [
            'Sarapan' => [
                ['Nasi Goreng Kampung', 'Nasi goreng dengan telur mata sapi dan kerupuk.', 28000, 'nasi-goreng'],
                ['Roti Bakar Cokelat Keju', 'Roti tebal dibakar, isi cokelat dan keju.', 20000, 'roti-bakar'],
                ['Bubur Ayam', 'Bubur hangat dengan suwiran ayam dan cakwe.', 22000, 'bubur-ayam'],
            ],
            'Makan Utama' => [
                ['Ayam Bakar Madu', 'Ayam bakar dengan nasi, lalapan, dan sambal.', 38000, 'ayam-bakar'],
                ['Mie Kuah Telur', 'Mie rebus kuah gurih dengan telur dan sayur.', 18000, 'mie-kuah'],
                ['Paket BBQ Dua Orang', 'Daging, sosis, jagung, dan bumbu untuk dibakar sendiri.', 145000, 'paket-bbq'],
            ],
            'Camilan' => [
                ['Kentang Goreng', 'Kentang goreng renyah dengan saus sambal.', 18000, 'kentang-goreng'],
                ['Pisang Goreng Keju', 'Pisang goreng hangat, taburan keju.', 16000, 'pisang-goreng'],
            ],
            'Minuman' => [
                ['Kopi Tubruk', 'Kopi hitam seduh tubruk.', 12000, 'kopi'],
                ['Teh Panas', 'Teh melati panas, gula terpisah.', 8000, 'teh'],
                ['Cokelat Panas', 'Cokelat susu hangat.', 18000, 'cokelat-panas'],
            ],
        ];

        $sortOrder = 0;
        foreach ($menu as $categoryName => $items) {
            $category = MenuCategory::firstOrCreate(['name' => $categoryName], ['sort_order' => ++$sortOrder]);

            foreach ($items as [$name, $description, $price, $image]) {
                $item = MenuItem::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $name],
                    ['description' => $description, 'price' => $price, 'is_available' => true],
                );

                if (! $item->photo) {
                    $item->update([
                        'photo' => SeedImage::publish("menu/{$image}.webp", 'menu-items/'.Str::slug($name).'.webp'),
                    ]);
                }
            }
        }
    }
}
