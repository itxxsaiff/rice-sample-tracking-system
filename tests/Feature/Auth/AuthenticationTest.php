<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_admin_can_log_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_employee_can_log_in_through_the_same_url(): void
    {
        $employee = User::factory()->employee()->create();

        $this->post('/login', ['email' => $employee->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($employee);
        $this->get('/')->assertOk()->assertSee($employee->name);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_deactivated_mid_session_is_signed_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_passwords_are_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-123']);

        $this->assertNotSame('secret-pass-123', $user->getRawOriginal('password'));
        $this->assertTrue(password_verify('secret-pass-123', $user->getRawOriginal('password')));
    }
}
