<?php

namespace Database\Factories;

use App\Models\Mill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mill>
 */
class MillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Rice Mill',
            'city' => fake()->randomElement(['Gujranwala', 'Sheikhupura', 'Hafizabad', 'Kamoke', 'Lahore']),
            'contact_person' => fake()->name(),
            'phone' => '03'.fake()->numerify('#########'),
            'address' => fake()->streetAddress(),
        ];
    }
}
