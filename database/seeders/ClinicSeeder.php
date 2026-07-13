<?php

namespace Database\Seeders;

use App\Models\Clinic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        $clinicId = DB::table('clinics')->insertGetId([
            'name'       => 'Virtual Medical Complex - Main Branch',
            'phone'      => '+963-11-0000000',
            'address'    => 'Damascus, Syria',
            'status'     => 'active',
            'latitude'   => 33.5138,
            'longitude'  => 36.2765,
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        $departmentIds = DB::table('departments')->pluck('id');

        $pivotRows = $departmentIds->map(fn ($deptId) => [
            'clinic_id'     => $clinicId,
            'department_id' => $deptId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ])->toArray();

        DB::table('clinic_departments')->insert($pivotRows);

        $this->command->info("✅ Default clinic seeded (ID: {$clinicId}) with all departments linked.");
    }
}
