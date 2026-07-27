<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class Phase3PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'manage_receptionists')->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name'       => 'manage_receptionists',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        if ($adminRoleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id'       => $adminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        $this->command?->info('✅ Phase 3 permission seeded: manage_receptionists → admin');
    }
}
