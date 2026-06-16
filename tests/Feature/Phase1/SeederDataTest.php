<?php

namespace Tests\Feature\Phase1;

use App\Core\Enums\NotificationType;
use Illuminate\Support\Facades\DB;


class SeederDataTest extends Phase1TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }


    public function test_four_core_roles_seeded(): void
    {
        $roles = DB::table('roles')->pluck('name')->toArray();

        $this->assertCount(4, $roles);
        $this->assertEqualsCanonicalizing(
            ['admin', 'doctor', 'patient', 'receptionist'],
            $roles
        );
    }


    public function test_every_role_has_at_least_one_permission(): void
    {
        $roles = DB::table('roles')->get();

        foreach ($roles as $role) {
            $count = DB::table('role_permissions')->where('role_id', $role->id)->count();
            $this->assertGreaterThan(0, $count, "Role '{$role->name}' has no permissions assigned.");
        }
    }


    public function test_admin_role_has_no_medical_record_permission(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        $permissionNames = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $adminRoleId)
            ->pluck('permissions.name');

        foreach ($permissionNames as $name) {
            $this->assertStringNotContainsString(
                'medical_record',
                $name,
                "Admin role should not have direct medical record permissions (FR 2.4 compliance)."
            );
        }
    }


    public function test_twelve_departments_seeded(): void
    {
        $this->assertEquals(14, DB::table('departments')->count());

        $this->assertDatabaseHas('departments', ['name' => 'Cardiology']);
        $this->assertDatabaseHas('departments', ['name' => 'Pediatrics']);
    }


    public function test_fifteen_drugs_seeded(): void
    {
        $this->assertEquals(15, DB::table('drugs')->count());
        $this->assertDatabaseHas('drugs', ['name' => 'Paracetamol', 'form' => 'tablet']);
    }


    public function test_notification_template_exists_for_every_notification_type(): void
    {
        $expectedTypes = NotificationType::values();
        $seededTypes   = DB::table('notification_templates')->pluck('type')->toArray();

        $this->assertEqualsCanonicalizing($expectedTypes, $seededTypes);
    }


    public function test_notification_templates_have_complete_bilingual_content(): void
    {
        $templates = DB::table('notification_templates')->get();

        foreach ($templates as $template) {
            $this->assertNotEmpty($template->title_en, "Template [{$template->type}] missing title_en");
            $this->assertNotEmpty($template->title_ar, "Template [{$template->type}] missing title_ar");
            $this->assertNotEmpty($template->body_en, "Template [{$template->type}] missing body_en");
            $this->assertNotEmpty($template->body_ar, "Template [{$template->type}] missing body_ar");

            $channels = json_decode($template->channels, true);
            $this->assertIsArray($channels);
            $this->assertNotEmpty($channels, "Template [{$template->type}] has no channels.");
        }
    }


    public function test_default_clinic_seeded_with_all_departments_linked(): void
    {
        $this->assertEquals(1, DB::table('clinics')->count());

        $clinicId = DB::table('clinics')->value('id');
        $departmentsCount = DB::table('departments')->count();

        $linkedCount = DB::table('clinic_departments')
            ->where('clinic_id', $clinicId)
            ->count();

        $this->assertEquals(
            $departmentsCount,
            $linkedCount,
            'All departments should be linked to the default clinic.'
        );
    }


    public function test_super_admin_created_correctly(): void
    {
        $admin = DB::table('users')->where('email', 'admin@vmc.test')->first();
        $this->assertNotNull($admin, 'Admin user was not seeded.');

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        $clinicUserRow = DB::table('clinic_users')
            ->where('user_id', $admin->id)
            ->first();

        $this->assertNotNull($clinicUserRow, 'clinic_users row for admin not found.');
        $this->assertNull($clinicUserRow->clinic_id, 'Super Admin clinic_id must be NULL.');
        $this->assertEquals($adminRoleId, $clinicUserRow->role_id);
    }

    public function test_admin_password_is_hashed(): void
    {
        $admin = DB::table('users')->where('email', 'admin@vmc.test')->first();

        $this->assertNotEquals('Admin@12345', $admin->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Admin@12345', $admin->password));
    }
}
