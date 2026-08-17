<?php

namespace App\Actions\Charges;

use App\Actions\Contracts\CalculateContractDueDate;
use App\Exceptions\ChargeGenerationConflict;
use App\Models\Charge;
use App\Models\Contract;
use App\Support\Charges\ChargeSummaryCache;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerateCharge
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{charge: Charge, created: bool}
     *
     * @throws ChargeGenerationConflict
     * @throws ValidationException
     */
    public function execute(array $data): array
    {
        $billingPeriod = CarbonImmutable::createFromFormat('Y-m-d', $data['billing_period'].'-01', 'America/Sao_Paulo')->startOfMonth();
        $originalAmount = Money::normalize($data['original_amount']);
        $fixedFeeAmount = Money::normalize($data['fixed_fee_amount'] ?? '0.00');

        return DB::transaction(function () use ($data, $billingPeriod, $originalAmount, $fixedFeeAmount): array {
            $contract = Contract::query()
                ->whereKey($data['contract_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($contract->status !== Contract::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'contract_id' => ['O contrato informado nao esta apto a gerar cobranca.'],
                ]);
            }

            $existing = Charge::query()
                ->where('contract_id', $contract->id)
                ->whereDate('billing_period', $billingPeriod->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->ensureSamePayload($existing, $data['payment_method'], $originalAmount, $fixedFeeAmount);

                return ['charge' => $existing->load('paymentDetail'), 'created' => false];
            }

            try {
                $charge = Charge::create([
                    'uuid' => (string) Str::uuid(),
                    'contract_id' => $contract->id,
                    'billing_period' => $billingPeriod->toDateString(),
                    'payment_method' => $data['payment_method'],
                    'original_amount' => $originalAmount,
                    'fixed_fee_amount' => $fixedFeeAmount,
                    'due_date' => app(CalculateContractDueDate::class)->execute($contract->billing_cycle_day, $billingPeriod)->toDateString(),
                    'status' => Charge::STATUS_OPEN,
                ]);
            } catch (QueryException $exception) {
                $existing = Charge::query()
                    ->where('contract_id', $contract->id)
                    ->whereDate('billing_period', $billingPeriod->toDateString())
                    ->first();

                if (! $existing) {
                    throw $exception;
                }

                $this->ensureSamePayload($existing, $data['payment_method'], $originalAmount, $fixedFeeAmount);

                return ['charge' => $existing->load('paymentDetail'), 'created' => false];
            }

            $charge->paymentDetail()->create($this->paymentDetailsFor($charge));
            app(ChargeSummaryCache::class)->invalidate();

            return ['charge' => $charge->load('paymentDetail'), 'created' => true];
        });
    }

    private function ensureSamePayload(Charge $charge, string $paymentMethod, string $originalAmount, string $fixedFeeAmount): void
    {
        if (
            $charge->payment_method !== $paymentMethod
            || Money::normalize($charge->original_amount) !== $originalAmount
            || Money::normalize($charge->fixed_fee_amount) !== $fixedFeeAmount
        ) {
            throw new ChargeGenerationConflict('Ja existe cobranca para este contrato e competencia com dados diferentes.');
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function paymentDetailsFor(Charge $charge): array
    {
        return match ($charge->payment_method) {
            Charge::PAYMENT_METHOD_BOLETO => [
                'boleto_barcode' => '34191'.str_pad((string) $charge->id, 39, '0', STR_PAD_LEFT),
                'pix_key' => null,
                'pix_transaction_id' => null,
                'card_reference' => null,
                'card_brand' => null,
                'card_last4' => null,
            ],
            Charge::PAYMENT_METHOD_PIX => [
                'boleto_barcode' => null,
                'pix_key' => 'pix-'.$charge->uuid.'@simulado.local',
                'pix_transaction_id' => (string) Str::uuid(),
                'card_reference' => null,
                'card_brand' => null,
                'card_last4' => null,
            ],
            Charge::PAYMENT_METHOD_CARD => [
                'boleto_barcode' => null,
                'pix_key' => null,
                'pix_transaction_id' => null,
                'card_reference' => 'card_ref_'.Str::uuid(),
                'card_brand' => 'visa',
                'card_last4' => '4242',
            ],
        };
    }
}
