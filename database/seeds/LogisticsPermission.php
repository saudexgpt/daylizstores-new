<?php

use Illuminate\Database\Seeder;

class LogisticsPermission extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        \App\Laravue\Models\Permission::findOrCreate('manage drivers', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage vehicles', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage vehicle expenses', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage vehicle conditions', 'api');
        \App\Laravue\Models\Permission::findOrCreate('approve vehicle expenses', 'api');

        // Assign new permissions to admin group
        $adminRole = App\Laravue\Models\Role::findByName(App\Laravue\Acl::ROLE_ADMIN);
        $adminRole->givePermissionTo(['manage drivers', 'manage vehicles', 'manage vehicle expenses', 'approve vehicle expenses', 'manage vehicle conditions']);
    }
}
