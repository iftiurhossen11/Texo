<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('The demo admin account is disabled outside local and testing environments.');

            return;
        }

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
