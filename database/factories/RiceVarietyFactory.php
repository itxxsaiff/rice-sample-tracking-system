<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RiceVariety>
 */
class RiceVarietyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('Variety-###??'),
            'description' => null,
        ];
    }
}
