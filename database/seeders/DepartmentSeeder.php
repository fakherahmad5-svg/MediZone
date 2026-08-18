<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [

                ['name' => 'Cardiology', 'description' => 'Heart and cardiovascular care'],
                ['name' => 'Pediatrics', 'description' => 'Child and adolescent health'],
                ['name' => 'General Medicine', 'description' => 'Primary and general care'],
                ['name' => 'Dermatology', 'description' => 'Skin and cosmetic treatments'],
                ['name' => 'Neurology',         'description' => 'الأمراض العصبية'],
                ['name' => 'Psychiatry',        'description' => 'الطب النفسي'],
                ['name' => 'Gynecology',        'description' => 'أمراض النسائية والتوليد'],
                ['name' => 'ENT',               'description' => 'أنف، أذن، حنجرة'],
                ['name' => 'Ophthalmology',     'description' => 'طب وجراحة العيون'],
                ['name' => 'Dentistry',         'description' => 'طب الأسنان'],
                ['name' => 'General Surgery',   'description' => 'الجراحة العامة'],
                ['name' => 'Orthopedics', 'description' => 'Bone, joint, and muscle care'],
                ['name' => 'Internal Medicine', 'description' => 'Adult internal medicine'],

        ];

        foreach ($departments as $dept) {
            DB::table('departments')->insert(array_merge($dept, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
        $this->command->info('✅ Departments seeded (' . count($departments) . ' departments).');
    }
}
