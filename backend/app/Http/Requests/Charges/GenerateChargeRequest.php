<?php

namespace App\Http\Requests\Charges;

use App\Models\Charge;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class GenerateChargeRequest extends FormRequest
{
    private const FORBIDDEN_FIELDS = [
        'due_date',
        'late_interest_amount',
        'total_amount',
        'paid_original_amount',
        'paid_fixed_fee_amount',
        'paid_late_interest_amount',
        'paid_total_amount',
        'paid_at',
        'card_number',
        'card_pan',
        'pan',
        'cvv',
        'card_cvv',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('fixed_fee_amount') && $this->input('fixed_fee_amount') === null) {
            $this->merge(['fixed_fee_amount' => '0.00']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contract_id' => ['required', 'integer', 'exists:contracts,id'],
            'billing_period' => ['required', 'date_format:Y-m'],
            'payment_method' => ['required', Rule::in([Charge::PAYMENT_METHOD_BOLETO, Charge::PAYMENT_METHOD_PIX, Charge::PAYMENT_METHOD_CARD])],
            'original_amount' => ['required', 'string', 'regex:/^\d{1,10}(?:[.,]\d{1,2})?$/', 'not_in:0,0.0,0.00,0,00'],
            'fixed_fee_amount' => ['sometimes', 'string', 'regex:/^\d{1,10}(?:[.,]\d{1,2})?$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (self::FORBIDDEN_FIELDS as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add($field, 'Este campo nao pode ser enviado.');
                }
            }

            try {
                if ($this->has('original_amount') && Money::cents((string) $this->input('original_amount')) <= 0) {
                    $validator->errors()->add('original_amount', 'O valor original deve ser maior que zero.');
                }
            } catch (InvalidArgumentException) {
                return;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contract_id.required' => 'O contrato e obrigatorio.',
            'contract_id.exists' => 'O contrato informado nao existe.',
            'billing_period.date_format' => 'A competencia deve estar no formato YYYY-MM.',
            'payment_method.in' => 'O metodo de pagamento informado e invalido.',
            'original_amount.regex' => 'O valor original deve ser uma string decimal positiva com no maximo 10 digitos antes dos centavos.',
            'original_amount.not_in' => 'O valor original deve ser maior que zero.',
            'fixed_fee_amount.regex' => 'A multa fixa deve ser uma string decimal maior ou igual a zero com no maximo 10 digitos antes dos centavos.',
        ];
    }
}
