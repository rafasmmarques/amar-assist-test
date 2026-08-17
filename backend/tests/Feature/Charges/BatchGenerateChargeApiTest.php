<?php

namespace Tests\Feature\Charges;

use App\Jobs\ProcessChargeBatch;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BatchGenerateChargeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_generation_requires_authentication(): void
    {
        $this->postJson('/api/charges/batch-generate', [])
            ->assertUnauthorized();
    }

    public function test_dispatches_valid_batch_without_creating_charges_synchronously(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $contracts = Contract::factory()->count(3)->create();

        $this->actingAs($user)
            ->postJson('/api/charges/batch-generate', [
                'billing_period' => '2026-08',
                'items' => [
                    [
                        'contract_id' => $contracts[0]->id,
                        'payment_method' => Charge::PAYMENT_METHOD_BOLETO,
                        'original_amount' => '100.00',
                        'fixed_fee_amount' => '5.00',
                    ],
                    [
                        'contract_id' => $contracts[1]->id,
                        'payment_method' => Charge::PAYMENT_METHOD_PIX,
                        'original_amount' => '150.00',
                    ],
                    [
                        'contract_id' => $contracts[2]->id,
                        'payment_method' => Charge::PAYMENT_METHOD_CARD,
                        'original_amount' => '200.00',
                        'fixed_fee_amount' => '0.00',
                    ],
                ],
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Geracao de cobrancas enviada para processamento.')
            ->assertJsonPath('queued_items', 3)
            ->assertJsonStructure(['batch_id']);

        Queue::assertPushed(ProcessChargeBatch::class, function (ProcessChargeBatch $job): bool {
            return $job->connection === 'redis'
                && $job->queue === 'charges'
                && $job->tries === 3
                && $job->billingPeriod === '2026-08'
                && count($job->items) === 3;
        });
        $this->assertDatabaseCount('charges', 0);
    }

    public function test_rejects_empty_batch_without_dispatching_job(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/charges/batch-generate', [
                'billing_period' => '2026-08',
                'items' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        Queue::assertNothingPushed();
    }

    public function test_rejects_more_than_one_hundred_items_without_dispatching_job(): void
    {
        Queue::fake();

        $items = Contract::factory()->count(101)->create()->map(fn (Contract $contract): array => [
            'contract_id' => $contract->id,
            'payment_method' => Charge::PAYMENT_METHOD_PIX,
            'original_amount' => '100.00',
        ])->all();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/charges/batch-generate', [
                'billing_period' => '2026-08',
                'items' => $items,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        Queue::assertNothingPushed();
    }

    public function test_rejects_duplicate_contract_in_same_batch_without_dispatching_job(): void
    {
        Queue::fake();

        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/charges/batch-generate', [
                'billing_period' => '2026-08',
                'items' => [
                    [
                        'contract_id' => $contract->id,
                        'payment_method' => Charge::PAYMENT_METHOD_PIX,
                        'original_amount' => '100.00',
                    ],
                    [
                        'contract_id' => $contract->id,
                        'payment_method' => Charge::PAYMENT_METHOD_BOLETO,
                        'original_amount' => '150.00',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.contract_id');

        Queue::assertNothingPushed();
    }

    public function test_rejects_invalid_payment_method_without_dispatching_job(): void
    {
        Queue::fake();

        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/charges/batch-generate', [
                'billing_period' => '2026-08',
                'items' => [
                    [
                        'contract_id' => $contract->id,
                        'payment_method' => 'dinheiro',
                        'original_amount' => '100.00',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.payment_method');

        Queue::assertNothingPushed();
    }

    public function test_rejects_invalid_financial_values_without_dispatching_job(): void
    {
        Queue::fake();

        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/charges/batch-generate', [
                'billing_period' => '2026-08',
                'items' => [
                    [
                        'contract_id' => $contract->id,
                        'payment_method' => Charge::PAYMENT_METHOD_CARD,
                        'original_amount' => '0.00',
                        'fixed_fee_amount' => '-1.00',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.original_amount',
                'items.0.fixed_fee_amount',
            ]);

        Queue::assertNothingPushed();
    }

    public function test_rejects_missing_contract_without_dispatching_job(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/charges/batch-generate', [
                'billing_period' => '2026-08',
                'items' => [
                    [
                        'contract_id' => 999,
                        'payment_method' => Charge::PAYMENT_METHOD_PIX,
                        'original_amount' => '100.00',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.contract_id');

        Queue::assertNothingPushed();
    }

    public function test_rejects_ineligible_contract_without_dispatching_job(): void
    {
        Queue::fake();

        $contract = Contract::factory()->ended()->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/charges/batch-generate', [
                'billing_period' => '2026-08',
                'items' => [
                    [
                        'contract_id' => $contract->id,
                        'payment_method' => Charge::PAYMENT_METHOD_PIX,
                        'original_amount' => '100.00',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.contract_id');

        Queue::assertNothingPushed();
    }

    public function test_rejects_sensitive_or_server_calculated_fields_without_dispatching_job(): void
    {
        Queue::fake();

        $contract = Contract::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/charges/batch-generate', [
                'billing_period' => '2026-08',
                'due_date' => '2026-08-10',
                'items' => [
                    [
                        'contract_id' => $contract->id,
                        'payment_method' => Charge::PAYMENT_METHOD_CARD,
                        'original_amount' => '100.00',
                        'status' => Charge::STATUS_PAID,
                        'card_number' => '4242424242424242',
                        'cvv' => '123',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'due_date',
                'items.0.status',
                'items.0.card_number',
                'items.0.cvv',
            ]);

        $this->assertStringNotContainsString('4242424242424242', $response->getContent());
        $this->assertStringNotContainsString('123', $response->getContent());
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('charges', 0);
    }
}
