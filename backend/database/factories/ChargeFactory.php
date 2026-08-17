<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    public function definition()
    {
        $billingPeriod = now()->startOfMonth();

        return [
            'uuid' => (string) Str::uuid(),
            'contract_id' => Contract::factory(),
            'billing_period' => $billingPeriod->toDateString(),
            'payment_method' => fake()->randomElement([
                Charge::PAYMENT_METHOD_BOLETO,
                Charge::PAYMENT_METHOD_CARD,
                Charge::PAYMENT_METHOD_PIX,
            ]),
            'original_amount' => '100.00',
            'fixed_fee_amount' => '5.00',
            'due_date' => $billingPeriod->copy()->addDays(9)->toDateString(),
            'status' => Charge::STATUS_OPEN,
            'paid_original_amount' => null,
            'paid_fixed_fee_amount' => null,
            'paid_late_interest_amount' => null,
            'paid_total_amount' => null,
            'paid_at' => null,
            'idempotency_key' => null,
        ];
    }

    public function paid()
    {
        return $this->state(fn (array $attributes) => [
            'status' => Charge::STATUS_PAID,
            'paid_original_amount' => '100.00',
            'paid_fixed_fee_amount' => '5.00',
            'paid_late_interest_amount' => '3.00',
            'paid_total_amount' => '108.00',
            'paid_at' => now(),
        ]);
    }
}
