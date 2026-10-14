<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DateFormatter;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DateFormatterTest extends TestCase
{
    public function test_formats_date_per_prd_rule_11(): void
    {
        $date = Carbon::parse('2026-10-10 14:30:00');
        $formatted = DateFormatter::format($date);

        $this->assertEquals('10 oct 2026', $formatted);
    }

    public function test_formats_single_digit_day_without_dot(): void
    {
        $date = Carbon::parse('2026-01-05 08:00:00');
        $formatted = DateFormatter::format($date);

        $this->assertEquals('5 ene 2026', $formatted);
    }

    public function test_returns_empty_string_for_null_date(): void
    {
        $formatted = DateFormatter::format(null);

        $this->assertEquals('', $formatted);
    }

    public function test_formats_date_time_with_explicit_timezone(): void
    {
        $dateUtc = Carbon::parse('2026-10-13 21:30:00', 'UTC');
        $formatted = DateFormatter::formatDateTime($dateUtc, 'America/Guayaquil');

        // UTC 21:30 is 16:30 in America/Guayaquil (UTC-5)
        $this->assertEquals('13 oct 2026, 16:30', $formatted);
    }

    public function test_format_date_time_returns_empty_string_for_null(): void
    {
        $formatted = DateFormatter::formatDateTime(null);

        $this->assertEquals('', $formatted);
    }
}
