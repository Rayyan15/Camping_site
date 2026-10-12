<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\KitchenQueue;
use App\Filament\Pages\OkupansiKalender;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;

/**
 * Page header plus the four counts staff check first each morning. Each card only appears for a role
 * that can act on it and links straight to the filtered list behind the number.
 */
class TodayActions extends Widget
{
    protected string $view = 'filament.widgets.today-actions';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $metrics = app(DashboardMetricsService::class);

        return [
            'today' => $metrics->today()->translatedFormat('l, j F Y'),
            'calendarUrl' => OkupansiKalender::canAccess() ? OkupansiKalender::getUrl() : null,
            'cards' => $this->cards($metrics),
        ];
    }

    /** @return list<array{count: int, label: string, caption: string, icon: string, url: string, needsAction: bool}> */
    private function cards(DashboardMetricsService $metrics): array
    {
        $user = auth()->user();
        $cards = [];

        if ($user?->can('record_manual_payment')) {
            $count = $metrics->awaitingTransferProofCount();
            $cards[] = [
                'count' => $count,
                'label' => 'Bukti transfer',
                'caption' => $count > 0 ? 'Menunggu dicocokkan dengan mutasi' : 'Tidak ada yang menunggu',
                'icon' => 'heroicon-o-document-check',
                'url' => PaymentResource::getUrl('index', ['filters' => ['awaiting_proof_review' => ['isActive' => true]]]),
                'needsAction' => $count > 0,
            ];
        }

        if ($user?->can('view_occupancy_dashboard')) {
            $count = $metrics->needsReviewBookingCount();
            $cards[] = [
                'count' => $count,
                'label' => 'Booking ditinjau',
                'caption' => $count > 0 ? 'Perlu keputusan pemilik' : 'Tidak ada yang bermasalah',
                'icon' => 'heroicon-o-exclamation-triangle',
                'url' => BookingResource::getUrl('index', ['tab' => 'needs_review']),
                'needsAction' => $count > 0,
            ];

            $cards[] = [
                'count' => $metrics->checkInsToday(),
                'label' => 'Check-in hari ini',
                'caption' => 'Tamu yang tiba hari ini',
                'icon' => 'heroicon-o-arrow-right-end-on-rectangle',
                'url' => BookingResource::getUrl(),
                'needsAction' => false,
            ];
        }

        if ($user?->can('view_kitchen_queue')) {
            $count = $metrics->activeFoodOrderCount();
            $cards[] = [
                'count' => $count,
                'label' => 'Pesanan dapur',
                'caption' => $count > 0 ? 'Belum selesai disajikan' : 'Dapur sedang kosong',
                'icon' => 'heroicon-o-fire',
                'url' => KitchenQueue::getUrl(),
                'needsAction' => false,
            ];
        }

        return $cards;
    }
}
