<?php

namespace Database\Factories;

use App\Models\Mill;
use App\Models\RiceVariety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Sample>
 */
class SampleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sample_number' => 'SMP-'.fake()->unique()->numerify('T#####'),
            'mill_id' => Mill::factory(),
            'rice_variety_id' => RiceVariety::factory(),
            'collection_date' => fake()->dateTimeBetween('-60 days')->format('Y-m-d'),
            'quantity' => fake()->randomElement([250, 500, 1000]),
            'quantity_unit' => 'g',
        ];
    }
}
