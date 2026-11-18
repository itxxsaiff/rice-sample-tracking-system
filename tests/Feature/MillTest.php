<?php

namespace Tests\Feature;

use App\Models\Mill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MillTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_a_mill(): void
    {
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)->post(route('mills.store'), [
            'name' => '  ABC   Rice Mill ', 'city' => 'Gujranwala', 'phone' => '03001112233',
        ]);

        $mill = Mill::firstOrFail();
        $response->assertRedirect(route('mills.show', $mill));
        $this->assertSame('ABC Rice Mill', $mill->name);
        $this->assertSame($employee->id, $mill->created_by);
        $this->assertDatabaseHas('activities', ['subject_type' => 'mill', 'subject_id' => $mill->id, 'event' => 'created']);
    }

    public function test_mill_requires_name_and_city(): void
    {
        $this->actingAs(User::factory()->create())->post(route('mills.store'), [])
            ->assertSessionHasErrors(['name', 'city']);
    }

    public function test_duplicate_mill_in_same_city_is_rejected(): void
    {
        Mill::factory()->create(['name' => 'ABC Rice Mill', 'city' => 'Gujranwala']);

        $this->actingAs(User::factory()->create())
            ->post(route('mills.store'), ['name' => 'ABC Rice Mill', 'city' => 'Gujranwala'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Mill::count());
    }

    public function test_same_mill_name_in_different_city_is_allowed(): void
    {
        Mill::factory()->create(['name' => 'ABC Rice Mill', 'city' => 'Gujranwala']);

        $this->actingAs(User::factory()->create())
            ->post(route('mills.store'), ['name' => 'ABC Rice Mill', 'city' => 'Hafizabad'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Mill::count());
    }

    public function test_quick_add_returns_json(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('mills.store'), ['name' => 'Quick Mill', 'city' => 'Kamoke'])
            ->assertCreated()
            ->assertJson(['label' => 'Quick Mill', 'sub' => 'Kamoke']);
    }

    public function test_mills_can_be_searched_and_filtered(): void
    {
        Mill::factory()->create(['name' => 'Sunrise Rice Mill', 'city' => 'Lahore']);
        Mill::factory()->create(['name' => 'Golden Grain Mill', 'city' => 'Kamoke']);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('mills.index', ['q' => 'sunrise']))
            ->assertSee('Sunrise Rice Mill')->assertDontSee('Golden Grain Mill');
        $this->get(route('mills.index', ['city' => 'Kamoke']))
            ->assertSee('Golden Grain Mill')->assertDontSee('Sunrise Rice Mill');
    }

    public function test_lookup_endpoint_returns_matches(): void
    {
        Mill::factory()->create(['name' => 'Sunrise Rice Mill', 'city' => 'Lahore']);

        $this->actingAs(User::factory()->create())->getJson(route('lookup.mills', ['q' => 'Lahore']))
            ->assertOk()->assertJsonPath('data.0.label', 'Sunrise Rice Mill');
    }

    public function test_mill_can_be_updated_and_viewed(): void
    {
        $mill = Mill::factory()->create();

        $this->actingAs(User::factory()->employee()->create())
            ->put(route('mills.update', $mill), ['name' => 'Renamed Mill', 'city' => 'Sialkot'])
            ->assertRedirect(route('mills.show', $mill));

        $this->get(route('mills.show', $mill))->assertOk()->assertSee('Renamed Mill')->assertSee('Sialkot');
    }

    public function test_only_admin_can_delete_an_unused_mill(): void
    {
        $mill = Mill::factory()->create();

        $this->actingAs(User::factory()->employee()->create())->delete(route('mills.destroy', $mill))->assertForbidden();
        $this->assertModelExists($mill);

        $this->actingAs(User::factory()->admin()->create())->delete(route('mills.destroy', $mill))->assertRedirect(route('mills.index'));
        $this->assertModelMissing($mill);
    }
}
