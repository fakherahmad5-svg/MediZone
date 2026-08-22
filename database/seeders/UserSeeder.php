<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Department;
use App\Models\ClinicUser;
use App\Models\Role;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Receptionist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // NOTE: clinics have no 'email' column and departments have no
        // 'clinic_id' column in the current schema (departments are
        // global, linked to clinics only via the clinic_departments
        // pivot) - looked up by name instead.
        $mainClinic = Clinic::query()->where('name', 'Virtual Medical Complex - Main Branch')->firstOrFail();
        $cardiology = Department::query()
            ->where('name', 'Cardiology')
            ->firstOrFail();
        $pediatrics = Department::query()
            ->where('name', 'Pediatrics')
            ->firstOrFail();

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $doctorRole = Role::query()->where('name', 'doctor')->firstOrFail();
        $patientRole = Role::query()->where('name', 'patient')->firstOrFail();
        $receptionistRole = Role::query()->where('name', 'receptionist')->firstOrFail();

        $admin = $this->createUser([
            'first_name' => 'System',
            'last_name' => 'Admin',
            'email' => 'admin@vmc.sa',
            'phone' => '+966500000001',
            'dob' => '1985-01-15',
            'gender' => 'male',
        ]);

        $doctorAhmed = $this->createUser([
            'first_name' => 'Ahmed',
            'last_name' => 'Al-Rashid',
            'email' => 'doctor1@vmc.sa',
            'phone' => '+966500000002',
            'dob' => '1980-06-20',
            'gender' => 'male',
        ]);

        $doctorSara = $this->createUser([
            'first_name' => 'Sara',
            'last_name' => 'Al-Harbi',
            'email' => 'doctor2@vmc.sa',
            'phone' => '+966500000003',
            'dob' => '1988-03-10',
            'gender' => 'female',
        ]);

        $patientKhalid = $this->createUser([
            'first_name' => 'Khalid',
            'last_name' => 'Al-Otaibi',
            'email' => 'patient1@vmc.sa',
            'phone' => '+966500000004',
            'dob' => '1992-11-05',
            'gender' => 'male',
        ]);

        $patientMinor = $this->createUser([
            'first_name' => 'Noura',
            'last_name' => 'Al-Otaibi',
            'email' => 'patient2@vmc.sa',
            'phone' => '+966500000005',
            'dob' => '2015-08-22',
            'gender' => 'female',
        ]);

        $guardian = $this->createUser([
            'first_name' => 'Fahad',
            'last_name' => 'Al-Otaibi',
            'email' => 'guardian@vmc.sa',
            'phone' => '+966500000006',
            'dob' => '1975-04-18',
            'gender' => 'male',
        ]);

        $receptionist = $this->createUser([
            'first_name' => 'Mona',
            'last_name' => 'Al-Qahtani',
            'email' => 'receptionist@vmc.sa',
            'phone' => '+966500000007',
            'dob' => '1990-09-12',
            'gender' => 'female',
        ]);

        $this->assignClinicRole($mainClinic->id, $admin->id, $adminRole->id);
        $this->assignClinicRole($mainClinic->id, $doctorAhmed->id, $doctorRole->id);
        $this->assignClinicRole($mainClinic->id, $doctorSara->id, $doctorRole->id);
        $this->assignClinicRole($mainClinic->id, $receptionist->id, $receptionistRole->id);

        $doctorAhmedRecord = Doctor::query()->firstOrCreate(
            ['user_id' => $doctorAhmed->id],
            [
                'department_id' => $cardiology->id,
                'license_number' => 'SA-DOC-10001',
                'experience_years' => 12,
                'avg_rating' => 4.75,
            ]
        );

        $doctorSaraRecord = Doctor::query()->firstOrCreate(
            ['user_id' => $doctorSara->id],
            [
                'department_id' => $pediatrics->id,
                'license_number' => 'SA-DOC-10002',
                'experience_years' => 8,
                'avg_rating' => 4.50,
            ]
        );

        DB::table('doctor_profiles')->updateOrInsert(
            ['doctor_id' => $doctorAhmedRecord->id],
            [
                'bio' => 'Consultant cardiologist specializing in preventive heart care.',
                'qualifications' => 'MBBS, Board Certified Cardiology',
                'consultation_fee' => 350.00,
                'photo_path' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('doctor_profiles')->updateOrInsert(
            ['doctor_id' => $doctorSaraRecord->id],
            [
                'bio' => 'Pediatrician focused on child wellness and development.',
                'qualifications' => 'MBBS, MD Pediatrics',
                'consultation_fee' => 300.00,
                'photo_path' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $patientKhalidRecord = Patient::query()->firstOrCreate(
            ['user_id' => $patientKhalid->id],
            ['blood_type' => 'O+']
        );

        $patientMinorRecord = Patient::query()->firstOrCreate(
            ['user_id' => $patientMinor->id],
            ['blood_type' => 'A+']
        );

        PatientRecord::query()->firstOrCreate(['patient_id' => $patientKhalidRecord->id]);
        PatientRecord::query()->firstOrCreate(['patient_id' => $patientMinorRecord->id]);

        Receptionist::query()->firstOrCreate(['user_id' => $receptionist->id]);

        DB::table('guardians')->updateOrInsert(
            [
                'patient_id' => $patientMinorRecord->id,
                'user_id' => $guardian->id,
            ],
            [
                'relation' => 'father',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Patient role is implicit via patient profile; stored for future RBAC checks.
        unset($patientRole);
    }

    private function createUser(array $attributes): User
    {
        return User::query()->firstOrCreate(
            ['email' => $attributes['email']],
            [
                ...$attributes,
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );
    }

    private function assignClinicRole(int $clinicId, int $userId, int $roleId): void
    {
        ClinicUser::query()->updateOrCreate(
            [
                'clinic_id' => $clinicId,
                'user_id' => $userId,
            ],
            [
                'role_id' => $roleId,
            ]
        );
    }
}
