<?php

namespace App\Http\Resources;

use App\Actions\Charges\CalculateChargeAmounts;
use App\Models\Charge;
use App\Support\Money;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'contract_id' => $this->contract_id,
            'billing_period' => $this->billing_period?->toDateString(),
            'payment_method' => $this->payment_method,
            'original_amount' => Money::normalize($this->original_amount),
            'fixed_fee_amount' => Money::normalize($this->fixed_fee_amount),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];

        if ($this->status === Charge::STATUS_PAID) {
            $data['paid_snapshot'] = [
                'paid_original_amount' => Money::normalize($this->paid_original_amount),
                'paid_fixed_fee_amount' => Money::normalize($this->paid_fixed_fee_amount),
                'paid_late_interest_amount' => Money::normalize($this->paid_late_interest_amount),
                'paid_total_amount' => Money::normalize($this->paid_total_amount),
                'paid_at' => $this->paid_at?->timezone('America/Sao_Paulo')->toAtomString(),
            ];
        } else {
            $data['amounts'] = app(CalculateChargeAmounts::class)->execute($this->resource);
        }

        if ($this->relationLoaded('paymentDetail') && $this->paymentDetail) {
            $data['payment_details'] = $this->safePaymentDetails();
        }

        return $data;
    }

    /**
     * @return array<string, string|null>
     */
    private function safePaymentDetails(): array
    {
        return match ($this->payment_method) {
            Charge::PAYMENT_METHOD_BOLETO => [
                'method' => Charge::PAYMENT_METHOD_BOLETO,
                'boleto_barcode' => $this->paymentDetail->boleto_barcode,
            ],
            Charge::PAYMENT_METHOD_PIX => [
                'method' => Charge::PAYMENT_METHOD_PIX,
                'pix_key_masked' => $this->maskPixKey((string) $this->paymentDetail->pix_key),
            ],
            Charge::PAYMENT_METHOD_CARD => [
                'method' => Charge::PAYMENT_METHOD_CARD,
                'card_brand' => $this->paymentDetail->card_brand,
                'card_last4' => $this->paymentDetail->card_last4,
                'card_masked' => '**** **** **** '.$this->paymentDetail->card_last4,
            ],
        };
    }

    private function maskPixKey(string $pixKey): string
    {
        if ($pixKey === '') {
            return '';
        }

        return substr($pixKey, 0, 4).'***'.substr($pixKey, -4);
    }
}
