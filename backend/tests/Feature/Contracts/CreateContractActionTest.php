<?php

namespace Tests\Feature\Contracts;

use App\Actions\Contracts\CreateContract;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateContractActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_revalidates_person_type_against_locked_client_document_type(): void
    {
        $client = Client::factory()->company()->create([
            'document_type' => Client::DOCUMENT_TYPE_CNPJ,
            'document' => '11222333000181',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O tipo de pessoa do contrato nao corresponde ao documento do cliente.');

        app(CreateContract::class)->execute($client, [
            'person_type' => Contract::PERSON_TYPE_PF,
            'billing_cycle_day' => 10,
            'status' => Contract::STATUS_ACTIVE,
            'started_at' => '2026-08-01',
            'ended_at' => null,
        ]);
    }
}
