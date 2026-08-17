<?php

namespace Tests\Unit\Contracts;

use App\Actions\Contracts\CalculateContractDueDate;
use PHPUnit\Framework\TestCase;

class CalculateContractDueDateTest extends TestCase
{
    public function test_uses_configured_day_when_month_has_day(): void
    {
        $dueDate = (new CalculateContractDueDate)->execute(15, '2026-08-01');

        $this->assertSame('2026-08-15', $dueDate->toDateString());
    }

    public function test_uses_last_day_for_months_with_thirty_days(): void
    {
        $dueDate = (new CalculateContractDueDate)->execute(31, '2026-04-01');

        $this->assertSame('2026-04-30', $dueDate->toDateString());
    }

    public function test_uses_last_day_for_february_in_common_year(): void
    {
        $dueDate = (new CalculateContractDueDate)->execute(31, '2025-02-01');

        $this->assertSame('2025-02-28', $dueDate->toDateString());
    }

    public function test_uses_last_day_for_february_in_leap_year(): void
    {
        $dueDate = (new CalculateContractDueDate)->execute(31, '2024-02-01');

        $this->assertSame('2024-02-29', $dueDate->toDateString());
    }
}
