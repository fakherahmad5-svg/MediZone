<?php

namespace Database\Seeders;

use App\Models\Clinic;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = [
            [
                'name' => 'المجمع الطبي الافتراضي -ض',
                'phone' => '+966112345678',
                'email' => 'riyadh@vmc.sa',
                'address' => 'حي العليا، طريق الملك فهد، الرياض',
            ],
        
        ];

        foreach ($clinics as $clinic) {
            Clinic::query()->firstOrCreate(
                ['email' => $clinic['email']],
                $clinic
            );
        }
    }
}
