<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    public function definition()
    {
        return [
            'client_id' => Client::factory(),
            'person_type' => Contract::PERSON_TYPE_PF,
            'billing_cycle_day' => fake()->numberBetween(1, 28),
            'status' => Contract::STATUS_ACTIVE,
            'started_at' => now()->toDateString(),
            'ended_at' => null,
        ];
    }

    public function company()
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => Client::factory()->company(),
            'person_type' => Contract::PERSON_TYPE_PJ,
        ]);
    }

    public function ended()
    {
        return $this->state(fn (array $attributes) => [
            'status' => Contract::STATUS_ENDED,
            'ended_at' => now()->toDateString(),
        ]);
    }
}
