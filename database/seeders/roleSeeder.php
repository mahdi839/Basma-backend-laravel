<?php

namespace Database\Seeders;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class roleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::create([
            'name' => 'mehedi',
            'email' => 'hasanarefi56574@gmail.com',
            'password' => Hash::make('2443424434'), // Always encrypt passwords
            'role' => 'admin',
            'remember_token' => str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);


    }
}
