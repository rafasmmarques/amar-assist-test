<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    public const DOCUMENT_TYPE_CPF = 'cpf';

    public const DOCUMENT_TYPE_CNPJ = 'cnpj';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'document_type',
        'document',
        'address',
        'contact',
        'status',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function expectedContractPersonType(): string
    {
        return $this->document_type === self::DOCUMENT_TYPE_CPF
            ? Contract::PERSON_TYPE_PF
            : Contract::PERSON_TYPE_PJ;
    }
}
