<?php

use Illuminate\Database\Seeder;

class MenuPermission extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Laravue\Models\Permission::findOrCreate('create menu', 'api');
        \App\Laravue\Models\Permission::findOrCreate('edit menu', 'api');
        \App\Laravue\Models\Permission::findOrCreate('delete menu', 'api');

        // Assign new permissions to admin group
        $adminRole = App\Laravue\Models\Role::findByName(App\Laravue\Acl::ROLE_ADMIN);
        $adminRole->givePermissionTo(['create menu', 'edit menu', 'delete menu']);
    }
}
