<?php

namespace Database\Factories;

use App\Enums\TruckLoadingStatus;
use App\Models\SampleDispatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Loading>
 */
class LoadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'loading_number' => 'LD-'.fake()->unique()->numerify('T#####'),
            'sample_dispatch_id' => SampleDispatch::factory()->confirmed(),
            'truck_number' => strtoupper(fake()->bothify('???-####')),
            'loading_date' => now()->format('Y-m-d'),
            'quantity' => 250,
            'quantity_unit' => 'maund',
            'status' => TruckLoadingStatus::Pending,
        ];
    }

    public function loaded(): static
    {
        return $this->state(fn () => ['status' => TruckLoadingStatus::Completed, 'completed_at' => now()]);
    }
}
