<?php

namespace Database\Seeders;

use App\Core\Enums\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminUserId = DB::table('users')->insertGetId([
            'first_name'        => 'System',
            'last_name'         => 'Administrator',
            'email'             => 'admin@vmc.test',
            'password'          => Hash::make('Admin@12345'),
            'status'            => UserStatus::Active->value,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        DB::table('clinic_users')->insert([
            'clinic_id'  => null,
            'user_id'    => $adminUserId,
            'role_id'    => $adminRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('✅ Super Admin created: admin@vmc.test / Admin@12345');

    }
}
