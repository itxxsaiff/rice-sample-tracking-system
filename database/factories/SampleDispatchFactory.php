<?php

namespace Database\Factories;

use App\Enums\DispatchStatus;
use App\Enums\LoadingStatus;
use App\Models\Buyer;
use App\Models\Sample;
use App\Models\SampleDispatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SampleDispatch>
 */
class SampleDispatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dispatch_number' => 'DSP-'.fake()->unique()->numerify('T#####'),
            'sample_id' => Sample::factory(),
            'buyer_id' => Buyer::factory(),
            'dispatch_date' => now()->subDays(3)->format('Y-m-d'),
            'status' => DispatchStatus::Pending,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => DispatchStatus::Confirmed,
            'confirmed_quantity' => 500,
            'confirmed_quantity_unit' => 'maund',
            'rate' => 9500,
            'rate_unit' => 'per_maund',
            'packing_type' => 'PP bag',
            'confirmation_date' => now()->format('Y-m-d'),
            'responded_at' => now(),
            'loading_status' => LoadingStatus::Pending,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => DispatchStatus::Rejected,
            'rejection_reason' => 'Quality not matched',
            'rejection_date' => now()->format('Y-m-d'),
            'responded_at' => now(),
        ]);
    }
}
