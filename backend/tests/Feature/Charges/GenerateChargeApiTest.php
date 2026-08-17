<?php

namespace Tests\Feature\Charges;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateChargeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_generation_requires_authentication(): void
    {
        $this->postJson('/api/charges/generate', [])
            ->assertUnauthorized();
    }

    public function test_generates_valid_charge_for_boleto(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create(['billing_cycle_day' => 10]);

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_BOLETO,
                'original_amount' => '100',
                'fixed_fee_amount' => '5',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Cobranca gerada com sucesso.')
            ->assertJsonPath('data.status', Charge::STATUS_OPEN)
            ->assertJsonPath('data.billing_period', '2026-08-01')
            ->assertJsonPath('data.original_amount', '100.00')
            ->assertJsonPath('data.fixed_fee_amount', '5.00')
            ->assertJsonPath('data.due_date', '2026-08-10')
            ->assertJsonPath('data.payment_details.method', Charge::PAYMENT_METHOD_BOLETO)
            ->assertJsonMissing(['cvv'])
            ->assertJsonMissing(['card_number']);

        $this->assertDatabaseHas('charges', [
            'contract_id' => $contract->id,
            'billing_period' => '2026-08-01',
            'payment_method' => Charge::PAYMENT_METHOD_BOLETO,
            'original_amount' => '100.00',
            'fixed_fee_amount' => '5.00',
            'status' => Charge::STATUS_OPEN,
        ]);
    }

    public function test_normalizes_money_before_persisting(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_PIX,
                'original_amount' => '00100,5',
                'fixed_fee_amount' => '0005,5',
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_amount', '100.50')
            ->assertJsonPath('data.fixed_fee_amount', '5.50');

        $this->assertDatabaseHas('charges', [
            'contract_id' => $contract->id,
            'original_amount' => '100.50',
            'fixed_fee_amount' => '5.50',
        ]);
    }

    public function test_generates_valid_charge_for_pix(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_PIX,
                'original_amount' => '100.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.fixed_fee_amount', '0.00')
            ->assertJsonPath('data.payment_details.method', Charge::PAYMENT_METHOD_PIX)
            ->assertJsonMissing(['pix_transaction_id']);
    }

    public function test_generates_valid_charge_for_card_without_sensitive_data(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_CARD,
                'original_amount' => '100.00',
                'fixed_fee_amount' => '0.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment_details.method', Charge::PAYMENT_METHOD_CARD)
            ->assertJsonPath('data.payment_details.card_brand', 'visa')
            ->assertJsonPath('data.payment_details.card_last4', '4242')
            ->assertJsonMissing(['card_reference'])
            ->assertJsonMissing(['cvv'])
            ->assertJsonMissing(['card_number']);

        $chargeId = $response->json('data.id');
        $this->assertDatabaseMissing('charge_payment_details', [
            'charge_id' => $chargeId,
            'card_last4' => '4242424242424242',
        ]);
    }

    public function test_rejects_invalid_original_amount(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_PIX,
                'original_amount' => 100.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('original_amount');
    }

    public function test_rejects_amount_above_database_precision(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_PIX,
                'original_amount' => '99999999999.99',
                'fixed_fee_amount' => '99999999999.99',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['original_amount', 'fixed_fee_amount']);
    }

    public function test_rejects_zero_original_amount(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_PIX,
                'original_amount' => '00.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('original_amount')
            ->assertJsonPath('errors.original_amount.0', 'O valor original deve ser maior que zero.');
    }

    public function test_rejects_negative_fixed_fee_amount(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_PIX,
                'original_amount' => '100.00',
                'fixed_fee_amount' => '-1.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fixed_fee_amount');
    }

    public function test_rejects_invalid_payment_method(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => 'dinheiro',
                'original_amount' => '100.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_rejects_missing_contract(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => 999,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_PIX,
                'original_amount' => '100.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contract_id');
    }

    public function test_rejects_ineligible_contract(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->ended()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_PIX,
                'original_amount' => '100.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contract_id')
            ->assertJsonPath('errors.contract_id.0', 'O contrato informado nao esta apto a gerar cobranca.');
    }

    public function test_calculates_due_date_with_last_valid_day(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create(['billing_cycle_day' => 31]);

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2024-02',
                'payment_method' => Charge::PAYMENT_METHOD_PIX,
                'original_amount' => '100.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.due_date', '2024-02-29');
    }

    public function test_idempotent_repetition_returns_existing_charge(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();
        $payload = [
            'contract_id' => $contract->id,
            'billing_period' => '2026-08',
            'payment_method' => Charge::PAYMENT_METHOD_PIX,
            'original_amount' => '100.00',
            'fixed_fee_amount' => '5.00',
        ];

        $firstId = $this->actingAs($user)->postJson('/api/charges/generate', $payload)->assertCreated()->json('data.id');

        $this->actingAs($user)
            ->postJson('/api/charges/generate', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Cobranca ja existente retornada.')
            ->assertJsonPath('data.id', $firstId);

        $this->assertDatabaseCount('charges', 1);
    }

    public function test_existing_charge_with_different_data_returns_conflict(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();
        $payload = [
            'contract_id' => $contract->id,
            'billing_period' => '2026-08',
            'payment_method' => Charge::PAYMENT_METHOD_PIX,
            'original_amount' => '100.00',
            'fixed_fee_amount' => '5.00',
        ];

        $this->actingAs($user)->postJson('/api/charges/generate', $payload)->assertCreated();

        $payload['original_amount'] = '101.00';

        $this->actingAs($user)
            ->postJson('/api/charges/generate', $payload)
            ->assertConflict()
            ->assertJsonPath('message', 'Ja existe cobranca para este contrato e competencia com dados diferentes.');
    }

    public function test_sequential_concurrent_generation_attempts_do_not_duplicate_charge(): void
    {
        $this->test_idempotent_repetition_returns_existing_charge();
    }

    public function test_rejects_due_date_and_sensitive_payment_data(): void
    {
        $user = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/charges/generate', [
                'contract_id' => $contract->id,
                'billing_period' => '2026-08',
                'payment_method' => Charge::PAYMENT_METHOD_CARD,
                'original_amount' => '100.00',
                'due_date' => '2026-08-10',
                'card_number' => '4242424242424242',
                'cvv' => '123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date', 'card_number', 'cvv']);
    }
}
