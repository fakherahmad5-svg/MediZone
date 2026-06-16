<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\AccessStatus;
use App\Core\Enums\AccessType;
use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\ConsultationType;
use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Enums\Gender;
use App\Core\Enums\SlotStatus;
use App\Core\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

abstract class Phase1TestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();


        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    // ─────────────────────────────────────────────
    //  Helper Methods
    // ─────────────────────────────────────────────

    protected function createClinic(array $overrides = []): int
    {
        return DB::table('clinics')->insertGetId(array_merge([
            'name'       => 'Test Clinic',
            'phone'      => '+963-11-1234567',
            'email'      => 'clinic@test.local',
            'address'    => 'Damascus',
            'latitude'   => 33.5138,
            'longitude'  => 36.2765,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function createUser(array $overrides = []): int
    {
        static $counter = 0;
        $counter++;

        return DB::table('users')->insertGetId(array_merge([
            'first_name' => 'Test',
            'last_name'  => "User{$counter}",
            'email'      => "user{$counter}@test.local",
            'password'   => Hash::make('password'),
            'phone'      => "+9631100{$counter}",
            'gender'     => Gender::Male->value,
            'status'     => UserStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    protected function createPatient(array $userOverrides = [], array $patientOverrides = []): array
    {
        $userId = $this->createUser($userOverrides);

        $patientId = DB::table('patients')->insertGetId(array_merge([
            'user_id'    => $userId,
            'blood_type' => 'O+',
            'created_at' => now(),
            'updated_at' => now(),
        ], $patientOverrides));

        return ['user_id' => $userId, 'patient_id' => $patientId];
    }

    protected function createDoctor(array $userOverrides = [], array $doctorOverrides = []): array
    {
        static $licenseCounter = 0;
        $licenseCounter++;

        $userId = $this->createUser($userOverrides);

        $doctorId = DB::table('doctors')->insertGetId(array_merge([
            'user_id'             => $userId,
            'license_number'      => "LIC-{$licenseCounter}",
            'experience_years'    => 5,
            'verification_status' => DoctorVerificationStatus::Verified->value,
            'avg_rating'          => 0,
            'reviews_count'       => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ], $doctorOverrides));

        return ['user_id' => $userId, 'doctor_id' => $doctorId];
    }

    protected function createPatientRecord(array $userOverrides = []): array
    {
        $patient = $this->createPatient($userOverrides);

        $recordId = DB::table('patient_records')->insertGetId([
            'patient_id' => $patient['patient_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return array_merge($patient, ['patient_record_id' => $recordId]);
    }

    protected function createAccess(int $patientId, int $doctorId, int $grantedBy, array $overrides = []): int
    {
        return DB::table('patient_doctor_access')->insertGetId(array_merge([
            'patient_id'  => $patientId,
            'doctor_id'   => $doctorId,
            'access_type' => AccessType::Full->value,
            'status'      => AccessStatus::Active->value,
            'granted_at'  => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));
    }


    protected function createAppointment(
        int $clinicId,
        int $patientId,
        int $doctorId,
        int $createdBy,
        array $overrides = []
    ): int {

        $slotId = DB::table('doctor_time_slots')->insertGetId([
            'clinic_id'  => $clinicId,
            'doctor_id'  => $doctorId,
            'session_id' => $this->getOrCreateSession($clinicId, $doctorId),
            'starts_at'  => now()->addDays(rand(1, 30))->setTime(9, 0),
            'ends_at'    => now()->addDays(rand(1, 30))->setTime(9, 30),
            'status'     => SlotStatus::Available->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('appointments')->insertGetId(array_merge([
            'clinic_id'      => $clinicId,
            'patient_id'     => $patientId,
            'doctor_id'      => $doctorId,
            'slot_id'        => $slotId,
            'status'         => AppointmentStatus::Scheduled->value,
            'encounter_type' => ConsultationType::Chat->value,
            'price'          => 50.00,
            'created_by'     => $createdBy,
            'created_at'     => now(),
            'updated_at'     => now(),
        ], $overrides));
    }


    private function getOrCreateSession(int $clinicId, int $doctorId): int
    {
        $configId = DB::table('schedule_configs')->insertGetId([
            'clinic_id'             => $clinicId,
            'doctor_id'             => $doctorId,
            'consultation_duration' => 30,
            'break_duration'        => 0,
            'is_active'             => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $dayId = DB::table('schedule_days')->insertGetId([
            'schedule_config_id' => $configId,
            'day_of_week'        => 0,
            'is_active'          => true,
        ]);

        return DB::table('schedule_sessions')->insertGetId([
            'schedule_day_id' => $dayId,
            'session_type'    => 'morning',
            'start_time'      => '09:00:00',
            'end_time'        => '13:00:00',
            'is_active'       => true,
        ]);
    }

    protected function getColumnType(string $table, string $column): string
    {
        return DB::getSchemaBuilder()->getColumnType($table, $column);
    }
}
