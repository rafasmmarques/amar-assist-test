<?php

namespace App\Actions\Contracts;

use App\Models\Client;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateContract
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(Client $client, array $data): Contract
    {
        return DB::transaction(function () use ($client, $data): Contract {
            $lockedClient = Client::query()->whereKey($client->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedClient->status !== Client::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'client' => ['Cliente inativo nao pode receber contrato.'],
                ]);
            }

            if (($data['person_type'] ?? null) !== $lockedClient->expectedContractPersonType()) {
                throw ValidationException::withMessages([
                    'person_type' => ['O tipo de pessoa do contrato nao corresponde ao documento do cliente.'],
                ]);
            }

            return $lockedClient->contracts()->create($data);
        });
    }
}
