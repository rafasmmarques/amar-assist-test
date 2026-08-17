<?php

namespace App\Http\Requests\Charges;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PayChargeRequest extends FormRequest
{
    private const FORBIDDEN_FIELDS = [
        'original_amount',
        'fixed_fee_amount',
        'late_interest_amount',
        'total_amount',
        'paid_original_amount',
        'paid_fixed_fee_amount',
        'paid_late_interest_amount',
        'paid_total_amount',
        'paid_at',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (self::FORBIDDEN_FIELDS as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add($field, 'Este campo nao pode ser enviado no pagamento.');
                }
            }

            if ($this->header('Idempotency-Key') !== null && strlen((string) $this->header('Idempotency-Key')) > 120) {
                $validator->errors()->add('Idempotency-Key', 'A chave de idempotencia deve ter no maximo 120 caracteres.');
            }
        });
    }
}
