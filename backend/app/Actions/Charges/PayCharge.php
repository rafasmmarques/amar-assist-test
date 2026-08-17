<?php

namespace App\Actions\Charges;

use App\Models\Charge;
use App\Support\Charges\ChargeSummaryCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayCharge
{
    public function execute(Charge $charge, ?string $idempotencyKey = null): Charge
    {
        return DB::transaction(function () use ($charge, $idempotencyKey): Charge {
            $lockedCharge = Charge::query()->whereKey($charge->getKey())->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $chargeWithKey = Charge::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($chargeWithKey && ! $chargeWithKey->is($lockedCharge)) {
                    throw ValidationException::withMessages([
                        'Idempotency-Key' => ['A chave de idempotencia ja foi usada em outra cobranca.'],
                    ]);
                }
            }

            if ($lockedCharge->status === Charge::STATUS_PAID) {
                return $lockedCharge->refresh();
            }

            $amounts = app(CalculateChargeAmounts::class)->execute($lockedCharge);

            $lockedCharge->update([
                'status' => Charge::STATUS_PAID,
                'paid_original_amount' => $amounts['original_amount'],
                'paid_fixed_fee_amount' => $amounts['fixed_fee_amount'],
                'paid_late_interest_amount' => $amounts['late_interest_amount'],
                'paid_total_amount' => $amounts['total_amount'],
                'paid_at' => now('America/Sao_Paulo'),
                'idempotency_key' => $idempotencyKey,
            ]);

            app(ChargeSummaryCache::class)->invalidate();

            return $lockedCharge->refresh();
        });
    }
}
