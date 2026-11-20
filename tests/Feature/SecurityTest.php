<?php

namespace Tests\Feature;

use App\Models\Mill;
use App\Models\RiceVariety;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_every_business_route_requires_login(): void
    {
        foreach (['/', '/samples', '/dispatches', '/loading', '/records', '/mills', '/buyers', '/varieties', '/search', '/lookup/mills', '/settings/users'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_user_input_is_escaped_in_pages(): void
    {
        $mill = Mill::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs(User::factory()->create())->get(route('mills.show', $mill))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_inactive_variety_cannot_be_used_for_new_samples_but_old_samples_stay_editable(): void
    {
        $inactive = RiceVariety::factory()->create(['is_active' => false]);
        $mill = Mill::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('samples.store'), ['samples' => [
            ['mill_id' => $mill->id, 'rice_variety_id' => $inactive->id, 'collection_date' => now()->toDateString()],
        ]])->assertSessionHasErrors('samples.0.rice_variety_id');

        $old = Sample::factory()->create(['rice_variety_id' => $inactive->id, 'mill_id' => $mill->id]);
        $this->put(route('samples.update', $old), [
            'mill_id' => $mill->id, 'rice_variety_id' => $inactive->id,
            'collection_date' => now()->toDateString(), 'notes' => 'updated',
        ])->assertSessionHasNoErrors();
    }

    public function test_missing_records_show_friendly_404(): void
    {
        $this->actingAs(User::factory()->create())->get('/samples/999999')->assertNotFound()->assertSee('Page not found');
    }

    public function test_forbidden_page_is_friendly(): void
    {
        $this->actingAs(User::factory()->employee()->create())->get(route('users.index'))
            ->assertForbidden()->assertSee('Only administrators can access this page.');
    }
}
