<?php

namespace App\Http\Requests\Contracts;

use App\Models\Client;
use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContractRequest extends FormRequest
{
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
            'person_type' => ['required', Rule::in([Contract::PERSON_TYPE_PF, Contract::PERSON_TYPE_PJ])],
            'billing_cycle_day' => ['required', 'integer', 'min:1', 'max:31'],
            'status' => ['sometimes', Rule::in([Contract::STATUS_ACTIVE, Contract::STATUS_ENDED])],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at', 'required_if:status,'.Contract::STATUS_ENDED],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Client $client */
            $client = $this->route('client');
            $personType = (string) $this->input('person_type');

            if ($personType !== '' && $personType !== $client->expectedContractPersonType()) {
                $validator->errors()->add('person_type', 'O tipo de pessoa do contrato nao corresponde ao documento do cliente.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_type.required' => 'O tipo de pessoa e obrigatorio.',
            'person_type.in' => 'O tipo de pessoa informado e invalido.',
            'billing_cycle_day.required' => 'O dia do ciclo de cobranca e obrigatorio.',
            'billing_cycle_day.integer' => 'O dia do ciclo de cobranca deve ser um numero inteiro.',
            'billing_cycle_day.min' => 'O dia do ciclo de cobranca deve ser maior ou igual a 1.',
            'billing_cycle_day.max' => 'O dia do ciclo de cobranca deve ser menor ou igual a 31.',
            'status.in' => 'O status informado e invalido.',
            'started_at.required' => 'A data de inicio e obrigatoria.',
            'started_at.date' => 'A data de inicio deve ser uma data valida.',
            'ended_at.date' => 'A data de encerramento deve ser uma data valida.',
            'ended_at.after_or_equal' => 'A data de encerramento deve ser igual ou posterior a data de inicio.',
            'ended_at.required_if' => 'A data de encerramento e obrigatoria para contrato encerrado.',
        ];
    }
}
