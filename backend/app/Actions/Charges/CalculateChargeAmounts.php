<?php

namespace App\Actions\Charges;

use App\Models\Charge;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class CalculateChargeAmounts
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Charge $charge, DateTimeInterface|string|null $referenceDate = null): array
    {
        $reference = $referenceDate
            ? CarbonImmutable::parse($referenceDate, 'America/Sao_Paulo')->startOfDay()
            : CarbonImmutable::now('America/Sao_Paulo')->startOfDay();
        $dueDate = CarbonImmutable::parse($charge->due_date, 'America/Sao_Paulo')->startOfDay();
        $daysLate = $reference->greaterThan($dueDate) ? $dueDate->diffInDays($reference) : 0;
        $lateInterest = intdiv((Money::cents($charge->original_amount) * $daysLate) + 50, 100);
        $total = Money::cents($charge->original_amount) + Money::cents($charge->fixed_fee_amount) + $lateInterest;

        return [
            'original_amount' => Money::normalize($charge->original_amount),
            'fixed_fee_amount' => Money::normalize($charge->fixed_fee_amount),
            'late_interest_amount' => Money::fromCents($lateInterest),
            'total_amount' => Money::fromCents($total),
            'days_late' => $daysLate,
            'reference_date' => $reference->toDateString(),
        ];
    }
}
