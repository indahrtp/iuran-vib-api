<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Properti
            'residents.view', 'residents.manage',
            'units.view', 'units.manage',
            'vehicles.manage', 'water-meters.manage',
            'master.manage',
            // Tarif dan keuangan
            'tariffs.manage',
            'invoices.view', 'invoices.generate', 'invoices.request-change',
            'payments.view', 'payments.request-refund',
            'deposits.view', 'deposits.manage',
            'retributions.view', 'retributions.collect', 'retributions.waive',
            // Persetujuan (satu tingkat)
            'approve.invoice', 'approve.refund', 'approve.reschedule',
            // Operasional
            'meter-readings.view', 'meter-readings.create',
            'meter-readings.verify', 'meter-readings.cancel',
            'work.view-own', 'work.view-all', 'work.assign', 'review.work',
            'schedules.manage', 'points.view', 'points.close-period',
            // Portal warga
            'portal.access',
            // Sistem
            'users.manage', 'roles.manage', 'audit.view',
            'api-clients.manage', 'settings.manage',
        ];

        $approvals = ['approve.invoice', 'approve.refund', 'approve.reschedule', 'review.work'];

        $roles = [
            // Admin mengajukan, tidak menyetujui (pengaju tidak boleh menjadi pemutus)
            'admin' => array_values(array_diff($permissions, $approvals, ['portal.access'])),

            'pengurus' => [
                ...$approvals,
                'residents.view', 'units.view', 'invoices.view', 'payments.view',
                'deposits.view', 'retributions.view', 'meter-readings.view',
                'work.view-all', 'points.view', 'points.close-period', 'audit.view',
            ],

            'bendahara' => [
                'residents.view', 'units.view',
                'tariffs.manage', 'invoices.view', 'invoices.generate', 'invoices.request-change',
                'payments.view', 'payments.request-refund',
                'deposits.view', 'deposits.manage',
                'retributions.view', 'meter-readings.view',
            ],

            'meter_reader' => ['units.view', 'meter-readings.view', 'meter-readings.create', 'water-meters.manage'],

            'maintenance_staff' => ['work.view-own'],

            'supervisor' => [
                'work.view-all', 'work.assign', 'review.work', 'approve.reschedule',
                'schedules.manage', 'points.view', 'meter-readings.view', 'meter-readings.verify',
            ],

            'penjaga_pos' => ['retributions.view', 'retributions.collect', 'retributions.waive'],

            'warga' => ['portal.access'],
        ];

        $all = collect($permissions)
            ->merge(collect($roles)->flatten())
            ->unique()
            ->values();

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Cache dibuang SETELAH semua permission dibuat
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($roles as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');

            // Berikan objek, bukan nama, supaya tidak bergantung pada cache
            $role->syncPermissions(
                Permission::where('guard_name', 'web')->whereIn('name', $perms)->get()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}