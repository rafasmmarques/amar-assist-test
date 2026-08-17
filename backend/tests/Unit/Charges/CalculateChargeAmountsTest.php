<?php

namespace Tests\Unit\Charges;

use App\Actions\Charges\CalculateChargeAmounts;
use App\Models\Charge;
use Tests\TestCase;

class CalculateChargeAmountsTest extends TestCase
{
    public function test_charge_due_on_reference_date_is_not_late(): void
    {
        $charge = new Charge([
            'original_amount' => '100.00',
            'fixed_fee_amount' => '5.00',
            'due_date' => '2026-08-10',
        ]);

        $amounts = app(CalculateChargeAmounts::class)->execute($charge, '2026-08-10');

        $this->assertSame(0, $amounts['days_late']);
        $this->assertSame('0.00', $amounts['late_interest_amount']);
        $this->assertSame('105.00', $amounts['total_amount']);
    }

    public function test_late_interest_uses_original_amount_only(): void
    {
        $charge = new Charge([
            'original_amount' => '100.00',
            'fixed_fee_amount' => '5.00',
            'due_date' => '2026-08-10',
        ]);

        $amounts = app(CalculateChargeAmounts::class)->execute($charge, '2026-08-13');

        $this->assertSame(3, $amounts['days_late']);
        $this->assertSame('3.00', $amounts['late_interest_amount']);
        $this->assertSame('108.00', $amounts['total_amount']);
    }
}
