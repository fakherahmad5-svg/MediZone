<?php

namespace Tests\Feature\Phase1;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RelationshipIntegrityTest extends Phase1TestCase
{

    public function test_deleting_patient_cascades_through_medical_record_chain(): void
    {
        $patientRecord = $this->createPatientRecord();

        $medicalHistoryId = DB::table('medical_history')->insertGetId([
            'patient_record_id' => $patientRecord['patient_record_id'],
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $allergyId = DB::table('allergies')->insertGetId([
            'medical_history_id' => $medicalHistoryId,
            'allergen'           => 'Penicillin',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $this->assertDatabaseHas('allergies', ['id' => $allergyId]);

        // حذف patient (hard delete لأنه DB::table — لكن cascade يُطبَّق على FK)
        DB::table('patients')->where('id', $patientRecord['patient_id'])->delete();

        $this->assertDatabaseMissing('patient_records', ['id' => $patientRecord['patient_record_id']]);
        $this->assertDatabaseMissing('medical_history', ['id' => $medicalHistoryId]);
        $this->assertDatabaseMissing('allergies', ['id' => $allergyId]);
    }


    public function test_deleting_appointment_nullifies_encounter_reference_not_deletes_it(): void
    {
        $clinicId = $this->createClinic();
        $patient  = $this->createPatientRecord();
        $doctor   = $this->createDoctor();
        $admin    = $this->createUser();

        $appointmentId = $this->createAppointment(
            $clinicId, $patient['patient_id'], $doctor['doctor_id'], $admin
        );

        $encounterId = DB::table('encounters')->insertGetId([
            'appointment_id'    => $appointmentId,
            'patient_record_id' => $patient['patient_record_id'],
            'visit_type'        => 'scheduled',
            'created_by'        => $admin,
            'created_at'        => now(),
        ]);

        DB::table('appointments')->where('id', $appointmentId)->delete();

        $this->assertDatabaseHas('encounters', ['id' => $encounterId]);

        $encounter = DB::table('encounters')->find($encounterId);
        $this->assertNull($encounter->appointment_id);
    }


    public function test_soft_delete_column_exists_and_accepts_timestamp(): void
    {
        $userId = $this->createUser();


        $this->assertDatabaseHas('users', ['id' => $userId]);


        DB::table('users')->where('id', $userId)->update([
            'deleted_at' => now(),
        ]);

        $this->assertDatabaseHas('users', ['id' => $userId]);


        $activeUser = DB::table('users')
            ->whereNull('deleted_at')
            ->where('id', $userId)
            ->first();

        $this->assertNull(
            $activeUser,
            'Soft-deleted user should not appear in whereNull(deleted_at) queries.'
        );
    }


    public function test_doctor_favorites_unique_constraint(): void
    {
        $patient = $this->createPatient();
        $doctor  = $this->createDoctor();

        DB::table('doctor_favorites')->insert([
            'patient_id' => $patient['patient_id'],
            'doctor_id'  => $doctor['doctor_id'],
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('doctor_favorites')->insert([
            'patient_id' => $patient['patient_id'],
            'doctor_id'  => $doctor['doctor_id'],
            'created_at' => now(),
        ]);
    }


    public function test_doctor_time_slot_uniqueness_prevents_duplicate_slots(): void
    {
        $clinicId = $this->createClinic();
        $doctor   = $this->createDoctor();


        $sessionId = $this->createSessionForDoctor($clinicId, $doctor['doctor_id']);

        $startsAt = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s');

        DB::table('doctor_time_slots')->insert([
            'clinic_id'  => $clinicId,
            'doctor_id'  => $doctor['doctor_id'],
            'session_id' => $sessionId,
            'starts_at'  => $startsAt,
            'ends_at'    => now()->addDay()->setTime(10, 30)->format('Y-m-d H:i:s'),
            'status'     => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('doctor_time_slots')->insert([
            'clinic_id'  => $clinicId,
            'doctor_id'  => $doctor['doctor_id'],
            'session_id' => $sessionId,
            'starts_at'  => $startsAt, // نفس الوقت = violation
            'ends_at'    => now()->addDay()->setTime(10, 30)->format('Y-m-d H:i:s'),
            'status'     => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    public function test_patient_doctor_access_security_query_returns_correct_result(): void
    {
        $patient = $this->createPatientRecord();
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor();

        $this->createAccess($patient['patient_id'], $doctorA['doctor_id'], $patient['user_id']);

        $hasAccessA = DB::table('patient_doctor_access')
            ->where('patient_id', $patient['patient_id'])
            ->where('doctor_id', $doctorA['doctor_id'])
            ->where('status', 'active')
            ->exists();

        $hasAccessB = DB::table('patient_doctor_access')
            ->where('patient_id', $patient['patient_id'])
            ->where('doctor_id', $doctorB['doctor_id'])
            ->where('status', 'active')
            ->exists();

        $this->assertTrue($hasAccessA);
        $this->assertFalse($hasAccessB);
    }

    public function test_doctor_review_appointment_uniqueness(): void
    {
        $clinicId = $this->createClinic();
        $patient  = $this->createPatient();
        $doctor   = $this->createDoctor();
        $admin    = $this->createUser();

        $appointmentId = $this->createAppointment(
            $clinicId,
            $patient['patient_id'],
            $doctor['doctor_id'],
            $admin,
            ['status' => 'completed']
        );

        DB::table('doctor_reviews')->insert([
            'clinic_id'      => $clinicId,
            'patient_id'     => $patient['patient_id'],
            'doctor_id'      => $doctor['doctor_id'],
            'appointment_id' => $appointmentId,
            'rating'         => 5,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('doctor_reviews')->insert([
            'clinic_id'      => $clinicId,
            'patient_id'     => $patient['patient_id'],
            'doctor_id'      => $doctor['doctor_id'],
            'appointment_id' => $appointmentId,
            'rating'         => 3,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    // ─────────────────────────────────────────────
    //  Private Helper
    // ─────────────────────────────────────────────

    private function createSessionForDoctor(int $clinicId, int $doctorId): int
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
            'day_of_week'        => 1,
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
}
