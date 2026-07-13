<?php

namespace Tests\Feature\Phase2;

use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;

/**
 * RegistrationTest — UC-C01
 *
 * يختبر تسجيل Patient / Doctor / Receptionist
 * والبيانات التي تُنشأ تلقائياً مع كل دور.
 */
class RegistrationTest extends Phase2TestCase
{
    // ─────────────────────────────────────────────
    //  Successful Registrations
    // ─────────────────────────────────────────────

    public function test_patient_can_register_successfully(): void
    {
        Event::fake([Registered::class]);

        $response = $this->postJson('/api/v1/auth/register', $this->validPatientData());

        $this->assertSuccessResponse($response, 201);

        $response->assertJsonStructure([
            'success', 'message',
            'data' => ['user' => ['id', 'email', 'role', 'email_verified']],
        ]);

        $response->assertJsonPath('data.user.role', 'patient');
        $response->assertJsonPath('data.user.email_verified', false);

        Event::assertDispatched(Registered::class);
    }

    public function test_doctor_can_register_successfully(): void
    {
        Event::fake([Registered::class]);

        $data     = $this->validDoctorData();
        $response = $this->postJson('/api/v1/auth/register', $data);

        $this->assertSuccessResponse($response, 201);

        // Doctor يبدأ بـ pending ولا يملك token فوري
        $response->assertJsonPath('data.requires_verification', true);
        $this->assertNull($response->json('data.token'));

        // التحقق من قاعدة البيانات
        $doctor = Doctor::whereHas(
            'user',
            fn ($q) => $q->where('email', $data['email'])
        )->first();

        $this->assertNotNull($doctor);
        $this->assertTrue($doctor->isPending());
        $this->assertDatabaseHas('doctor_profiles', ['doctor_id' => $doctor->id]);
    }

    public function test_receptionist_can_register_successfully(): void
    {
        Event::fake([Registered::class]);

        $response = $this->postJson('/api/v1/auth/register', $this->validReceptionistData());

        $this->assertSuccessResponse($response, 201);
        $response->assertJsonPath('data.user.role', 'receptionist');
    }

    // ─────────────────────────────────────────────
    //  Auto-Creation Checks
    // ─────────────────────────────────────────────

    public function test_patient_registration_creates_patient_record_and_medical_history(): void
    {
        Event::fake();

        $data     = $this->validPatientData();
        $response = $this->postJson('/api/v1/auth/register', $data);

        $this->assertSuccessResponse($response, 201);

        $patient = Patient::whereHas(
            'user',
            fn ($q) => $q->where('email', $data['email'])
        )->first();

        $this->assertNotNull($patient, 'Patient profile must be created.');
        $this->assertDatabaseHas('patient_records', ['patient_id' => $patient->id]);

        $record = $patient->patientRecord;
        $this->assertNotNull($record);
        $this->assertDatabaseHas('medical_history', ['patient_record_id' => $record->id]);
    }

    public function test_clinic_users_entry_is_created_on_registration(): void
    {
        Event::fake();

        $data     = $this->validPatientData();
        $response = $this->postJson('/api/v1/auth/register', $data);

        $userId = $response->json('data.user.id');

        $this->assertDatabaseHas('clinic_users', ['user_id' => $userId]);
    }

    public function test_patient_has_token_on_registration(): void
    {
        Event::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validPatientData());

        $this->assertSuccessResponse($response, 201);

        // Patient يملك token (لكن يحتاج email verification لبعض الـ routes)
        $this->assertNotNull($response->json('data.token'));
        $response->assertJsonPath('data.requires_verification', false);
    }

    // ─────────────────────────────────────────────
    //  Validation Failures
    // ─────────────────────────────────────────────

    public function test_registration_fails_with_duplicate_email(): void
    {
        Event::fake();

        $data = $this->validPatientData();
        $this->postJson('/api/v1/auth/register', $data);

        $response = $this->postJson('/api/v1/auth/register', array_merge($data, [
            'password'              => 'Password1',
            'password_confirmation' => 'Password1',
        ]));

        $this->assertValidationError($response, 'email');
    }

    public function test_doctor_registration_requires_license_number(): void
    {
        $data = $this->validDoctorData();
        unset($data['license_number']);

        $response = $this->postJson('/api/v1/auth/register', $data);

        $this->assertValidationError($response, 'license_number');
    }

    public function test_doctor_registration_requires_clinic_id(): void
    {
        $data = $this->validDoctorData();
        unset($data['clinic_id']);

        $response = $this->postJson('/api/v1/auth/register', $data);

        $this->assertValidationError($response, 'clinic_id');
    }

    public function test_doctor_registration_requires_department_id(): void
    {
        $data = $this->validDoctorData();
        unset($data['department_id']);

        $response = $this->postJson('/api/v1/auth/register', $data);

        $this->assertValidationError($response, 'department_id');
    }

    public function test_receptionist_registration_requires_clinic_id(): void
    {
        $data = $this->validReceptionistData();
        unset($data['clinic_id']);

        $response = $this->postJson('/api/v1/auth/register', $data);

        $this->assertValidationError($response, 'clinic_id');
    }

    public function test_registration_rejects_admin_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge(
            $this->validPatientData(),
            ['role' => 'admin']
        ));

        $this->assertValidationError($response, 'role');
    }

    public function test_registration_fails_with_weak_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge(
            $this->validPatientData(),
            ['password' => '123', 'password_confirmation' => '123']
        ));

        $this->assertValidationError($response, 'password');
    }

    public function test_registration_fails_when_password_confirmation_mismatch(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge(
            $this->validPatientData(),
            ['password' => 'Password1', 'password_confirmation' => 'Different1']
        ));

        $this->assertValidationError($response, 'password');
    }

    public function test_registration_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertArrayHasKey('role', $errors);
        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }
}
