<?php

namespace Tests\Feature\Phase8;

use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicUser;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PaymentController is authorization-driven ($this->authorize() +
 * $request->user() throughout), unlike StripeWebhookController which
 * has no auth dependency at all. That means it can only be exercised
 * correctly through a real HTTP request that goes through the router
 * and the auth:sanctum middleware — a direct app(PaymentController::class)
 * call leaves $request->user() null and every authorize() call throws.
 * Hence getJson() + actingAs($user, 'sanctum') here, unlike the
 * direct-call pattern used elsewhere in this project's tests.
 */
class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────
    //  Role builders — Role::firstOrCreate() + ClinicUser::create()
    //  is what User::hasRole()/isSuperAdmin() actually check against.
    // ─────────────────────────────────────────────────────────

    private function makePatientUser(?Clinic $clinic = null): array
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['user_id' => $user->id]);

        $role = Role::firstOrCreate(['name' => 'patient']);
        $this->grantPermission($role, 'invoices.view');

        ClinicUser::create([
            'clinic_id' => $clinic?->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        return [$user->fresh(), $patient];
    }

    private function makeDoctorUser(Clinic $clinic): array
    {
        $user = User::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $user->id]);

        $role = Role::firstOrCreate(['name' => 'doctor']);
        $this->grantPermission($role, 'invoices.view');

        ClinicUser::create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        return [$user->fresh(), $doctor];
    }

    private function makeReceptionistUser(Clinic $clinic): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'receptionist']);
        $this->grantPermission($role, 'payments.manage');

        ClinicUser::create([
            'clinic_id' => $clinic->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        return $user->fresh();
    }

    private function makeSuperAdminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin']);

        // isSuperAdmin() specifically checks clinic_id IS NULL.
        ClinicUser::create([
            'clinic_id' => null,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        return $user->fresh();
    }


    private function grantPermission(Role $role, string $permission): void
    {
        $perm = Permission::firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    private function makePayment(Appointment $appointment, array $overrides = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
            'clinic_id' => $appointment->clinic_id,
            'amount' => '100.00',
            'method' => PaymentMethod::Card,
            'status' => PaymentStatus::Paid,
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────
    //  index() — patient scope
    // ─────────────────────────────────────────────────────────

    public function test_patient_index_only_returns_their_own_payments(): void
    {
        $clinic = Clinic::factory()->create();
        [$patientUser, $patient] = $this->makePatientUser($clinic);

        $ownAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
        ]);
        $ownPayment = $this->makePayment($ownAppointment);

        [, $otherPatient] = $this->makePatientUser($clinic);
        $otherAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $otherPatient->id,
        ]);
        $this->makePayment($otherAppointment);

        $response = $this->actingAs($patientUser, 'sanctum')
            ->getJson('/api/v1/payments');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownPayment->id);
    }

    // ─────────────────────────────────────────────────────────
    //  index() — doctor scope
    // ─────────────────────────────────────────────────────────

    public function test_doctor_index_only_returns_payments_for_their_appointments(): void
    {
        $clinic = Clinic::factory()->create();
        [$doctorUser, $doctor] = $this->makeDoctorUser($clinic);

        $ownAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
        ]);
        $ownPayment = $this->makePayment($ownAppointment);

        [, $otherDoctor] = $this->makeDoctorUser($clinic);
        $otherAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $otherDoctor->id,
        ]);
        $this->makePayment($otherAppointment);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->getJson('/api/v1/payments');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownPayment->id);
    }

    // ─────────────────────────────────────────────────────────
    //  index() — clinic staff scope (receptionist)
    // ─────────────────────────────────────────────────────────

    public function test_receptionist_index_only_returns_payments_for_their_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $otherClinic = Clinic::factory()->create();
        $receptionistUser = $this->makeReceptionistUser($clinic);

        $ownClinicAppointment = Appointment::factory()->create(['clinic_id' => $clinic->id]);
        $ownClinicPayment = $this->makePayment($ownClinicAppointment);

        $otherClinicAppointment = Appointment::factory()->create(['clinic_id' => $otherClinic->id]);
        $this->makePayment($otherClinicAppointment);

        $response = $this->actingAs($receptionistUser, 'sanctum')
            ->getJson('/api/v1/payments');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownClinicPayment->id);
    }

    // ─────────────────────────────────────────────────────────
    //  index() — super admin sees everything, unscoped
    // ─────────────────────────────────────────────────────────

    public function test_super_admin_index_returns_payments_across_all_clinics(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->makeSuperAdminUser();

        $this->makePayment(Appointment::factory()->create(['clinic_id' => $clinicA->id]));
        $this->makePayment(Appointment::factory()->create(['clinic_id' => $clinicB->id]));

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/payments');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    // ─────────────────────────────────────────────────────────
    //  show() — ownership enforcement
    // ─────────────────────────────────────────────────────────

    public function test_patient_can_view_their_own_payment(): void
    {
        $clinic = Clinic::factory()->create();
        [$patientUser, $patient] = $this->makePatientUser($clinic);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
        ]);
        $payment = $this->makePayment($appointment);

        $response = $this->actingAs($patientUser, 'sanctum')
            ->getJson("/api/v1/payments/{$payment->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $payment->id);
    }

    public function test_patient_cannot_view_another_patients_payment(): void
    {
        $clinic = Clinic::factory()->create();
        [$patientUser] = $this->makePatientUser($clinic);

        [, $otherPatient] = $this->makePatientUser($clinic);
        $otherAppointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $otherPatient->id,
        ]);
        $otherPayment = $this->makePayment($otherAppointment);

        // findForUser() scopes by ownership at the query level, so an
        // out-of-scope id surfaces as a 404 — same defense-in-depth
        // pattern AppointmentQueryService uses (existence of another
        // patient's record is not revealed).
        $response = $this->actingAs($patientUser, 'sanctum')
            ->getJson("/api/v1/payments/{$otherPayment->id}");

        $response->assertNotFound();
    }

    // ─────────────────────────────────────────────────────────
    //  Resource shape — Stripe internals hidden from patients
    // ─────────────────────────────────────────────────────────

    public function test_patient_response_hides_stripe_internal_ids(): void
    {
        $clinic = Clinic::factory()->create();
        [$patientUser, $patient] = $this->makePatientUser($clinic);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
        ]);
        $payment = $this->makePayment($appointment, [
            'stripe_payment_intent_id' => 'pi_should_be_hidden',
        ]);

        $response = $this->actingAs($patientUser, 'sanctum')
            ->getJson("/api/v1/payments/{$payment->id}");

        $response->assertOk();
        $response->assertJsonMissingPath('data.stripe_payment_intent_id');
    }

    public function test_doctor_response_includes_stripe_internal_ids(): void
    {
        $clinic = Clinic::factory()->create();
        [$doctorUser, $doctor] = $this->makeDoctorUser($clinic);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
        ]);
        $payment = $this->makePayment($appointment, [
            'stripe_payment_intent_id' => 'pi_visible_to_staff',
        ]);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->getJson("/api/v1/payments/{$payment->id}");

        $response->assertOk();
        $response->assertJsonPath('data.stripe_payment_intent_id', 'pi_visible_to_staff');
    }

    // ─────────────────────────────────────────────────────────
    //  Unauthenticated
    // ─────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/payments');

        $response->assertUnauthorized();
    }
}