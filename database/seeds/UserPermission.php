<?php

use Illuminate\Database\Seeder;

class UserPermission extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        \App\Laravue\Models\Permission::findOrCreate('audit confirm actions', 'api');


        // Assign new permissions to admin group
        $adminRole = App\Laravue\Models\Role::findByName(App\Laravue\Acl::ROLE_ADMIN);
        $adminRole->givePermissionTo(['audit confirm actions']);
    }
}
