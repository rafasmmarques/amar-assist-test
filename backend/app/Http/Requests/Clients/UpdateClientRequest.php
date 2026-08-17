<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Support\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('document')) {
            $this->merge([
                'document' => Document::normalize($this->input('document')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'document_type' => ['sometimes', 'required', Rule::in([Client::DOCUMENT_TYPE_CPF, Client::DOCUMENT_TYPE_CNPJ])],
            'document' => ['sometimes', 'required', 'string', 'max:14', Rule::unique('clients', 'document')->ignore($client)],
            'address' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Client $client */
            $client = $this->route('client');
            $document = (string) $this->input('document', $client->document);
            $documentType = (string) $this->input('document_type', $client->document_type);

            if (! Document::isValid($document, $documentType)) {
                $validator->errors()->add('document', 'O documento informado e invalido para o tipo selecionado.');
            }

            if (
                $this->has('document_type')
                && $documentType !== $client->document_type
                && $client->contracts()->exists()
            ) {
                $validator->errors()->add('document_type', 'Cliente com contrato associado nao pode alterar o tipo de documento.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome e obrigatorio.',
            'name.max' => 'O nome deve ter no maximo 255 caracteres.',
            'document_type.required' => 'O tipo de documento e obrigatorio.',
            'document_type.in' => 'O tipo de documento informado e invalido.',
            'document.required' => 'O documento e obrigatorio.',
            'document.max' => 'O documento deve ter no maximo 14 digitos.',
            'document.unique' => 'O documento informado ja esta cadastrado.',
            'address.max' => 'O endereco deve ter no maximo 255 caracteres.',
            'contact.max' => 'O contato deve ter no maximo 255 caracteres.',
        ];
    }
}
