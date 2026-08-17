<?php

namespace Tests\Feature\Database;

use App\Models\Charge;
use App\Models\ChargePaymentDetail;
use App\Models\Client;
use App\Models\Contract;
use Database\Seeders\DomainSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataModelStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_tables_have_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('clients', [
            'id',
            'name',
            'document_type',
            'document',
            'address',
            'contact',
            'status',
        ]));

        $this->assertTrue(Schema::hasColumns('contracts', [
            'id',
            'client_id',
            'person_type',
            'billing_cycle_day',
            'status',
            'started_at',
            'ended_at',
        ]));

        $this->assertTrue(Schema::hasColumns('charges', [
            'id',
            'uuid',
            'contract_id',
            'billing_period',
            'payment_method',
            'original_amount',
            'fixed_fee_amount',
            'due_date',
            'status',
            'paid_original_amount',
            'paid_fixed_fee_amount',
            'paid_late_interest_amount',
            'paid_total_amount',
            'paid_at',
            'idempotency_key',
        ]));

        $this->assertTrue(Schema::hasColumns('charge_payment_details', [
            'id',
            'charge_id',
            'boleto_barcode',
            'pix_key',
            'pix_transaction_id',
            'card_reference',
            'card_brand',
            'card_last4',
        ]));
    }

    public function test_factories_create_relationship_graph(): void
    {
        $charge = Charge::factory()
            ->has(ChargePaymentDetail::factory(), 'paymentDetail')
            ->create();

        $this->assertInstanceOf(Contract::class, $charge->contract);
        $this->assertInstanceOf(Client::class, $charge->contract->client);
        $this->assertInstanceOf(ChargePaymentDetail::class, $charge->paymentDetail);
    }

    public function test_client_document_is_unique(): void
    {
        Client::factory()->create(['document' => '12345678909']);

        $this->expectException(QueryException::class);

        Client::factory()->create(['document' => '12345678909']);
    }

    public function test_charge_is_unique_by_contract_and_billing_period(): void
    {
        $contract = Contract::factory()->create();

        Charge::factory()->for($contract)->create([
            'billing_period' => '2026-08-01',
        ]);

        $this->expectException(QueryException::class);

        Charge::factory()->for($contract)->create([
            'billing_period' => '2026-08-01',
        ]);
    }

    public function test_charge_payment_detail_is_one_to_one(): void
    {
        $charge = Charge::factory()->create();

        ChargePaymentDetail::factory()->for($charge)->create();

        $this->expectException(QueryException::class);

        ChargePaymentDetail::factory()->for($charge)->create();
    }

    public function test_domain_seeder_populates_minimal_relationship_graph(): void
    {
        $this->seed(DomainSeeder::class);

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('charges', 1);
        $this->assertDatabaseCount('charge_payment_details', 1);
    }
}
