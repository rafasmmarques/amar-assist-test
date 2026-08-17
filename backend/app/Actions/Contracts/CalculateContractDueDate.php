<?php

namespace App\Actions\Contracts;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class CalculateContractDueDate
{
    public function execute(int $billingCycleDay, DateTimeInterface|string $billingPeriod): CarbonImmutable
    {
        $period = CarbonImmutable::parse($billingPeriod, 'America/Sao_Paulo')->startOfMonth();
        $day = min($billingCycleDay, $period->daysInMonth);

        return $period->setDay($day);
    }
}
