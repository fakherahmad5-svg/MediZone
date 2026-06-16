<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\UserStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class EnumConstraintTest extends Phase1TestCase
{
    /**
     * ✅ قيمة enum صحيحة (users.status = 'active') تُقبَل
     */
    public function test_valid_enum_value_is_accepted(): void
    {
        $userId = DB::table('users')->insertGetId([
            'first_name' => 'Valid',
            'last_name'  => 'User',
            'email'      => 'valid@test.local',
            'password'   => Hash::make('password'),
            'status'     => UserStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('users', [
            'id'     => $userId,
            'status' => 'active',
        ]);
    }


    public function test_invalid_user_status_enum_value_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'first_name' => 'Invalid',
            'last_name'  => 'User',
            'email'      => 'invalid@test.local',
            'password'   => Hash::make('password'),
            'status'     => 'this_value_does_not_exist', // ❌ غير موجود في UserStatus
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    public function test_invalid_appointment_status_is_rejected(): void
    {
        $clinicId = $this->createClinic();
        $patient  = $this->createPatient();
        $doctor   = $this->createDoctor();
        $admin    = $this->createUser();

        $this->expectException(QueryException::class);

        DB::table('appointments')->insert([
            'clinic_id'      => $clinicId,
            'patient_id'     => $patient['patient_id'],
            'doctor_id'      => $doctor['doctor_id'],
            'status'         => 'in_progress', // ❌ غير موجود في AppointmentStatus
            'encounter_type' => 'chat',
            'price'          => 50,
            'created_by'     => $admin,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }


    public function test_appointment_status_default_value(): void
    {
        $clinicId = $this->createClinic();
        $patient  = $this->createPatient();
        $doctor   = $this->createDoctor();
        $admin    = $this->createUser();

        $appointmentId = DB::table('appointments')->insertGetId([
            'clinic_id'      => $clinicId,
            'patient_id'     => $patient['patient_id'],
            'doctor_id'      => $doctor['doctor_id'],
            // status غير مُمرَّر عمداً → يجب أن يأخذ default
            'encounter_type' => 'chat',
            'price'          => 50,
            'created_by'     => $admin,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->assertDatabaseHas('appointments', [
            'id'     => $appointmentId,
            'status' => AppointmentStatus::Scheduled->value,
        ]);
    }


    public function test_invalid_slot_status_is_rejected(): void
    {
        $clinicId = $this->createClinic();
        $doctor   = $this->createDoctor();

        $this->expectException(QueryException::class);

        DB::table('doctor_time_slots')->insert([
            'clinic_id'  => $clinicId,
            'doctor_id'  => $doctor['doctor_id'],
            'starts_at'  => now()->addDay(),
            'ends_at'    => now()->addDay()->addMinutes(30),
            'status'     => 'reserved', // ❌ غير موجود في SlotStatus (available/booked/blocked)
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
