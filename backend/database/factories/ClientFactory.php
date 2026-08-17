<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'document_type' => Client::DOCUMENT_TYPE_CPF,
            'document' => fake()->unique()->numerify('###########'),
            'address' => fake()->streetAddress(),
            'contact' => fake()->email(),
            'status' => Client::STATUS_ACTIVE,
        ];
    }

    public function company()
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->company(),
            'document_type' => Client::DOCUMENT_TYPE_CNPJ,
            'document' => fake()->unique()->numerify('##############'),
        ]);
    }
}
