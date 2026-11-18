<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Buyer>
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
