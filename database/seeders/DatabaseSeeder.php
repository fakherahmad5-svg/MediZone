<?php

namespace Database\Seeders;

use Illuminate\database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepartmentSeeder::class,
            ClinicSeeder::class,
            NotificationTemplateSeeder::class,
            DrugSeeder::class,
            //UserSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
