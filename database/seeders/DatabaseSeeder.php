<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            Phase3PermissionSeeder::class,
            DepartmentSeeder::class,
            ClinicSeeder::class,
            NotificationTemplateSeeder::class,
            DrugSeeder::class,
            //UserSeeder::class,
            AdminUserSeeder::class,
           // WalletSeeder::class,
        ]);
    }
}
