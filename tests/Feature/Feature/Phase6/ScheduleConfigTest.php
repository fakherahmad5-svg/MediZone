<?php

namespace Tests\Feature\Phase6;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Role;
use App\Models\ScheduleConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;
    private string $token;
    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء role
        $role = Role::create(['name' => 'doctor']);

        // إنشاء clinic
        $this->clinic = Clinic::create([
            'name'   => 'Test Clinic',
            'status' => 'active',
        ]);

        // إنشاء user + doctor
        $this->doctor = User::create([
            'first_name'        => 'Test',
            'last_name'         => 'Doctor',
            'email'             => 'doctor@test.com',
            'password'          => bcrypt('password'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        Doctor::create([
            'user_id'             => $this->doctor->id,
            'verification_status' => 'verified',
        ]);

        // ربط الدكتور بالعيادة
        \App\Models\ClinicUser::create([
            'clinic_id' => $this->clinic->id,
            'user_id'   => $this->doctor->id,
            'role_id'   => $role->id,
        ]);

        $this->token = $this->doctor->createToken('test')->plainTextToken;
    }

    public function test_doctor_can_create_schedule_config(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/schedule/config', [
                'clinic_id'             => $this->clinic->id,
                'consultation_duration' => 30,
                'break_duration'        => 5,
                'max_patients'          => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.consultation_duration', 30);
    }

    public function test_doctor_can_get_schedule_configs(): void
    {
        ScheduleConfig::create([
            'doctor_id'             => Doctor::where('user_id', $this->doctor->id)->first()->id,
            'clinic_id'             => $this->clinic->id,
            'consultation_duration' => 30,
            'break_duration'        => 5,
            'is_active'             => true,
            'is_vacation_mode'      => false,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/schedule/config');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_doctor_can_toggle_vacation_mode(): void
    {
        $config = ScheduleConfig::create([
            'doctor_id'             => Doctor::where('user_id', $this->doctor->id)->first()->id,
            'clinic_id'             => $this->clinic->id,
            'consultation_duration' => 30,
            'break_duration'        => 5,
            'is_active'             => true,
            'is_vacation_mode'      => false,
        ]);

        $response = $this->withToken($this->token)
            ->patchJson("/api/v1/schedule/config/{$config->id}/vacation");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_vacation_mode', true);
    }

    public function test_unauthenticated_user_cannot_access_schedule(): void
    {
        $response = $this->getJson('/api/v1/schedule/config');
        $response->assertStatus(401);
    }
}    
