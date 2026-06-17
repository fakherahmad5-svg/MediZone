<?php

namespace Tests\Feature\Auth;

use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Enums\UserStatus;
use App\Models\ClinicUser;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Receptionist;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClinicSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->seed(RoleSeeder::class);
        $this->seed(DepartmentSeeder::class);
        $this->seed(ClinicSeeder::class);
    }

    public function test_patient_can_register_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'role' => 'patient',
            'first_name' => 'Ali',
            'last_name' => 'Hassan',
            'email' => 'ali.patient@test.local',
            'phone' => '+963111111111',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'dob' => '1995-05-10',
            'gender' => 'male',
            'blood_type' => 'A+',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'patient')
            ->assertJsonPath('data.dashboard', 'patient_dashboard')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['permissions', 'profile'],
                ],
            ]);

        $this->assertDatabaseHas('patients', [
            'user_id' => User::query()->where('email', 'ali.patient@test.local')->value('id'),
        ]);
    }

    public function test_doctor_registration_is_pending_without_token(): void
    {
        $clinicId = DB::table('clinics')->value('id');
        $departmentId = DB::table('departments')->value('id');

        $response = $this->postJson('/api/v1/auth/register', [
            'role' => 'doctor',
            'first_name' => 'Sami',
            'last_name' => 'Doctor',
            'email' => 'sami.doctor@test.local',
            'phone' => '+963122222222',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'clinic_id' => $clinicId,
            'department_id' => $departmentId,
            'license_number' => 'DOC-99999',
            'experience_years' => 7,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.requires_verification', true)
            ->assertJsonMissingPath('data.token')
            ->assertJsonPath('data.user.profile.verification_status', DoctorVerificationStatus::Pending->value);
    }

    public function test_admin_can_login_and_get_admin_dashboard(): void
    {
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');

        $admin = User::factory()->create([
            'email' => 'admin.auth@test.local',
            'password' => Hash::make('Password123'),
            'status' => UserStatus::Active->value,
        ]);

        ClinicUser::query()->create([
            'clinic_id' => null,
            'user_id' => $admin->id,
            'role_id' => $adminRoleId,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin.auth@test.local',
            'password' => 'Password123',
            'device_name' => 'Postman',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.dashboard', 'admin_dashboard')
            ->assertJsonStructure(['data' => ['token', 'user' => ['permissions']]]);
    }

    public function test_receptionist_can_login_with_receptionist_dashboard(): void
    {
        $clinicId = DB::table('clinics')->value('id');
        $roleId = Role::query()->where('name', 'receptionist')->value('id');

        $user = User::factory()->create([
            'email' => 'receptionist.auth@test.local',
            'password' => Hash::make('Password123'),
            'status' => UserStatus::Active->value,
        ]);

        Receptionist::query()->create(['user_id' => $user->id]);

        ClinicUser::query()->create([
            'clinic_id' => $clinicId,
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'receptionist.auth@test.local',
            'password' => 'Password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.role', 'receptionist')
            ->assertJsonPath('data.dashboard', 'receptionist_dashboard');
    }

    public function test_pending_doctor_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'pending.doctor@test.local',
            'password' => Hash::make('Password123'),
            'status' => UserStatus::Active->value,
        ]);

        Doctor::query()->create([
            'user_id' => $user->id,
            'license_number' => 'DOC-PENDING',
            'experience_years' => 3,
            'verification_status' => DoctorVerificationStatus::Pending->value,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'pending.doctor@test.local',
            'password' => 'Password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_access_me_and_logout(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active->value,
        ]);

        Patient::query()->create([
            'user_id' => $user->id,
            'blood_type' => 'O+',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $meResponse = $this->withToken($token)->getJson('/api/v1/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('data.user.role', 'patient')
            ->assertJsonPath('data.dashboard', 'patient_dashboard');

        $logoutResponse = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNull(\Laravel\Sanctum\PersonalAccessToken::findToken($token));
    }

    public function test_user_can_reset_password_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset.user@test.local',
            'password' => Hash::make('OldPassword123'),
            'status' => UserStatus::Active->value,
        ]);

        Patient::query()->create(['user_id' => $user->id]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset.user@test.local',
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset.user@test.local',
            'password' => 'NewPassword123',
        ])->assertOk();
    }
}
