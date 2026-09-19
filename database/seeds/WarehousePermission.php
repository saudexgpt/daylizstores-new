<?php

use Illuminate\Database\Seeder;

class WarehousePermission extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        \App\Laravue\Models\Permission::findOrCreate('view warehouse', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage warehouse', 'api');
        \App\Laravue\Models\Permission::findOrCreate('view reports', 'api');
        \App\Laravue\Models\Permission::findOrCreate('view audit trail', 'api');

        // Assign new permissions to admin group
        $adminRole = App\Laravue\Models\Role::findByName(App\Laravue\Acl::ROLE_ADMIN);
        $adminRole->givePermissionTo(['view warehouse', 'manage warehouse', 'view reports', 'view audit trail']);
    }
}
