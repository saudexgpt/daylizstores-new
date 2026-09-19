<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the Accounting and Reports modules.
 *
 *   view accounting     see the books: overview, transactions, statements, chart of accounts
 *   manage accounting   record / correct / void transactions, edit accounts, change books settings
 *   view reports        open the Reports page and download reports (finance reports also need
 *                       "view accounting")
 *
 * Idempotent, and (like 2026_09_19_150000) makes sure the admin role holds every permission.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['view accounting', 'manage accounting', 'view reports'];

    public function up()
    {
        $now = now();
        foreach (self::PERMISSIONS as $name) {
            if (!DB::table('permissions')->where('name', $name)->where('guard_name', 'api')->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $adminId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->value('id');
        if ($adminId) {
            $missing = DB::table('permissions')
                ->where('guard_name', 'api')
                ->whereNotIn('id', DB::table('role_has_permissions')->where('role_id', $adminId)->select('permission_id'))
                ->pluck('id');
            foreach ($missing as $permissionId) {
                DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $adminId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'api')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
