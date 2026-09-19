<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions that routes ask for (`->middleware('permission:…')`) but that never existed
 * in the database:
 *
 *   view admin dashboard        GET  /api/dashboard/admin and .../running-out-of-stock-products
 *   backup database             GET  /api/reports/backups
 *   assign order to location    PUT  /api/order/general/assign-order-to-location/{order}
 *
 * Administrators were unaffected (the Gate::before admin bypass lets them through), but no
 * other role could ever be granted these, because a permission that isn't in the table
 * can't be ticked in Roles & permissions.
 *
 * Also makes sure the admin role actually holds every permission (the live database's admin
 * role was missing `manage location`), so the permission list the admin panel shows for an
 * administrator matches what they can really do.
 *
 * Idempotent: safe to run on a database that already has any of these rows.
 */
return new class extends Migration
{
    private const NEW_PERMISSIONS = [
        'view admin dashboard',
        'backup database',
        'assign order to location',
    ];

    public function up()
    {
        $guard = 'api';
        $now = now();

        foreach (self::NEW_PERMISSIONS as $name) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'guard_name' => $guard,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // the admin role holds every permission
        $adminId = DB::table('roles')->where('name', 'admin')->where('guard_name', $guard)->value('id');
        if ($adminId) {
            $missing = DB::table('permissions')
                ->where('guard_name', $guard)
                ->whereNotIn('id', DB::table('role_has_permissions')->where('role_id', $adminId)->select('permission_id'))
                ->pluck('id');
            foreach ($missing as $permissionId) {
                DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $adminId]);
            }
        }

        // Spatie caches the permission list; without this a running app wouldn't see the change
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        $ids = DB::table('permissions')->whereIn('name', self::NEW_PERMISSIONS)->where('guard_name', 'api')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
