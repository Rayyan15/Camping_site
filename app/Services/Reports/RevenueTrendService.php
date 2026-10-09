<?php

namespace App\Services\Reports;

use App\Enums\RevenueGranularity;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonImmutable;

/**
 * Net revenue over time, split into camping and food. Uses the same settled, in-minus-out rule as
 * DashboardMetricsService::monthlyRevenue, so a refund lowers the camping line on the day it was paid out.
 */
class RevenueTrendService
{
    /**
     * @return array{labels: list<string>, camping: list<int>, food: list<int>}
     */
    public function series(ReportPeriod $period, RevenueGranularity $granularity): array
    {
        $buckets = $this->emptyBuckets($period, $granularity);
        $campingType = (new Booking)->getMorphClass();
        $foodType = (new Order)->getMorphClass();

        $payments = Payment::query()
            ->settled()
            ->paidBetween($period->from->toDateTimeString(), $period->untilExclusive()->toDateTimeString())
            ->withSignedAmount()
            ->toBase()
            ->cursor();

        foreach ($payments as $payment) {
            $key = $this->bucketStart(CarbonImmutable::parse($payment->paid_at), $period, $granularity)->toDateString();
            $line = match ($payment->payable_type) {
                $campingType => 'camping',
                $foodType => 'food',
                default => null,
            };

            if ($line !== null) {
                $buckets[$key][$line] += (int) $payment->signed_amount;
            }
        }

        return [
            'labels' => array_column($buckets, 'label'),
            'camping' => array_column($buckets, 'camping'),
            'food' => array_column($buckets, 'food'),
        ];
    }

    /** @return array<string, array{label: string, camping: int, food: int}> */
    private function emptyBuckets(ReportPeriod $period, RevenueGranularity $granularity): array
    {
        $buckets = [];

        for ($day = $period->from; $day->lte($period->to); $day = $day->addDay()) {
            $start = $this->bucketStart($day, $period, $granularity);
            $buckets[$start->toDateString()] ??= [
                'label' => $this->label($start, $granularity),
                'camping' => 0,
                'food' => 0,
            ];
        }

        return $buckets;
    }

    /** First day of the bucket a day falls in, never earlier than the period start. */
    private function bucketStart(CarbonImmutable $moment, ReportPeriod $period, RevenueGranularity $granularity): CarbonImmutable
    {
        $day = $moment->startOfDay();
        $start = match ($granularity) {
            RevenueGranularity::Day => $day,
            RevenueGranularity::Week => $day->startOfWeek(),
            RevenueGranularity::Month => $day->startOfMonth(),
        };

        return $start->lt($period->from) ? $period->from : $start;
    }

    private function label(CarbonImmutable $start, RevenueGranularity $granularity): string
    {
        return match ($granularity) {
            RevenueGranularity::Day => $start->format('d/m'),
            RevenueGranularity::Week => 'Mgg '.$start->format('d/m'),
            RevenueGranularity::Month => $start->format('m/Y'),
        };
    }
}
