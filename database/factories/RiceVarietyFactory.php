<?php

namespace Database\Factories;

use App\Models\RiceVariety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiceVariety>
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
