<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);
        User::create([
            'name' => 'Regular User',
            'email' => 'user@user.com',
            'password' => Hash::make('password'),
            'is_admin' => false,
        ]);
    }
}
