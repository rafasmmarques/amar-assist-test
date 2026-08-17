<?php

namespace Tests\Feature\Charges;

use App\Actions\Charges\GenerateCharge;
use App\Jobs\ProcessChargeBatch;
use App\Models\Charge;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProcessChargeBatchJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_uses_redis_connection_named_queue_limited_attempts_and_backoff(): void
    {
        $job = new ProcessChargeBatch('batch-1', '2026-08', []);

        $this->assertSame('redis', $job->connection);
        $this->assertSame('charges', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 60, 120], $job->backoff());
    }

    public function test_job_processes_batch_using_individual_generation_rule(): void
    {
        $contracts = Contract::factory()->count(3)->create(['billing_cycle_day' => 10]);

        $this->runJob(new ProcessChargeBatch('batch-1', '2026-08', [
            $this->item($contracts[0], Charge::PAYMENT_METHOD_BOLETO),
            $this->item($contracts[1], Charge::PAYMENT_METHOD_PIX),
            $this->item($contracts[2], Charge::PAYMENT_METHOD_CARD),
        ]));

        $this->assertDatabaseCount('charges', 3);
        $this->assertDatabaseHas('charges', [
            'contract_id' => $contracts[0]->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-10',
            'status' => Charge::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('charge_payment_details', [
            'card_brand' => 'visa',
            'card_last4' => '4242',
        ]);
        $this->assertDatabaseMissing('charge_payment_details', [
            'card_last4' => '4242424242424242',
        ]);
    }

    public function test_job_repetition_is_idempotent(): void
    {
        $contract = Contract::factory()->create();
        $job = new ProcessChargeBatch('batch-2', '2026-08', [
            $this->item($contract, Charge::PAYMENT_METHOD_PIX),
        ]);

        $this->runJob($job);
        $this->runJob($job);

        $this->assertDatabaseCount('charges', 1);
        $this->assertDatabaseHas('charges', [
            'contract_id' => $contract->id,
            'billing_period' => '2026-08-01',
        ]);
    }

    public function test_job_continues_after_conflict_and_logs_safe_summary(): void
    {
        Log::spy();

        $contracts = Contract::factory()->count(2)->create();
        app(GenerateCharge::class)->execute([
            'contract_id' => $contracts[0]->id,
            'billing_period' => '2026-08',
            'payment_method' => Charge::PAYMENT_METHOD_PIX,
            'original_amount' => '100.00',
            'fixed_fee_amount' => '0.00',
        ]);

        $this->runJob(new ProcessChargeBatch('batch-3', '2026-08', [
            $this->item($contracts[0], Charge::PAYMENT_METHOD_PIX, '101.00'),
            $this->item($contracts[1], Charge::PAYMENT_METHOD_BOLETO),
        ]));

        $this->assertDatabaseCount('charges', 2);
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Conflito em item de lote de cobrancas.'
                && $context['batch_id'] === 'batch-3'
                && $context['contract_id'] === $contracts[0]->id
                && ! array_key_exists('original_amount', $context)
                && ! array_key_exists('cvv', $context)
                && ! array_key_exists('card_number', $context)
        );
        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Lote de cobrancas processado.'
                && $context['batch_id'] === 'batch-3'
                && $context['created'] === 1
                && $context['conflicts'] === 1
                && ! array_key_exists('items', $context)
        );
    }

    public function test_job_retry_after_partial_failure_reuses_existing_charges_and_processes_remaining_items(): void
    {
        $contracts = Contract::factory()->count(2)->create();
        app(GenerateCharge::class)->execute([
            'contract_id' => $contracts[0]->id,
            'billing_period' => '2026-08',
            'payment_method' => Charge::PAYMENT_METHOD_PIX,
            'original_amount' => '100.00',
            'fixed_fee_amount' => '0.00',
        ]);

        $this->runJob(new ProcessChargeBatch('batch-4', '2026-08', [
            $this->item($contracts[0], Charge::PAYMENT_METHOD_PIX),
            $this->item($contracts[1], Charge::PAYMENT_METHOD_CARD),
        ]));

        $this->assertDatabaseCount('charges', 2);
        $this->assertDatabaseHas('charges', [
            'contract_id' => $contracts[1]->id,
            'billing_period' => '2026-08-01',
            'payment_method' => Charge::PAYMENT_METHOD_CARD,
        ]);
    }

    public function test_job_invalidates_charge_summary_cache_after_processing(): void
    {
        Cache::put('charges:summary:version', 10, 60);

        $contract = Contract::factory()->create();

        $this->runJob(new ProcessChargeBatch('batch-5', '2026-08', [
            $this->item($contract, Charge::PAYMENT_METHOD_PIX),
        ]));

        $this->assertEquals(11, Cache::get('charges:summary:version'));
    }

    /**
     * @return array<string, string|int>
     */
    private function item(Contract $contract, string $paymentMethod, string $originalAmount = '100.00'): array
    {
        return [
            'contract_id' => $contract->id,
            'payment_method' => $paymentMethod,
            'original_amount' => $originalAmount,
            'fixed_fee_amount' => '0.00',
        ];
    }

    private function runJob(ProcessChargeBatch $job): void
    {
        $job->handle(app(GenerateCharge::class));
    }
}
