<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_a_buyer(): void
    {
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)->post(route('buyers.store'), [
            'name' => '  ABC   Traders ', 'city' => 'Gujranwala', 'phone' => '03001112233',
        ]);

        $buyer = Buyer::firstOrFail();
        $response->assertRedirect(route('buyers.show', $buyer));
        $this->assertSame('ABC Traders', $buyer->name);
        $this->assertSame($employee->id, $buyer->created_by);
        $this->assertDatabaseHas('activities', ['subject_type' => 'buyer', 'subject_id' => $buyer->id, 'event' => 'created']);
    }

    public function test_buyer_requires_name_and_city(): void
    {
        $this->actingAs(User::factory()->create())->post(route('buyers.store'), [])
            ->assertSessionHasErrors(['name', 'city']);
    }

    public function test_duplicate_buyer_in_same_city_is_rejected(): void
    {
        Buyer::factory()->create(['name' => 'ABC Traders', 'city' => 'Gujranwala']);

        $this->actingAs(User::factory()->create())
            ->post(route('buyers.store'), ['name' => 'ABC Traders', 'city' => 'Gujranwala'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Buyer::count());
    }

    public function test_same_buyer_name_in_different_city_is_allowed(): void
    {
        Buyer::factory()->create(['name' => 'ABC Traders', 'city' => 'Gujranwala']);

        $this->actingAs(User::factory()->create())
            ->post(route('buyers.store'), ['name' => 'ABC Traders', 'city' => 'Hafizabad'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Buyer::count());
    }

    public function test_quick_add_returns_json(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('buyers.store'), ['name' => 'Quick Buyer Co', 'city' => 'Kamoke'])
            ->assertCreated()
            ->assertJson(['label' => 'Quick Buyer Co', 'sub' => 'Kamoke']);
    }

    public function test_buyers_can_be_searched_and_filtered(): void
    {
        Buyer::factory()->create(['name' => 'Sunrise Traders', 'city' => 'Lahore']);
        Buyer::factory()->create(['name' => 'Golden Exports', 'city' => 'Kamoke']);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('buyers.index', ['q' => 'sunrise']))
            ->assertSee('Sunrise Traders')->assertDontSee('Golden Exports');
        $this->get(route('buyers.index', ['city' => 'Kamoke']))
            ->assertSee('Golden Exports')->assertDontSee('Sunrise Traders');
    }

    public function test_lookup_endpoint_returns_matches(): void
    {
        Buyer::factory()->create(['name' => 'Sunrise Traders', 'city' => 'Lahore']);

        $this->actingAs(User::factory()->create())->getJson(route('lookup.buyers', ['q' => 'Lahore']))
            ->assertOk()->assertJsonPath('data.0.label', 'Sunrise Traders');
    }

    public function test_buyer_can_be_updated_and_viewed(): void
    {
        $buyer = Buyer::factory()->create();

        $this->actingAs(User::factory()->employee()->create())
            ->put(route('buyers.update', $buyer), ['name' => 'Renamed Buyer', 'city' => 'Sialkot'])
            ->assertRedirect(route('buyers.show', $buyer));

        $this->get(route('buyers.show', $buyer))->assertOk()->assertSee('Renamed Buyer')->assertSee('Sialkot');
    }

    public function test_only_admin_can_delete_an_unused_buyer(): void
    {
        $buyer = Buyer::factory()->create();

        $this->actingAs(User::factory()->employee()->create())->delete(route('buyers.destroy', $buyer))->assertForbidden();
        $this->assertModelExists($buyer);

        $this->actingAs(User::factory()->admin()->create())->delete(route('buyers.destroy', $buyer))->assertRedirect(route('buyers.index'));
        $this->assertModelMissing($buyer);
    }
}
