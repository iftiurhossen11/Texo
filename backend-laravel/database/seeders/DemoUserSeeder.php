<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo user accounts are disabled outside local and testing environments.');

            return;
        }

        foreach ([
            ['email' => 'qa@texo.com', 'name' => 'QA Manager', 'role' => 'qa'],
            ['email' => 'compliance@texo.com', 'name' => 'Compliance Officer', 'role' => 'compliance'],
            ['email' => 'hr@texo.com', 'name' => 'HR Manager', 'role' => 'hr'],
            ['email' => 'gm@texo.com', 'name' => 'General Manager', 'role' => 'gm'],
            ['email' => 'employee@texo.com', 'name' => 'Floor Employee', 'role' => 'employee'],
        ] as $demoUser) {
            User::query()->updateOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'role' => $demoUser['role'],
                    'password' => Hash::make('password123'),
                ],
            );
        }
    }
}
