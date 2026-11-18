<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active', 'admin'])->get('/_test/admin-only', fn () => 'secret');
    }

    public function test_admin_middleware_allows_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/_test/admin-only')->assertOk()->assertSee('secret');
    }

    public function test_admin_middleware_blocks_employees(): void
    {
        $this->actingAs(User::factory()->employee()->create())->get('/_test/admin-only')->assertForbidden();
    }

    public function test_policy_prevents_admin_from_deactivating_or_demoting_self(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->employee()->create();

        $this->assertFalse($admin->can('toggleActive', $admin));
        $this->assertFalse($admin->can('changeRole', $admin));
        $this->assertTrue($admin->can('toggleActive', $other));
        $this->assertTrue($admin->can('changeRole', $other));
        $this->assertFalse($other->can('update', $admin));
        $this->assertFalse($other->can('viewAny', User::class));
    }

    public function test_create_admin_command_creates_an_active_admin(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Owner', '--email' => 'owner@example.com', '--password' => 'strong-pass-1'])
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('strong-pass-1', $user->password));
    }

    public function test_create_admin_command_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('app:create-admin', ['--name' => 'X', '--email' => 'taken@example.com', '--password' => 'strong-pass-1'])
            ->assertFailed();
        $this->artisan('app:create-admin', ['--name' => 'X', '--email' => 'new@example.com', '--password' => 'short'])
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }
}
