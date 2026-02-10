<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Product permissions
            'view products',
            'create products',
            'edit products',
            'delete products',
            'reOrderImage',
            // Order permissions
            'view orders',
            'create orders',
            'edit orders',
            'update orders',
            'delete orders',
            'download orders',
            'order show',
            'incomplete_order',

            // Category permissions
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',

            // sizes
            'view sizes',
            'create sizes',
            'edit sizes',
            'delete sizes',

            // Banner permissions
            'view banners',
            'create banners',
            'edit banners',
            'delete banners',

            // leaderboard
            'view leaderboard',
            'view statistics',
            'view customer details',

            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Role management (super-admin only)
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'assign roles',

            // Settings
            'view settings',
            'edit settings',

            // Dashboard
            'view dashboard',
            'view dashboard summary'
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                [
                    'name' => $permission,
                ],
                []
            );
        }

        // ✅ Assign all permissions to super-admin
        $superAdminRole = Role::findByName('super-admin');
        $superAdminRole->givePermissionTo(Permission::all());

        // ✅ Assign limited permissions to admin
        $adminRole = Role::findByName('admin');
        $adminRole->givePermissionTo([
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            'view banners',
            'create banners',
            'edit banners',
            'delete banners',
            'view dashboard',
        ]);

        // ✅ User role has no admin permissions
        $userRole = Role::findByName('user');
        // Users can only access public routes
    }
}
