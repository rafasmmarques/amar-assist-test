<?php

namespace App\Http\Requests\Charges;

use App\Models\Charge;
use App\Models\Contract;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class BatchGenerateChargeRequest extends FormRequest
{
    private const FORBIDDEN_ROOT_FIELDS = [
        'due_date',
        'late_interest_amount',
        'total_amount',
        'status',
        'card_number',
        'card_pan',
        'pan',
        'cvv',
        'card_cvv',
    ];

    private const FORBIDDEN_ITEM_FIELDS = [
        'due_date',
        'late_interest_amount',
        'total_amount',
        'status',
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'billing_period' => ['required', 'date_format:Y-m'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.contract_id' => ['required', 'integer', 'distinct', 'exists:contracts,id'],
            'items.*.payment_method' => ['required', Rule::in([Charge::PAYMENT_METHOD_BOLETO, Charge::PAYMENT_METHOD_PIX, Charge::PAYMENT_METHOD_CARD])],
            'items.*.original_amount' => ['required', 'string', 'regex:/^\d{1,10}(?:[.,]\d{1,2})?$/'],
            'items.*.fixed_fee_amount' => ['sometimes', 'string', 'regex:/^\d{1,10}(?:[.,]\d{1,2})?$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (self::FORBIDDEN_ROOT_FIELDS as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add($field, 'Este campo nao pode ser enviado.');
                }
            }

            foreach ((array) $this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach (self::FORBIDDEN_ITEM_FIELDS as $field) {
                    if (array_key_exists($field, $item)) {
                        $validator->errors()->add("items.{$index}.{$field}", 'Este campo nao pode ser enviado.');
                    }
                }

                $this->validatePositiveAmount($validator, $index, $item);
            }

            $contractIds = collect((array) $this->input('items', []))
                ->pluck('contract_id')
                ->filter()
                ->all();

            if ($contractIds === []) {
                return;
            }

            $inactiveContracts = Contract::query()
                ->whereIn('id', $contractIds)
                ->where('status', '!=', Contract::STATUS_ACTIVE)
                ->pluck('id')
                ->all();

            foreach ($inactiveContracts as $contractId) {
                $index = array_search($contractId, $contractIds, true);
                $validator->errors()->add("items.{$index}.contract_id", 'O contrato informado nao esta apto a gerar cobranca.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'billing_period.required' => 'A competencia e obrigatoria.',
            'billing_period.date_format' => 'A competencia deve estar no formato YYYY-MM.',
            'items.required' => 'O lote de cobrancas e obrigatorio.',
            'items.min' => 'O lote deve ter pelo menos 1 item.',
            'items.max' => 'O lote deve ter no maximo 100 itens.',
            'items.*.contract_id.required' => 'O contrato e obrigatorio.',
            'items.*.contract_id.distinct' => 'O contrato nao pode se repetir no mesmo lote.',
            'items.*.contract_id.exists' => 'O contrato informado nao existe.',
            'items.*.payment_method.in' => 'O metodo de pagamento informado e invalido.',
            'items.*.original_amount.regex' => 'O valor original deve ser uma string decimal positiva com no maximo 10 digitos antes dos centavos.',
            'items.*.fixed_fee_amount.regex' => 'A multa fixa deve ser uma string decimal maior ou igual a zero com no maximo 10 digitos antes dos centavos.',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validatePositiveAmount(Validator $validator, int $index, array $item): void
    {
        try {
            if (isset($item['original_amount']) && Money::cents((string) $item['original_amount']) <= 0) {
                $validator->errors()->add("items.{$index}.original_amount", 'O valor original deve ser maior que zero.');
            }
        } catch (InvalidArgumentException) {
            return;
        }
    }
}
