<?php

namespace Tests\Feature;

use App\Models\RiceVariety;
use App\Models\User;
use Database\Seeders\RiceVarietySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiceVarietyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_adds_common_varieties_idempotently(): void
    {
        $this->seed(RiceVarietySeeder::class);
        $this->seed(RiceVarietySeeder::class);

        $this->assertDatabaseHas('rice_varieties', ['name' => '1121']);
        $this->assertDatabaseHas('rice_varieties', ['name' => '1509']);
        $this->assertSame(1, RiceVariety::where('name', '1121')->count());
    }

    public function test_any_user_can_add_a_new_variety(): void
    {
        $this->actingAs(User::factory()->employee()->create())
            ->post(route('varieties.store'), ['name' => '1847'])
            ->assertRedirect(route('varieties.index'));

        $this->assertDatabaseHas('rice_varieties', ['name' => '1847', 'is_active' => true]);
    }

    public function test_quick_add_variety_returns_json_and_rejects_duplicates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('varieties.store'), ['name' => 'Kainat'])
            ->assertCreated()->assertJson(['label' => 'Kainat']);
        $this->postJson(route('varieties.store'), ['name' => 'Kainat'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_variety_can_be_deactivated_and_is_hidden_from_options(): void
    {
        $variety = RiceVariety::factory()->create(['name' => 'Old Type']);

        $this->actingAs(User::factory()->create())
            ->put(route('varieties.update', $variety), ['name' => 'Old Type', 'is_active' => '0'])
            ->assertRedirect(route('varieties.index'));

        $this->assertFalse($variety->fresh()->is_active);
        $this->assertArrayNotHasKey($variety->id, RiceVariety::options());
        $this->assertArrayHasKey($variety->id, RiceVariety::options(include: $variety->id));
    }

    public function test_only_admin_can_delete_varieties(): void
    {
        $variety = RiceVariety::factory()->create();

        $this->actingAs(User::factory()->employee()->create())->delete(route('varieties.destroy', $variety))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('varieties.destroy', $variety))->assertRedirect();
        $this->assertModelMissing($variety);
    }
}
