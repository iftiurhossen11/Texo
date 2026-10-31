<?php

namespace Database\Seeders;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ProductionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('DEMO_USERS_PASSWORD');
        if (! is_string($password) || strlen($password) < 16) {
            throw new RuntimeException('Set DEMO_USERS_PASSWORD to a unique password of at least 16 characters.');
        }

        foreach ([
            ['email' => 'qa@texo.com', 'name' => 'QA Manager', 'role' => 'qa'],
            ['email' => 'compliance@texo.com', 'name' => 'Compliance Officer', 'role' => 'compliance'],
            ['email' => 'hr@texo.com', 'name' => 'HR Manager', 'role' => 'hr'],
            ['email' => 'gm@texo.com', 'name' => 'General Manager', 'role' => 'gm'],
            ['email' => 'employee@texo.com', 'name' => 'Floor Employee', 'role' => 'employee'],
        ] as $demoUser) {
            User::query()->firstOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'role' => $demoUser['role'],
                    'password' => Hash::make($password),
                ],
            );
        }

        $this->call(DemoDocumentSeeder::class);

        Document::query()
            ->whereDoesntHave('chunks')
            ->pluck('id')
            ->each(fn (int $documentId) => ProcessDocumentJob::dispatch($documentId));
    }
}
