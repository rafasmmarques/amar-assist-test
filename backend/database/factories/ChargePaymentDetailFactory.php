<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\ChargePaymentDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargePaymentDetail>
 */
class ChargePaymentDetailFactory extends Factory
{
    public function definition()
    {
        return [
            'charge_id' => Charge::factory(),
            'boleto_barcode' => fake()->numerify('#####.##### #####.###### #####.###### # ##############'),
            'pix_key' => fake()->uuid(),
            'pix_transaction_id' => fake()->uuid(),
            'card_reference' => 'card_ref_'.fake()->uuid(),
            'card_brand' => fake()->randomElement(['visa', 'mastercard', 'elo']),
            'card_last4' => fake()->numerify('####'),
        ];
    }
}
