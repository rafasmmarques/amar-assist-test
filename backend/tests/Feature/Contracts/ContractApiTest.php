<?php

namespace Tests\Feature\Contracts;

use App\Models\Client;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_routes_require_authentication(): void
    {
        $client = Client::factory()->create();

        $this->getJson("/api/clients/{$client->id}/contracts")
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_pf_contract_for_cpf_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'document_type' => Client::DOCUMENT_TYPE_CPF,
            'document' => '52998224725',
        ]);

        $this->actingAs($user)
            ->postJson("/api/clients/{$client->id}/contracts", [
                'person_type' => Contract::PERSON_TYPE_PF,
                'billing_cycle_day' => 31,
                'started_at' => '2026-08-01',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Contrato criado com sucesso.')
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.person_type', Contract::PERSON_TYPE_PF)
            ->assertJsonPath('data.billing_cycle_day', 31)
            ->assertJsonPath('data.status', Contract::STATUS_ACTIVE);
    }

    public function test_authenticated_user_can_create_pj_contract_for_cnpj_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->company()->create([
            'document_type' => Client::DOCUMENT_TYPE_CNPJ,
            'document' => '11222333000181',
        ]);

        $this->actingAs($user)
            ->postJson("/api/clients/{$client->id}/contracts", [
                'person_type' => Contract::PERSON_TYPE_PJ,
                'billing_cycle_day' => 10,
                'started_at' => '2026-08-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.person_type', Contract::PERSON_TYPE_PJ);
    }

    public function test_contract_person_type_must_match_client_document_type(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'document_type' => Client::DOCUMENT_TYPE_CPF,
            'document' => '52998224725',
        ]);

        $this->actingAs($user)
            ->postJson("/api/clients/{$client->id}/contracts", [
                'person_type' => Contract::PERSON_TYPE_PJ,
                'billing_cycle_day' => 10,
                'started_at' => '2026-08-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('person_type')
            ->assertJsonPath('errors.person_type.0', 'O tipo de pessoa do contrato nao corresponde ao documento do cliente.');
    }

    public function test_inactive_client_cannot_receive_contract(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'status' => Client::STATUS_INACTIVE,
        ]);

        $this->actingAs($user)
            ->postJson("/api/clients/{$client->id}/contracts", [
                'person_type' => Contract::PERSON_TYPE_PF,
                'billing_cycle_day' => 10,
                'started_at' => '2026-08-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client')
            ->assertJsonPath('errors.client.0', 'Cliente inativo nao pode receber contrato.');
    }

    public function test_contract_billing_cycle_day_must_be_between_one_and_thirty_one(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/clients/{$client->id}/contracts", [
                'person_type' => Contract::PERSON_TYPE_PF,
                'billing_cycle_day' => 32,
                'started_at' => '2026-08-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('billing_cycle_day')
            ->assertJsonPath('errors.billing_cycle_day.0', 'O dia do ciclo de cobranca deve ser menor ou igual a 31.');
    }

    public function test_ended_contract_requires_ended_at(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/clients/{$client->id}/contracts", [
                'person_type' => Contract::PERSON_TYPE_PF,
                'billing_cycle_day' => 10,
                'status' => Contract::STATUS_ENDED,
                'started_at' => '2026-08-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ended_at')
            ->assertJsonPath('errors.ended_at.0', 'A data de encerramento e obrigatoria para contrato encerrado.');
    }

    public function test_authenticated_user_can_list_and_show_contracts(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $contract = Contract::factory()->for($client)->create([
            'person_type' => Contract::PERSON_TYPE_PF,
            'billing_cycle_day' => 5,
            'started_at' => '2026-08-01',
        ]);

        $this->actingAs($user)
            ->getJson("/api/clients/{$client->id}/contracts")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $contract->id)
            ->assertJsonPath('data.0.billing_cycle_day', 5);

        $this->actingAs($user)
            ->getJson("/api/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $contract->id)
            ->assertJsonPath('data.client_id', $client->id);
    }
}
