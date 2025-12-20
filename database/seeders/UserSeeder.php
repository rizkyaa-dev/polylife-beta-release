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
            ['email' => '1@2.com'],
            [
                'name'              => 'testing',
                'password'          => Hash::make('1'),
                'email_verified_at' => now(),
            ]
        );
    }
}
