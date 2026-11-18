<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_details(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Name', 'email' => 'updated@example.com', 'phone' => '03001234567',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Updated Name', $user->fresh()->name);
    }

    public function test_employee_cannot_promote_themselves_via_profile(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'admin', 'is_active' => 1,
        ]);

        $this->assertSame(UserRole::Employee, $user->fresh()->role);
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'nope', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors('current_password');
    }
}
