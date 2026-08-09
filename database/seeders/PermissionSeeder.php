<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Form permissions
            'create forms',
            'edit forms',
            'delete forms',
            'view forms',
            'manage forms',
            'publish forms',
            'duplicate forms',

            // Submission permissions
            'view submissions',
            'export submissions',
            'delete submissions',

            // User management
            'manage users',
            'manage roles',
            'manage permissions',

            // Settings
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        // Super Admin - has all permissions
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());

        // Admin - has most permissions except user/role management
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->syncPermissions([
            'create forms',
            'edit forms',
            'delete forms',
            'view forms',
            'manage forms',
            'publish forms',
            'duplicate forms',
            'view submissions',
            'export submissions',
            'delete submissions',
            'manage settings',
        ]);

        // Editor - can create and edit forms
        $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $role->syncPermissions([
            'create forms',
            'edit forms',
            'view forms',
            'view submissions',
            'export submissions',
        ]);

        // Viewer - can only view forms and submissions
        $role = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $role->syncPermissions([
            'view forms',
            'view submissions',
        ]);

        // Assign roles to users
        $adminUser = User::where('email', 'superadmin@example.com')->first();
        if ($adminUser) {
            $adminUser->syncRoles(['super-admin']);
        }

        $demoUser = User::where('email', 'admin@example.com')->first();
        if ($demoUser) {
            $demoUser->syncRoles(['admin']);
        }

        $johnUser = User::where('email', 'editor@example.com')->first();
        if ($johnUser) {
            $johnUser->syncRoles(['editor']);
        }

        // Create a viewer user if not exists
        $viewerUser = User::firstOrCreate(
            ['email' => 'viewer@example.com'],
            [
                'name' => 'Viewer User',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );
        $viewerUser->syncRoles(['viewer']);
    }
}
