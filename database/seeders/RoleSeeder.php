<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = [
            [
                'name' => 'Mehedi',
                'email' => 'hasanarefi56574@gmail.com',
                'password' => '2443424434',
            ],
            [
                'name' => 'Mehedi Admin',
                'email' => 'mehedi@gmail.com',
                'password' => 'mehedi@gmail.com',
            ],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'password' => Hash::make($admin['password']), // hash plain text here
                    'role' => 'admin',
                    'remember_token' => Str::random(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
