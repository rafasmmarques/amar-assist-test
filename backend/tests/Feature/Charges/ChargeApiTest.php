<?php

namespace Tests\Feature\Charges;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_list_orders_open_late_before_open_not_late_and_paid(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-16', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $contract = Contract::factory()->create();
        $openFuture = Charge::factory()->for($contract)->create([
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-10',
            'status' => Charge::STATUS_OPEN,
        ]);
        $paidLate = Charge::factory()->for($contract)->paid()->create([
            'billing_period' => '2026-07-01',
            'due_date' => '2026-07-10',
        ]);
        $openLate = Charge::factory()->for($contract)->create([
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-10',
            'status' => Charge::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->getJson('/api/charges')
            ->assertOk()
            ->assertJsonPath('data.0.id', $openLate->id)
            ->assertJsonPath('data.1.id', $openFuture->id)
            ->assertJsonPath('data.2.id', $paidLate->id)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_charge_detail_returns_discriminated_amounts(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $charge = Charge::factory()->create([
            'original_amount' => '100.00',
            'fixed_fee_amount' => '5.00',
            'due_date' => '2026-08-10',
            'status' => Charge::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->getJson("/api/charges/{$charge->id}")
            ->assertOk()
            ->assertJsonPath('data.amounts.late_interest_amount', '3.00')
            ->assertJsonPath('data.amounts.total_amount', '108.00')
            ->assertJsonPath('data.amounts.days_late', 3)
            ->assertJsonPath('data.amounts.reference_date', '2026-08-13');
    }
}
