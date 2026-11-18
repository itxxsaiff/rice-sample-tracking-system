<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SampleBundle>
 */
class SampleBundleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bundle_number' => 'BND-'.fake()->unique()->numerify('T####'),
            'bundle_date' => now()->format('Y-m-d'),
        ];
    }
}
