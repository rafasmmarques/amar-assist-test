<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Support\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClientRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in([Client::DOCUMENT_TYPE_CPF, Client::DOCUMENT_TYPE_CNPJ])],
            'document' => ['required', 'string', 'max:14', Rule::unique('clients', 'document')],
            'address' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $document = (string) $this->input('document');
            $documentType = (string) $this->input('document_type');

            if ($document !== '' && $documentType !== '' && ! Document::isValid($document, $documentType)) {
                $validator->errors()->add('document', 'O documento informado e invalido para o tipo selecionado.');
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
