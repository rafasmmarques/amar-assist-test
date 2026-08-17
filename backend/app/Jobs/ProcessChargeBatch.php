<?php

namespace App\Jobs;

use App\Actions\Charges\GenerateCharge;
use App\Exceptions\ChargeGenerationConflict;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessChargeBatch implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $items;

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public string $batchId,
        public string $billingPeriod,
        array $items
    ) {
        $this->onConnection('redis');
        $this->onQueue('charges');

        $this->items = $items;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(GenerateCharge $generateCharge): void
    {
        $created = 0;
        $reused = 0;
        $conflicts = 0;

        foreach ($this->items as $item) {
            try {
                $result = $generateCharge->execute([
                    'contract_id' => $item['contract_id'],
                    'billing_period' => $this->billingPeriod,
                    'payment_method' => $item['payment_method'],
                    'original_amount' => $item['original_amount'],
                    'fixed_fee_amount' => $item['fixed_fee_amount'] ?? '0.00',
                ]);

                $result['created'] ? $created++ : $reused++;
            } catch (ChargeGenerationConflict) {
                $conflicts++;

                Log::warning('Conflito em item de lote de cobrancas.', [
                    'batch_id' => $this->batchId,
                    'contract_id' => $item['contract_id'] ?? null,
                ]);
            }
        }

        Log::info('Lote de cobrancas processado.', [
            'batch_id' => $this->batchId,
            'queued_items' => count($this->items),
            'created' => $created,
            'reused' => $reused,
            'conflicts' => $conflicts,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Falha no lote de cobrancas.', [
            'batch_id' => $this->batchId,
            'exception' => $exception::class,
        ]);
    }
}
