<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_user_list(): void
    {
        $employee = User::factory()->employee()->create(['name' => 'Ali Raza']);

        $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->assertSee('Ali Raza');
    }

    public function test_employee_cannot_access_user_management(): void
    {
        $employee = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();

        $this->actingAs($employee);
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
        $this->get(route('users.edit', $other))->assertForbidden();
        $this->post(route('users.store'), $this->payload())->assertForbidden();
        $this->put(route('users.update', $other), ['name' => 'Hacked', 'email' => $other->email])->assertForbidden();
        $this->patch(route('users.toggle-active', $other))->assertForbidden();

        $this->assertTrue($other->fresh()->is_active);
        $this->assertDatabaseMissing('users', ['email' => 'new.employee@example.com']);
    }

    public function test_employee_cannot_promote_themselves(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)->put(route('users.update', $employee), [
            'name' => $employee->name, 'email' => $employee->email, 'role' => 'admin',
        ])->assertForbidden();

        $this->assertSame(UserRole::Employee, $employee->fresh()->role);
    }

    public function test_employee_navigation_hides_user_management(): void
    {
        $this->actingAs(User::factory()->employee()->create())->get('/')->assertDontSee('Manage Users');
        $this->actingAs($this->admin)->get('/')->assertSee('Manage Users');
    }

    public function test_admin_can_create_employee_account(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), $this->payload())
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'new.employee@example.com')->firstOrFail();
        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('employee-pass-1', $user->password));
    }

    public function test_created_employee_can_log_in(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), $this->payload());
        $this->post(route('logout'));

        $this->post('/login', ['email' => 'new.employee@example.com', 'password' => 'employee-pass-1'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_user_creation_is_validated(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), $this->payload([
            'email' => $this->admin->email, 'password' => 'short', 'password_confirmation' => 'other', 'role' => 'superuser',
        ]))->assertSessionHasErrors(['email', 'password', 'role']);
    }

    public function test_admin_can_edit_employee_and_reset_password(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($this->admin)->put(route('users.update', $employee), [
            'name' => 'Renamed', 'email' => $employee->email, 'role' => 'employee',
            'password' => 'reset-pass-99', 'password_confirmation' => 'reset-pass-99',
        ])->assertRedirect(route('users.index'));

        $employee->refresh();
        $this->assertSame('Renamed', $employee->name);
        $this->assertTrue(Hash::check('reset-pass-99', $employee->password));
    }

    public function test_blank_password_keeps_existing_password(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($this->admin)->put(route('users.update', $employee), [
            'name' => $employee->name, 'email' => $employee->email, 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password', $employee->fresh()->password));
    }

    public function test_admin_can_promote_employee(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($this->admin)->put(route('users.update', $employee), [
            'name' => $employee->name, 'email' => $employee->email, 'role' => 'admin',
        ]);

        $this->assertSame(UserRole::Admin, $employee->fresh()->role);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
            'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'employee',
        ]);

        $this->assertSame(UserRole::Admin, $this->admin->fresh()->role);
    }

    public function test_admin_can_deactivate_and_reactivate_employee(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($this->admin)->patch(route('users.toggle-active', $employee))->assertRedirect();
        $this->assertFalse($employee->fresh()->is_active);

        $this->patch(route('users.toggle-active', $employee));
        $this->assertTrue($employee->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $this->actingAs($this->admin)->patch(route('users.toggle-active', $this->admin))->assertForbidden();
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Employee',
            'email' => 'new.employee@example.com',
            'phone' => '03001234567',
            'role' => 'employee',
            'password' => 'employee-pass-1',
            'password_confirmation' => 'employee-pass-1',
        ], $overrides);
    }
}
