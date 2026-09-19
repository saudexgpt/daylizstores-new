<?php

use Illuminate\Database\Seeder;

class OrderPermission extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        \App\Laravue\Models\Permission::findOrCreate('view order', 'api');
        \App\Laravue\Models\Permission::findOrCreate('create order', 'api');
        \App\Laravue\Models\Permission::findOrCreate('cancel order', 'api');
        \App\Laravue\Models\Permission::findOrCreate('approve order', 'api');

        // Assign new permissions to admin group
        $adminRole = App\Laravue\Models\Role::findByName(App\Laravue\Acl::ROLE_ADMIN);
        $adminRole->givePermissionTo(['view order', 'create order', 'cancel order', 'approve order']);
    }
}
