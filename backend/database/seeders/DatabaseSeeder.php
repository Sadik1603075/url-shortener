<?php

namespace Database\Seeders;

use App\Models\AccessCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Development User',
            'email' => 'dev@example.com',
            'password' => Hash::make('password'),
        ]);

        AccessCode::create([
            'user_id' => $user->id,
            'code' => 'DEV-ACCESS-001',
            'is_active' => true,
        ]);
    }
}