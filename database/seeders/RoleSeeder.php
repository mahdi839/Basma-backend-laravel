<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // 1. Create roles if not exist
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'user']);

        // 2. Create Mehedi Hasan user
        $mehedi = User::firstOrCreate(
            ['email' => 'mehedi@example.com'], // Unique identifier
            [
                'name' => 'Mehedi Hasan',
                'password' => Hash::make('password123'), // Change password later
            ]
        );

        // 3. Assign super-admin role
        $mehedi->assignRole($superAdminRole);
    }
}
