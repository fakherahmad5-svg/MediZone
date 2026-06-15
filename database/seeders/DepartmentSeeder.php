<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departmentsByClinic = [
            'riyadh@vmc.sa' => [
                ['name' => 'Cardiology', 'description' => 'Heart and cardiovascular care'],
                ['name' => 'Pediatrics', 'description' => 'Child and adolescent health'],
                ['name' => 'General Medicine', 'description' => 'Primary and general care'],
                ['name' => 'Dermatology', 'description' => 'Skin and cosmetic treatments'],

                ['name' => 'Orthopedics', 'description' => 'Bone, joint, and muscle care'],
                ['name' => 'General Medicine', 'description' => 'Primary and general care'],
                ['name' => 'Internal Medicine', 'description' => 'Adult internal medicine'],
            ],
        ];

        foreach ($departmentsByClinic as $clinicEmail => $departments) {
            $clinic = Clinic::query()->where('email', $clinicEmail)->firstOrFail();

            foreach ($departments as $department) {
                Department::query()->firstOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'name' => $department['name'],
                    ],
                    [
                        'description' => $department['description'],
                    ]
                );
            }
        }
    }
}
