<?php

namespace Tests\Feature\Authorization;

use App\Laravue\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the Phase 0 privilege-escalation fix: before that fix,
 * any authenticated user (including a plain customer) could create a new
 * account with an arbitrary role — including 'admin' — because the
 * `permission:...` route middleware was commented out and
 * UserController::store() trusted the client-supplied `role` field outright.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function testCustomerCannotCreateUsers()
    {
        $customer = $this->createCustomerUser();

        $response = $this->actingAs($customer, 'api')->postJson('/api/users', [
            'name' => 'Malicious Actor',
            'email' => 'malicious@example.com',
            'phone' => '08011111111',
            'password' => 'password1',
            'confirmPassword' => 'password1',
            'roles' => ['admin'],
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'malicious@example.com']);
    }

    public function testAdminCanCreateUsersWithAnyRole()
    {
        $admin = $this->createAdminUser();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);

        $response = $this->actingAs($admin, 'api')->postJson('/api/users', [
            'name' => 'New Manager',
            'email' => 'manager@example.com',
            'phone' => '08022222222',
            'password' => 'password1',
            'confirmPassword' => 'password1',
            'roles' => ['manager'],
            'role' => 'manager',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'manager@example.com']);
    }

    public function testCustomerCannotListUsers()
    {
        $customer = $this->createCustomerUser();

        $response = $this->actingAs($customer, 'api')->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function testAdminCanListUsersWithoutExplicitPermissionGrant()
    {
        // Confirms the Gate::before admin bypass: an admin should never be
        // locked out of a permission-gated route just because nobody
        // explicitly granted them the underlying Spatie permission record.
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'api')->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function testStaffWithoutManageUserPermissionCannotResetPasswords()
    {
        $staff = $this->createStaffUser();
        $target = $this->createCustomerUser();

        $response = $this->actingAs($staff, 'api')
            ->putJson('/api/users/reset-password/' . $target->id);

        $response->assertStatus(403);
    }

    public function testCustomerCannotAccessAdminDashboard()
    {
        $customer = $this->createCustomerUser();

        $response = $this->actingAs($customer, 'api')->getJson('/api/dashboard/admin');

        $response->assertStatus(403);
    }

    public function testAdminCanAccessAdminDashboard()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'api')->getJson('/api/dashboard/admin');

        $response->assertStatus(200);
    }
}
