<?php

use Illuminate\Database\Seeder;

class StockPermission extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        \App\Laravue\Models\Permission::findOrCreate('view general items', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage general items', 'api');

        \App\Laravue\Models\Permission::findOrCreate('view item stocks', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage item stocks', 'api');
        \App\Laravue\Models\Permission::findOrCreate('create item stocks', 'api');
        \App\Laravue\Models\Permission::findOrCreate('update item stocks', 'api');
        \App\Laravue\Models\Permission::findOrCreate('delete item stocks', 'api');

        \App\Laravue\Models\Permission::findOrCreate('view item category', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage item category', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage returned products', 'api');
        \App\Laravue\Models\Permission::findOrCreate('view returned products', 'api');

        // Assign new permissions to admin group
        $adminRole = App\Laravue\Models\Role::findByName(App\Laravue\Acl::ROLE_ADMIN);
        $adminRole->givePermissionTo(['view general items', 'manage general items', 'view item stocks', 'manage item stocks', 'create item stocks', 'update item stocks', 'delete item stocks', 'view item category', 'manage item category', 'manage returned products', 'view returned products']);
    }
}
