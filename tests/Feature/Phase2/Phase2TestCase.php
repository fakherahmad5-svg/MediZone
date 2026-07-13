<?php

namespace Tests\Feature\Phase2;

use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Enums\UserStatus;
use App\Models\ClinicUser;
use App\Models\Doctor;
use App\Models\DoctorProfile;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Receptionist;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase2TestCase — Self-Contained Base
 *
 * مكان الملف: tests/Feature/Phase2/Phase2TestCase.php
 * الحالة: REPLACE النسخة السابقة كلياً
 *
 * سبب التغيير:
 *   النسخة السابقة كانت تستدعي:
 *     $this->seed(\Database\Seeders\RolePermissionSeeder::class)
 *   مما أدى لـ: "Target class does not exist" — 68 test فشلت
 *
 * الحل: إنشاء كل البيانات الضرورية مباشرة في setUp()
 * بدون الاعتماد على أي Seeder class خارجي.
 *
 * يرث من TestCase (Phase 0) الذي يحتوي على:
 *   assertSuccessResponse(), assertErrorResponse(),
 *   assertPaginatedResponse(), assertValidationError()
 *
 * [NEW] makeAdmin() helper مفقود من النسخة السابقة
 */
abstract class Phase2TestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Phase 1 Lesson: SQLite يحتاج هذا لتطبيق FK constraints
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->createRolesAndPermissions();
        $this->createDefaultClinic();
    }

    // ─────────────────────────────────────────────
    //  Self-Contained Data Setup
    // ─────────────────────────────────────────────

    /**
     * إنشاء الأدوار والصلاحيات مباشرة — بديل عن RolePermissionSeeder
     * البيانات مطابقة لـ Phase 1 RolePermissionSeeder
     */
    private function createRolesAndPermissions(): void
    {
        // ── Roles ──
        foreach (['admin', 'doctor', 'patient', 'receptionist'] as $name) {
            DB::table('roles')->insertOrIgnore([
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Permissions (مطابق لـ Phase 1 RolePermissionSeeder) ──
        $rolePermissions = [
            'admin' => [
                'manage_doctors', 'manage_clinics', 'manage_departments',
                'manage_users', 'view_audit_logs', 'view_analytics',
                'manage_reports', 'process_refunds',
            ],
            'doctor' => [
                'manage_own_schedule', 'manage_own_profile',
                'manage_appointments', 'create_clinical_data',
                'view_authorized_records',
            ],
            'patient' => [
                'manage_own_medical_history', 'view_own_records',
                'manage_own_access_grants', 'book_appointments',
                'rate_and_report_doctors', 'make_payments',
            ],
            'receptionist' => [
                'book_for_patients', 'manage_doctor_slots',
                'view_clinic_appointments',
            ],
        ];

        $allPermissions = array_unique(array_merge(...array_values($rolePermissions)));

        foreach ($allPermissions as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($rolePermissions as $roleName => $permissions) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            foreach ($permissions as $permName) {
                $permId = DB::table('permissions')->where('name', $permName)->value('id');
                if ($roleId && $permId) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id'       => $roleId,
                        'permission_id' => $permId,
                    ]);
                }
            }
        }
    }

    /**
     * إنشاء عيادة افتراضية — بديل عن ClinicSeeder
     */
    private function createDefaultClinic(): void
    {
        if (DB::table('clinics')->doesntExist()) {
            DB::table('clinics')->insert([
                'name'       => 'Test Clinic',
                'email'      => 'clinic@test.local',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ─────────────────────────────────────────────
    //  User Factory Helpers
    // ─────────────────────────────────────────────

    protected function makeUser(array $overrides = []): User
    {
        static $counter = 0;
        $counter++;

        return User::create(array_merge([
            'first_name'        => 'Test',
            'last_name'         => "User{$counter}",
            'email'             => "user{$counter}@test.local",
            'email_verified_at' => now(),
            'password'          => 'Password1',
            'status'            => UserStatus::Active->value,
        ], $overrides));
    }

    protected function makePatient(array $userOverrides = []): User
    {
        $user = $this->makeUser($userOverrides);

        $patient = Patient::create([
            'user_id'    => $user->id,
            'blood_type' => 'O+',
        ]);

        $record = PatientRecord::create(['patient_id' => $patient->id]);
        MedicalHistory::create(['patient_record_id' => $record->id]);

        $role = Role::where('name', 'patient')->firstOrFail();
        ClinicUser::create([
            'user_id'   => $user->id,
            'role_id'   => $role->id,
            'clinic_id' => null,
        ]);

        return $user->load('clinicUsers.role');
    }

    protected function makeDoctor(array $userOverrides = [], bool $verified = true): User
    {
        static $licenseCounter = 0;
        $licenseCounter++;

        $user = $this->makeUser($userOverrides);

        $doctor = Doctor::create([
            'user_id'             => $user->id,
            'license_number'      => "LIC-{$licenseCounter}",
            'experience_years'    => 5,
            'verification_status' => $verified
                ? DoctorVerificationStatus::Verified->value
                : DoctorVerificationStatus::Pending->value,
        ]);

        DoctorProfile::create(['doctor_id' => $doctor->id]);

        $role = Role::where('name', 'doctor')->firstOrFail();
        ClinicUser::create([
            'user_id'   => $user->id,
            'role_id'   => $role->id,
            'clinic_id' => null,
        ]);

        return $user->load('clinicUsers.role');
    }

    protected function makeReceptionist(): User
    {
        $user     = $this->makeUser();
        $clinicId = DB::table('clinics')->value('id');

        Receptionist::create(['user_id' => $user->id]);

        $role = Role::where('name', 'receptionist')->firstOrFail();
        ClinicUser::create([
            'user_id'   => $user->id,
            'role_id'   => $role->id,
            'clinic_id' => $clinicId,
        ]);

        return $user->load('clinicUsers.role');
    }

    /**
     * [NEW] makeAdmin — مفقود من النسخة السابقة
     * Super Admin: clinic_id = null (مطابق لـ Phase 1 AdminUserSeeder)
     */
    protected function makeAdmin(): User
    {
        $user = $this->makeUser();

        $role = Role::where('name', 'admin')->firstOrFail();
        ClinicUser::create([
            'user_id'   => $user->id,
            'role_id'   => $role->id,
            'clinic_id' => null,
        ]);

        return $user->load('clinicUsers.role');
    }

    // ─────────────────────────────────────────────
    //  Registration Data Helpers
    // ─────────────────────────────────────────────

    protected function validPatientData(array $overrides = []): array
    {
        static $c = 0;
        $c++;

        return array_merge([
            'role'                  => 'patient',
            'first_name'            => 'Ahmed',
            'last_name'             => 'Al-Sayed',
            'email'                 => "patient{$c}@test.local",
            'password'              => 'Password1',
            'password_confirmation' => 'Password1',
        ], $overrides);
    }

    protected function validDoctorData(array $overrides = []): array
    {
        static $c = 0;
        $c++;

        $clinicId = DB::table('clinics')->value('id');
        $deptId   = DB::table('departments')->value('id')
            ?? $this->createTestDepartment();

        return array_merge([
            'role'                  => 'doctor',
            'first_name'            => 'Dr. Sara',
            'last_name'             => 'Hassan',
            'email'                 => "doctor{$c}@test.local",
            'password'              => 'Password1',
            'password_confirmation' => 'Password1',
            'license_number'        => "LIC-{$c}-" . rand(1000, 9999),
            'experience_years'      => 7,
            'clinic_id'             => $clinicId,
            'department_id'         => $deptId,
        ], $overrides);
    }

    protected function validReceptionistData(array $overrides = []): array
    {
        static $c = 0;
        $c++;

        return array_merge([
            'role'                  => 'receptionist',
            'first_name'            => 'Lina',
            'last_name'             => 'Omar',
            'email'                 => "receptionist{$c}@test.local",
            'password'              => 'Password1',
            'password_confirmation' => 'Password1',
            'clinic_id'             => DB::table('clinics')->value('id'),
        ], $overrides);
    }

    /**
     * إنشاء قسم اختباري إذا لم يكن موجوداً
     */
    private function createTestDepartment(): int
    {
        return DB::table('departments')->insertGetId([
            'name'       => 'General Medicine',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
