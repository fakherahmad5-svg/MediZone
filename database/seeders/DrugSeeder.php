<?php

namespace Database\Seeders;

use App\Models\Drug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DrugSeeder extends Seeder
{
    public function run(): void
    {
        $drugs = [
            ['name' => 'Paracetamol',   'form' => 'tablet',     'strength' => '500mg'],
            ['name' => 'Paracetamol',   'form' => 'syrup',      'strength' => '120mg/5ml'],
            ['name' => 'Ibuprofen',     'form' => 'tablet',     'strength' => '400mg'],
            ['name' => 'Amoxicillin',   'form' => 'capsule',    'strength' => '500mg'],
            ['name' => 'Amoxicillin',   'form' => 'syrup',      'strength' => '250mg/5ml'],
            ['name' => 'Metformin',     'form' => 'tablet',     'strength' => '500mg'],
            ['name' => 'Atorvastatin',  'form' => 'tablet',     'strength' => '20mg'],
            ['name' => 'Omeprazole',    'form' => 'capsule',    'strength' => '20mg'],
            ['name' => 'Cetirizine',    'form' => 'tablet',     'strength' => '10mg'],
            ['name' => 'Salbutamol',    'form' => 'inhaler',    'strength' => '100mcg'],
            ['name' => 'Insulin Glargine', 'form' => 'injection', 'strength' => '100units/ml'],
            ['name' => 'Losartan',      'form' => 'tablet',     'strength' => '50mg'],
            ['name' => 'Azithromycin',  'form' => 'tablet',     'strength' => '250mg'],
            ['name' => 'Diclofenac',    'form' => 'gel',        'strength' => '1%'],
            ['name' => 'Aspirin',       'form' => 'tablet',     'strength' => '100mg'],
        ];

        foreach ($drugs as $drug) {
            DB::table('drugs')->insert(array_merge($drug, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('✅ Drugs seeded (' . count($drugs) . ' drugs).');
    }
}
