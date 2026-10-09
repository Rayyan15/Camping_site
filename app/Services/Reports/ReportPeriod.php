<?php

namespace App\Services\Reports;

use App\Enums\ReportPreset;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Inclusive range of local (Asia/Jakarta) calendar days. Queries use [from, untilExclusive).
 */
final readonly class ReportPeriod
{
    public const MAX_DAYS = 366;

    private function __construct(public CarbonImmutable $from, public CarbonImmutable $to) {}

    public static function thisMonth(?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();

        return new self($now->startOfMonth()->startOfDay(), $now->endOfMonth()->startOfDay());
    }

    public static function lastMonth(?CarbonImmutable $now = null): self
    {
        $anchor = ($now ?? CarbonImmutable::now())->startOfMonth()->subMonthNoOverflow();

        return new self($anchor->startOfMonth()->startOfDay(), $anchor->endOfMonth()->startOfDay());
    }

    /**
     * @throws InvalidReportPeriodException
     */
    public static function custom(?string $from, ?string $to): self
    {
        if (blank($from) || blank($to)) {
            throw InvalidReportPeriodException::missing();
        }

        try {
            $start = CarbonImmutable::parse($from)->startOfDay();
            $end = CarbonImmutable::parse($to)->startOfDay();
        } catch (InvalidFormatException) {
            throw InvalidReportPeriodException::missing();
        }

        if ($end->lt($start)) {
            throw InvalidReportPeriodException::reversed();
        }

        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw InvalidReportPeriodException::tooLong(self::MAX_DAYS);
        }

        return new self($start, $end);
    }

    /**
     * @throws InvalidReportPeriodException
     */
    public static function fromPreset(ReportPreset $preset, ?string $from = null, ?string $to = null): self
    {
        return match ($preset) {
            ReportPreset::ThisMonth => self::thisMonth(),
            ReportPreset::LastMonth => self::lastMonth(),
            ReportPreset::Custom => self::custom($from, $to),
        };
    }

    public function untilExclusive(): CarbonImmutable
    {
        return $this->to->addDay();
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function label(): string
    {
        return $this->from->format('d/m/Y').' sampai '.$this->to->format('d/m/Y');
    }
}
