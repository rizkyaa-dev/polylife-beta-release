<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => '1@2.c'],
            [
                'name'               => 'User',
                'password'          => Hash::make('1'),
                'email_verified_at' => now(),
                'is_admin'           => User::ADMIN_LEVEL_USER,
                'role'               => 'user',
                'account_status'     => 'active',
                'affiliation_status' => 'pending',
            ]
        );

        User::updateOrCreate(
            ['email' => '2@3.c'],
            [
                'name'               => 'Admin',
                'password'          => Hash::make('1'),
                'email_verified_at' => now(),
                'is_admin'           => User::ADMIN_LEVEL_ADMIN,
                'role'               => 'admin',
                'account_status'     => 'active',
                'affiliation_status' => 'verified',
            ]
        );

        User::updateOrCreate(
            ['email' => '3@4.c'],
            [
                'name'               => 'Super Admin',
                'password'          => Hash::make('1'),
                'email_verified_at' => now(),
                'is_admin'           => User::ADMIN_LEVEL_SUPER_ADMIN,
                'role'               => 'super_admin',
                'account_status'     => 'active',
                'affiliation_status' => 'verified',
            ]
        );
    }
}
