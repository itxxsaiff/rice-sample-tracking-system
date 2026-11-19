<?php

namespace Tests\Feature\Sync;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_worker_is_versioned_and_never_cached(): void
    {
        $res = $this->get('/sw.js')->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->assertHeader('Service-Worker-Allowed', '/');

        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $js = $res->getContent();
        $this->assertDoesNotMatchRegularExpression('/__VERSION__|__PRECACHE__/', $js);
        $this->assertMatchesRegularExpression("/const VERSION = '[a-f0-9]{12}'/", $js);
        $this->assertStringContainsString('"/offline-shell"', $js);
    }

    public function test_offline_shell_is_public_and_contains_no_user_data(): void
    {
        $user = User::factory()->create(['name' => 'Secret Person', 'email' => 'secret@example.com']);

        foreach ([null, $user] as $as) {
            $res = $as ? $this->actingAs($as)->get('/offline-shell') : $this->get('/offline-shell');
            $res->assertOk()->assertSee('offline-root', false)
                ->assertDontSee('Secret Person')->assertDontSee('secret@example.com')
                ->assertDontSee('csrf-token', false)->assertDontSee('app-user', false);
        }
    }

    public function test_signed_in_pages_publish_the_account_for_the_offline_layer(): void
    {
        $user = User::factory()->employee()->create(['name' => 'Field User']);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('<meta name="app-user"', false)
            ->assertSee('data-sync-indicator', false);
        $this->post(route('logout'));
        $this->get('/login')->assertDontSee('name="app-user"', false);
    }

    public function test_manifest_supports_home_screen_install(): void
    {
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertNotEmpty(collect($manifest['icons'])->firstWhere('sizes', '512x512'));
    }
}
