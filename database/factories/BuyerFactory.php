<?php

namespace Database\Factories;

use App\Models\Buyer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Buyer>
 */
class BuyerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Traders',
            'city' => fake()->randomElement(['Karachi', 'Lahore', 'Faisalabad', 'Multan', 'Islamabad']),
            'contact_person' => fake()->name(),
            'phone' => '03'.fake()->numerify('#########'),
            'address' => fake()->streetAddress(),
        ];
    }
}
