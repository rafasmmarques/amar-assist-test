<?php

namespace Tests\Feature\Charges;

use App\Models\Charge;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayChargeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_payment_persists_snapshot_and_is_idempotent(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 10:30:00', 'America/Sao_Paulo'));
        Carbon::setTestNow(Carbon::parse('2026-08-13 10:30:00', 'America/Sao_Paulo'));
        $user = User::factory()->create();
        $charge = Charge::factory()->create([
            'original_amount' => '100.00',
            'fixed_fee_amount' => '5.00',
            'due_date' => '2026-08-10',
        ]);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'pagamento-1')
            ->postJson("/api/charges/{$charge->id}/pay", [])
            ->assertOk()
            ->assertJsonPath('message', 'Cobranca paga com sucesso.')
            ->assertJsonPath('data.status', Charge::STATUS_PAID)
            ->assertJsonPath('data.paid_snapshot.paid_original_amount', '100.00')
            ->assertJsonPath('data.paid_snapshot.paid_fixed_fee_amount', '5.00')
            ->assertJsonPath('data.paid_snapshot.paid_late_interest_amount', '3.00')
            ->assertJsonPath('data.paid_snapshot.paid_total_amount', '108.00')
            ->assertJsonPath('data.paid_snapshot.paid_at', '2026-08-13T10:30:00-03:00');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-20 10:30:00', 'America/Sao_Paulo'));
        Carbon::setTestNow(Carbon::parse('2026-08-20 10:30:00', 'America/Sao_Paulo'));

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'pagamento-1')
            ->postJson("/api/charges/{$charge->id}/pay", [])
            ->assertOk()
            ->assertJsonPath('data.paid_snapshot.paid_late_interest_amount', '3.00')
            ->assertJsonPath('data.paid_snapshot.paid_total_amount', '108.00');
    }

    public function test_payment_rejects_amount_payload(): void
    {
        $user = User::factory()->create();
        $charge = Charge::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/charges/{$charge->id}/pay", [
                'original_amount' => '1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('original_amount');
    }

    public function test_payment_rejects_idempotency_key_used_by_another_charge(): void
    {
        $user = User::factory()->create();
        Charge::factory()->paid()->create([
            'billing_period' => '2026-08-01',
            'idempotency_key' => 'pagamento-repetido',
        ]);
        $charge = Charge::factory()->create([
            'billing_period' => '2026-09-01',
        ]);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'pagamento-repetido')
            ->postJson("/api/charges/{$charge->id}/pay", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('Idempotency-Key')
            ->assertJsonPath('errors.Idempotency-Key.0', 'A chave de idempotencia ja foi usada em outra cobranca.');
    }
}
