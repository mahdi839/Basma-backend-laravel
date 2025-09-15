<?php

namespace Database\Seeders;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class roleSeeder extends Seeder
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
                'password' => bcrypt('2443424434'),
            ],
            [
                'name' => 'mehedi@gmail.com',
                'email' => 'mehedi@gmail.com',
                'password' => bcrypt('mehedi@gmail.com'),
            ],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['email' => $admin['email']], // Check existing by email
                [
                    'name' => $admin['name'],
                    'password' => $admin['password'],
                    'role' => 'admin',
                    'remember_token' => Str::random(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }


    }
}
