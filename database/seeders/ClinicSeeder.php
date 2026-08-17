<?php

namespace Database\Seeders;

use App\Core\Enums\ClinicStatus;
use App\Models\Clinic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        $clinicsData = [
            [
                'name'      => 'Virtual Medical Complex - Main Branch',
                'phone'     => '+963-11-0000000',
                'address'   => 'Damascus, Syria',
                'latitude'  => 33.5138,
                'longitude' => 36.2765,
            ],
            [
                'name'      => 'Virtual Medical Complex - Aleppo Branch',
                'phone'     => '+963-21-0000001',
                'address'   => 'Aleppo, Syria',
                'latitude'  => 36.2021,
                'longitude' => 37.1343,
            ],
            [
                'name'      => 'Virtual Medical Complex - Homs Branch',
                'phone'     => '+963-31-0000002',
                'address'   => 'Homs, Syria',
                'latitude'  => 34.7324,
                'longitude' => 36.7137,
            ],
            [
                'name'      => 'Virtual Medical Complex - Latakia Branch',
                'phone'     => '+963-41-0000003',
                'address'   => 'Latakia, Syria',
                'latitude'  => 35.5317,
                'longitude' => 35.7915,
            ],
            [
                'name'      => 'Virtual Medical Complex - Hama Branch',
                'phone'     => '+963-33-0000004',
                'address'   => 'Hama, Syria',
                'latitude'  => 35.1318,
                'longitude' => 36.7578,
            ],
            [
                'name'      => 'Virtual Medical Complex - Tartus Branch',
                'phone'     => '+963-43-0000005',
                'address'   => 'Tartus, Syria',
                'latitude'  => 34.8890,
                'longitude' => 35.8866,
            ],
        ];

        $departmentIds = DB::table('departments')->pluck('id');

        foreach ($clinicsData as $data) {
            $clinic = Clinic::create([
                ...$data,
                'status' => ClinicStatus::Active->value,
            ]);

            $pivotRows = $departmentIds->map(fn ($deptId) => [
                'clinic_id'     => $clinic->id,
                'department_id' => $deptId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ])->toArray();

            DB::table('clinic_departments')->insert($pivotRows);

            $this->command->info("✅ Clinic seeded (ID: {$clinic->id}, Code: {$clinic->code}, Name: {$clinic->name}) with all departments linked.");
        }
    }
}
