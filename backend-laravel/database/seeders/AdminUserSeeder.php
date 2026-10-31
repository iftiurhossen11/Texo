<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@texo.local'],
            [
                'name' => 'Texo Admin',
                'password' => Hash::make('admin1234'),
                'role' => 'admin',
            ]
        );
    }
}
