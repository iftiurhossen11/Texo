<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo seeding is disabled outside local and testing environments.');

            return;
        }

        $this->call([
            AdminUserSeeder::class,
            DemoUserSeeder::class,
            DemoDocumentSeeder::class,
        ]);
    }
}
