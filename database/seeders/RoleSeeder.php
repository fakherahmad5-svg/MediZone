<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'doctor', 'patient', 'receptionist'];

        foreach ($roles as $role) {
            Role::query()->firstOrCreate(['name' => $role]);
        }

        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.suspend',
            'clinics.view',
            'clinics.manage',
            'departments.view',
            'departments.manage',
            'doctors.view',
            'doctors.verify',
            'doctors.manage',
            'patients.view',
            'patients.manage',
            'appointments.view',
            'appointments.create',
            'appointments.cancel',
            'appointments.manage',
            'records.view_own',
            'records.view_granted',
            'records.manage',
            'access.grant',
            'access.revoke',
            'schedules.manage',
            'consultations.join',
            'prescriptions.create',
            'invoices.view',
            'payments.manage',
            'notifications.view',
            'reports.view',
            'reports.manage',
            'audit.view',
            'admin.dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission]);
        }

        $rolePermissions = [
            'admin' => $permissions,
            'doctor' => [
                'doctors.view',
                'patients.view',
                'appointments.view',
                'appointments.manage',
                'records.view_granted',
                'records.manage',
                'schedules.manage',
                'consultations.join',
                'prescriptions.create',
                'notifications.view',
            ],
            'patient' => [
                'appointments.view',
                'appointments.create',
                'appointments.cancel',
                'records.view_own',
                'access.grant',
                'access.revoke',
                'consultations.join',
                'invoices.view',
                'notifications.view',
                'reports.view',
            ],
            'receptionist' => [
                'patients.view',
                'patients.manage',
                'doctors.view',
                'appointments.view',
                'appointments.create',
                'appointments.cancel',
                'appointments.manage',
                'schedules.manage',
                'invoices.view',
                'payments.manage',
                'notifications.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
