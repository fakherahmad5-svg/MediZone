<?php

namespace Tests\Feature\Phase1;

use Illuminate\Support\Facades\Schema;

class SchemaStructureTest extends Phase1TestCase
{
    public function test_all_49_tables_exist(): void
    {
        $expectedTables = [
            'clinics', 'roles', 'permissions', 'role_permissions', 'departments', 'drugs',
            'users', 'clinic_users', 'clinic_departments', 'patients', 'doctors', 'receptionists',
            'doctor_departments', 'doctor_profiles', 'patient_records',
            'patient_doctor_access', 'doctor_favorites',
            'medical_history', 'allergies', 'chronic_conditions', 'surgeries',
            'family_history', 'attachments',
            'schedule_configs', 'schedule_days', 'schedule_sessions',
            'doctor_time_slots', 'blocked_times',
            'appointments',
            'encounters', 'clinical_notes', 'diagnoses', 'prescriptions', 'prescription_items',
            'medications', 'consultations', 'consultation_messages',
            'medical_record_access_logs', 'invoices', 'payments',
            'doctor_reviews', 'doctor_reports', 'notification_templates',
            'notifications', 'audit_logs', 'clinic_logs',
        ];

        $this->assertCount(46, $expectedTables);

        foreach ($expectedTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "❌ Table [{$table}] does not exist — migration missing or not applied."
            );
        }
    }


    public function test_appointments_price_is_decimal(): void
    {
        $type = $this->getColumnType('appointments', 'price');

        $this->assertContains(
            $type,
            ['decimal', 'numeric'],
            "appointments.price should be decimal/numeric, got [{$type}]. " .
            "If 'string'/'varchar' → migrations القديمة مازالت مُطبَّقة."
        );
    }

    public function test_doctors_table_does_not_have_department_id(): void
    {
        $this->assertFalse(
            Schema::hasColumn('doctors', 'department_id'),
            '❌ doctors.department_id لا يزال موجوداً — ' .
            'يجب حذف migration القديم واستبداله بـ 2025_01_01_000011_create_doctors_table.php'
        );
    }

    public function test_doctor_departments_pivot_has_correct_columns(): void
    {
        foreach (['doctor_id', 'department_id', 'clinic_id', 'is_primary'] as $column) {
            $this->assertTrue(Schema::hasColumn('doctor_departments', $column));
        }
    }



    public function test_encounters_appointment_id_is_nullable(): void
    {
        $columns           = Schema::getColumns('encounters');
        $appointmentColumn = collect($columns)->firstWhere('name', 'appointment_id');

        $this->assertNotNull($appointmentColumn);
        $this->assertTrue($appointmentColumn['nullable']);
    }

    public function test_doctor_profiles_structure(): void
    {
        foreach (['doctor_id', 'biography', 'qualifications', 'consultation_fee', 'photo_path', 'languages'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('doctor_profiles', $column),
                "❌ doctor_profiles.{$column} مفقود — " .
                "يجب استبدال migration القديم بـ 2025_01_01_000014_create_doctor_profiles_table.php"
            );
        }
    }

    public function test_clinic_departments_pivot_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('clinic_departments', 'clinic_id'));
        $this->assertTrue(Schema::hasColumn('clinic_departments', 'department_id'));
    }

    public function test_clinics_has_geo_coordinates(): void
    {
        $this->assertTrue(Schema::hasColumn('clinics', 'latitude'));
        $this->assertTrue(Schema::hasColumn('clinics', 'longitude'));
    }

    public function test_doctors_has_cached_rating_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('doctors', 'avg_rating'));
        $this->assertTrue(Schema::hasColumn('doctors', 'reviews_count'));
    }


    public function test_soft_deletes_applied_to_correct_tables(): void
    {
        $shouldHaveSoftDeletes = ['users', 'patients', 'doctors', 'clinics', 'appointments', 'invoices'];

        foreach ($shouldHaveSoftDeletes as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'deleted_at'),
                "❌ {$table} مفقود منه deleted_at — استبدل migration القديم بنسخة المرحلة 1"
            );
        }

        $shouldNotHaveSoftDeletes = ['allergies', 'audit_logs', 'notifications'];

        foreach ($shouldNotHaveSoftDeletes as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'deleted_at'));
        }
    }

    public function test_enum_classes_are_loadable_and_return_values(): void
    {
        $this->assertEquals(
            ['active', 'inactive', 'banned'],
            \App\Core\Enums\UserStatus::values()
        );

        $this->assertEquals(
            ['scheduled', 'completed', 'cancelled', 'no_show'],
            \App\Core\Enums\AppointmentStatus::values()
        );

        $this->assertEquals(
            ['pending', 'approved', 'rejected', 'completed'],
            \App\Core\Enums\RefundStatus::values()
        );

        $this->assertTrue(\App\Core\Enums\AccessAction::CreatePrescription->requiresFullAccess());
        $this->assertFalse(\App\Core\Enums\AccessAction::ViewRecord->requiresFullAccess());
    }
}
