<?php

namespace App\Filament\Resources\DiningSpots\Pages;

use App\Filament\Resources\DiningSpots\DiningSpotResource;
use App\Models\DiningSpot;
use App\Services\DiningSpotService;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Url;

class PrintQrSheet extends Page
{
    protected static string $resource = DiningSpotResource::class;

    protected static ?string $title = 'Cetak QR';

    protected string $view = 'filament.pages.dining-spot-qr-sheet';

    #[Url]
    public ?int $spot = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(DiningSpotService::class);

        $sheets = DiningSpot::query()
            ->when($this->spot, fn ($query) => $query->whereKey($this->spot))
            ->orderBy('name')
            ->get()
            ->map(fn (DiningSpot $spot) => [
                'spot' => $spot,
                'svg' => $service->qrSvg($spot),
            ]);

        return ['sheets' => $sheets];
    }
}
