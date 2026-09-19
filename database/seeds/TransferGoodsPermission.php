<?php

use Illuminate\Database\Seeder;

class TransferGoodsPermission extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        \App\Laravue\Models\Permission::findOrCreate('manage transfer request', 'api');

        // Assign new permissions to admin group
        $adminRole = App\Laravue\Models\Role::findByName(App\Laravue\Acl::ROLE_ADMIN);
        $adminRole->givePermissionTo(['manage transfer request']);
    }
}
