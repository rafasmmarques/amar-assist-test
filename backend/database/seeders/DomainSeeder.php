<?php

namespace Database\Seeders;

use App\Models\Charge;
use App\Models\ChargePaymentDetail;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Database\Seeder;

class DomainSeeder extends Seeder
{
    public function run()
    {
        $client = Client::query()->firstOrCreate([
            'document' => '12345678909',
        ], [
            'name' => 'Cliente Exemplo',
            'document_type' => Client::DOCUMENT_TYPE_CPF,
            'address' => 'Rua Exemplo, 100',
            'contact' => 'cliente@example.com',
            'status' => Client::STATUS_ACTIVE,
        ]);

        $contract = Contract::query()->firstOrCreate([
            'client_id' => $client->id,
            'started_at' => now()->startOfYear()->toDateString(),
        ], [
            'person_type' => Contract::PERSON_TYPE_PF,
            'billing_cycle_day' => 10,
            'status' => Contract::STATUS_ACTIVE,
            'ended_at' => null,
        ]);

        $charge = Charge::query()->firstOrCreate([
            'contract_id' => $contract->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
        ], [
            'uuid' => (string) str()->uuid(),
            'due_date' => now()->startOfMonth()->addDays(9)->toDateString(),
            'payment_method' => Charge::PAYMENT_METHOD_PIX,
            'original_amount' => '100.00',
            'fixed_fee_amount' => '5.00',
            'status' => Charge::STATUS_OPEN,
        ]);

        ChargePaymentDetail::query()->updateOrCreate([
            'charge_id' => $charge->id,
        ], [
            'boleto_barcode' => null,
            'pix_key' => 'cliente-exemplo@pix.local',
            'pix_transaction_id' => null,
            'card_reference' => null,
            'card_brand' => null,
            'card_last4' => null,
        ]);
    }
}
