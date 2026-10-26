<?php

namespace App\Support;

use Carbon\Carbon;
use RuntimeException;

class BillingCycle
{
    public static function previousPeriodStart(Carbon $periodEnd, string $interval, int $count): Carbon
    {
        self::validateCount($count);

        return match ($interval) {
            'day' => $periodEnd->copy()->subDays($count),
            'week' => $periodEnd->copy()->subWeeks($count),
            'month' => $periodEnd->copy()->subMonthsNoOverflow($count),
            'year' => $periodEnd->copy()->subYearsNoOverflow($count),
            default => throw new RuntimeException("Unsupported billing interval: {$interval}"),
        };
    }

    public static function nextPeriodEnd(Carbon $periodEnd, string $interval, int $count): Carbon
    {
        self::validateCount($count);

        return match ($interval) {
            'day' => $periodEnd->copy()->addDays($count),
            'week' => $periodEnd->copy()->addWeeks($count),
            'month' => $periodEnd->copy()->addMonthsNoOverflow($count),
            'year' => $periodEnd->copy()->addYearsNoOverflow($count),
            default => throw new RuntimeException("Unsupported billing interval: {$interval}"),
        };
    }

    private static function validateCount(int $count): void
    {
        if ($count < 1) {
            throw new RuntimeException('The plan billing interval count must be positive.');
        }
    }
}
