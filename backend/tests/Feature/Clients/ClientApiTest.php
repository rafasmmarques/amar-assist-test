<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_routes_require_authentication(): void
    {
        $this->getJson('/api/clients')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_client_with_normalized_cpf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/clients', [
                'name' => 'Maria Silva',
                'document_type' => Client::DOCUMENT_TYPE_CPF,
                'document' => '529.982.247-25',
                'address' => 'Rua Central, 100',
                'contact' => 'maria@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Cliente criado com sucesso.')
            ->assertJsonPath('data.name', 'Maria Silva')
            ->assertJsonPath('data.document_type', Client::DOCUMENT_TYPE_CPF)
            ->assertJsonPath('data.document', '52998224725');

        $this->assertDatabaseHas('clients', [
            'document' => '52998224725',
            'status' => Client::STATUS_ACTIVE,
        ]);
    }

    public function test_client_document_must_have_valid_check_digits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/clients', [
                'name' => 'Documento Invalido',
                'document_type' => Client::DOCUMENT_TYPE_CPF,
                'document' => '111.111.111-11',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document')
            ->assertJsonPath('errors.document.0', 'O documento informado e invalido para o tipo selecionado.');
    }

    public function test_client_document_must_be_unique_after_normalization(): void
    {
        $user = User::factory()->create();

        Client::factory()->create([
            'document_type' => Client::DOCUMENT_TYPE_CPF,
            'document' => '52998224725',
        ]);

        $this->actingAs($user)
            ->postJson('/api/clients', [
                'name' => 'Maria Silva',
                'document_type' => Client::DOCUMENT_TYPE_CPF,
                'document' => '529.982.247-25',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document')
            ->assertJsonPath('errors.document.0', 'O documento informado ja esta cadastrado.');
    }

    public function test_authenticated_user_can_filter_clients_with_pagination_envelope(): void
    {
        $user = User::factory()->create();

        Client::factory()->create([
            'name' => 'Maria Silva',
            'document' => '52998224725',
            'status' => Client::STATUS_ACTIVE,
        ]);
        Client::factory()->create([
            'name' => 'Joao Souza',
            'document' => '39053344705',
            'status' => Client::STATUS_INACTIVE,
        ]);

        $this->actingAs($user)
            ->getJson('/api/clients?name=Maria&status=active&document=529.982&page=1&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Maria Silva')
            ->assertJsonPath('data.0.document', '52998224725')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_unknown_client_filter_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/clients?foo=bar')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('foo')
            ->assertJsonPath('errors.foo.0', 'Filtro desconhecido.');
    }

    public function test_authenticated_user_can_update_client_and_keep_document_unique(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'name' => 'Nome antigo',
            'document_type' => Client::DOCUMENT_TYPE_CNPJ,
            'document' => '11222333000181',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/clients/{$client->id}", [
                'name' => 'Empresa Atualizada',
                'document' => '11.222.333/0001-81',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Cliente atualizado com sucesso.')
            ->assertJsonPath('data.name', 'Empresa Atualizada')
            ->assertJsonPath('data.document', '11222333000181');
    }

    public function test_client_with_contract_cannot_change_document_type(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'document_type' => Client::DOCUMENT_TYPE_CPF,
            'document' => '52998224725',
        ]);
        Contract::factory()->for($client)->create([
            'person_type' => Contract::PERSON_TYPE_PF,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/clients/{$client->id}", [
                'document_type' => Client::DOCUMENT_TYPE_CNPJ,
                'document' => '11.222.333/0001-81',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_type')
            ->assertJsonPath('errors.document_type.0', 'Cliente com contrato associado nao pode alterar o tipo de documento.');
    }

    public function test_client_without_contracts_can_be_deactivated_and_reactivated(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($user)
            ->patchJson("/api/clients/{$client->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', Client::STATUS_INACTIVE);

        $this->actingAs($user)
            ->patchJson("/api/clients/{$client->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', Client::STATUS_ACTIVE);
    }

    public function test_client_with_any_contract_cannot_be_deactivated(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        Contract::factory()->for($client)->create();

        $this->actingAs($user)
            ->patchJson("/api/clients/{$client->id}/deactivate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client')
            ->assertJsonPath('errors.client.0', 'Cliente com contrato associado nao pode ser desativado.');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'status' => Client::STATUS_ACTIVE,
        ]);
    }
}
