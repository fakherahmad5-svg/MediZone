<?php

namespace Database\Seeders;

use App\Models\Drug;
use Illuminate\Database\Seeder;

class DrugSeeder extends Seeder
{
    public function run(): void
    {
        $drugs = [
            ['name' => 'Paracetamol', 'form' => 'Tablet', 'strength' => '500mg'],
            ['name' => 'Ibuprofen', 'form' => 'Tablet', 'strength' => '400mg'],
            ['name' => 'Amoxicillin', 'form' => 'Capsule', 'strength' => '500mg'],
            ['name' => 'Metformin', 'form' => 'Tablet', 'strength' => '850mg'],
            ['name' => 'Amlodipine', 'form' => 'Tablet', 'strength' => '5mg'],
            ['name' => 'Omeprazole', 'form' => 'Capsule', 'strength' => '20mg'],
            ['name' => 'Salbutamol', 'form' => 'Inhaler', 'strength' => '100mcg'],
            ['name' => 'Atorvastatin', 'form' => 'Tablet', 'strength' => '20mg'],
            ['name' => 'Cetirizine', 'form' => 'Tablet', 'strength' => '10mg'],
            ['name' => 'Losartan', 'form' => 'Tablet', 'strength' => '50mg'],
        ];

        foreach ($drugs as $drug) {
            Drug::query()->firstOrCreate(
                [
                    'name' => $drug['name'],
                    'strength' => $drug['strength'],
                ],
                $drug
            );
        }
    }
}
