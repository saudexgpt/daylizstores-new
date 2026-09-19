<?php

use App\Laravue\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Laravue\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@ritebites.com.ng',
            'password' => Hash::make('password'),
        ]);
        $manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@ritebites.com.ng',
            'password' => Hash::make('password'),
        ]);

        $adminRole = Role::findByName(\App\Laravue\Acl::ROLE_ADMIN);
        $managerRole = Role::findByName(\App\Laravue\Acl::ROLE_MANAGER);
        $admin->syncRoles($adminRole);
        $manager->syncRoles($managerRole);
        // $this->call(UsersTableSeeder::class);
    }
}
