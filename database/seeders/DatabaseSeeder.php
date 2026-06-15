<?php

namespace Database\Seeders;

use Illuminate\database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ClinicSeeder::class,
            DepartmentSeeder::class,
            NotificationTemplateSeeder::class,
            DrugSeeder::class,
            UserSeeder::class,
        ]);
    }
}
