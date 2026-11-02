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

    // Bare layout without sidebar, topbar or scroll container: the full panel layout printed blank pages.
    public function getLayout(): string
    {
        return 'filament-panels::components.layout.base';
    }

    #[Url]
    public ?int $spot = null;

    #[Url]
    public ?string $type = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(DiningSpotService::class);

        $sheets = DiningSpot::query()
            ->when($this->spot, fn ($query) => $query->whereKey($this->spot))
            ->when(! $this->spot && in_array($this->type, [DiningSpot::TYPE_TABLE, DiningSpot::TYPE_TENT], true), fn ($query) => $query->where('type', $this->type))
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (DiningSpot $spot) => [
                'spot' => $spot,
                'svg' => $service->qrSvg($spot),
            ]);

        return [
            'sheets' => $sheets,
            'filters' => [
                '' => 'Semua',
                DiningSpot::TYPE_TABLE => 'Meja',
                DiningSpot::TYPE_TENT => 'Tenda',
            ],
        ];
    }
}
