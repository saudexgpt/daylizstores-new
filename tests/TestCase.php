<?php

namespace Tests;

use App\Laravue\Models\Role;
use App\Laravue\Models\User;
use App\Models\Setting\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Create a user with the 'admin' role (bypasses all permission checks
     * via the Gate::before hook in AuthServiceProvider).
     */
    protected function createAdminUser(array $attributes = []): User
    {
        $user = User::factory()->staff()->create($attributes);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $user->syncRoles($role);

        return $user;
    }

    /**
     * Create a plain staff user with no elevated role/permissions.
     */
    protected function createStaffUser(array $attributes = []): User
    {
        $user = User::factory()->staff()->create($attributes);
        $role = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api']);
        $user->syncRoles($role);

        return $user;
    }

    /**
     * Create a plain customer user.
     */
    protected function createCustomerUser(array $attributes = []): User
    {
        $user = User::factory()->customer()->create($attributes);
        $role = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'api']);
        $user->syncRoles($role);

        return $user;
    }

    /**
     * Seed the baseline `settings` rows several controller code paths read
     * unconditionally (e.g. Controller::settingValue('can_make_order') is
     * called by order placement and fatal-errors if the row is missing).
     */
    protected function seedBaselineSettings(): void
    {
        // Setting has no $fillable (fully guarded like most models in this
        // app), so it's set via direct property assignment rather than mass
        // assignment.
        if (!Setting::where('key', 'can_make_order')->exists()) {
            $setting = new Setting();
            $setting->key = 'can_make_order';
            $setting->value = 'true';
            $setting->save();
        }
        // Defaults to enabled for tests exercising the Paystack path itself
        // (its own migration seeds it 'false' — disabled in production —
        // so it must be forced here rather than only-if-missing); tests
        // covering the disabled state override this explicitly afterward.
        $onlinePayment = Setting::where('key', 'online_payment_enabled')->first();
        if ($onlinePayment) {
            $onlinePayment->value = 'true';
            $onlinePayment->save();
        } else {
            $setting = new Setting();
            $setting->key = 'online_payment_enabled';
            $setting->value = 'true';
            $setting->save();
        }
    }
}
