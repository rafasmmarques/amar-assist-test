<?php

namespace App\Http\Requests\Charges;

use App\Models\Charge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexChargeRequest extends FormRequest
{
    private const ALLOWED_FILTERS = [
        'page',
        'per_page',
        'status',
        'payment_method',
        'client',
        'contract',
        'due_from',
        'due_to',
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::in([Charge::STATUS_OPEN, Charge::STATUS_PAID])],
            'payment_method' => ['sometimes', Rule::in([Charge::PAYMENT_METHOD_BOLETO, Charge::PAYMENT_METHOD_PIX, Charge::PAYMENT_METHOD_CARD])],
            'client' => ['sometimes', 'integer', 'exists:clients,id'],
            'contract' => ['sometimes', 'integer', 'exists:contracts,id'],
            'due_from' => ['sometimes', 'date'],
            'due_to' => ['sometimes', 'date', 'after_or_equal:due_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), self::ALLOWED_FILTERS) as $filter) {
                $validator->errors()->add($filter, 'Filtro desconhecido.');
            }
        });
    }
}
