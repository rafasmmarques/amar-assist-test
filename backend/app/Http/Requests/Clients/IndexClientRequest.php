<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Support\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexClientRequest extends FormRequest
{
    private const ALLOWED_FILTERS = [
        'page',
        'per_page',
        'name',
        'status',
        'document',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->has('document')) {
            $this->merge([
                'document' => Document::normalize($this->query('document')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in([Client::STATUS_ACTIVE, Client::STATUS_INACTIVE])],
            'document' => ['sometimes', 'string', 'max:14'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unknown = array_diff(array_keys($this->query()), self::ALLOWED_FILTERS);

            foreach ($unknown as $filter) {
                $validator->errors()->add($filter, 'Filtro desconhecido.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page.integer' => 'A pagina deve ser um numero inteiro.',
            'page.min' => 'A pagina deve ser maior ou igual a 1.',
            'per_page.integer' => 'O limite por pagina deve ser um numero inteiro.',
            'per_page.min' => 'O limite por pagina deve ser maior ou igual a 1.',
            'per_page.max' => 'O limite por pagina deve ser menor ou igual a 100.',
            'name.max' => 'O nome deve ter no maximo 255 caracteres.',
            'status.in' => 'O status informado e invalido.',
            'document.max' => 'O documento deve ter no maximo 14 digitos.',
        ];
    }
}
