<?php

use Illuminate\Database\Seeder;

class InvoicePermission extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        \App\Laravue\Models\Permission::findOrCreate('view invoice', 'api');
        \App\Laravue\Models\Permission::findOrCreate('create invoice', 'api');
        \App\Laravue\Models\Permission::findOrCreate('update invoice', 'api');
        \App\Laravue\Models\Permission::findOrCreate('delete invoice', 'api');
        \App\Laravue\Models\Permission::findOrCreate('view waybill', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage waybill', 'api');
        \App\Laravue\Models\Permission::findOrCreate('generate waybill', 'api');
        \App\Laravue\Models\Permission::findOrCreate('manage waybill cost', 'api');


        // Assign new permissions to admin group
        $adminRole = App\Laravue\Models\Role::findByName(App\Laravue\Acl::ROLE_ADMIN);
        $adminRole->givePermissionTo(['view invoice', 'create invoice', 'update invoice', 'delete invoice', 'manage waybill', 'view waybill', 'generate waybill', 'manage waybill cost']);
    }
}
