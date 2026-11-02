<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_upload_documents(): void
    {
        Storage::fake('local');
        Queue::fake();

        $employee = User::query()->create([
            'name' => 'General Employee',
            'email' => 'general.employee@example.com',
            'password' => 'test-password',
            'role' => 'employee',
        ]);
        $this->actingAs($employee, 'sanctum');

        $this->post('/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('factory-guide.txt', 'General factory guidance.'),
            'doc_type' => 'sop',
            'department' => 'general',
        ])->assertForbidden();

        $gm = User::query()->create([
            'name' => 'General Manager',
            'email' => 'general.manager@example.com',
            'password' => 'test-password',
            'role' => 'gm',
        ]);
        $this->actingAs($gm, 'sanctum')
            ->post('/api/documents', [
                'file' => UploadedFile::fake()->createWithContent('qa-guide.txt', 'Quality guidance.'),
                'doc_type' => 'sop',
                'department' => 'qa',
            ])->assertForbidden();

        $owner = User::query()->create([
            'name' => 'Factory Owner',
            'email' => 'factory.owner@example.com',
            'password' => 'test-password',
            'role' => 'owner',
        ]);
        $this->actingAs($owner, 'sanctum')
            ->post('/api/documents', [
                'file' => UploadedFile::fake()->createWithContent('qa-guide.txt', 'Quality guidance.'),
                'doc_type' => 'sop',
                'department' => 'qa',
            ])->assertCreated()
            ->assertJsonPath('status', 'uploaded');

        $this->assertDatabaseHas('documents', [
            'original_filename' => 'qa-guide.txt',
            'department' => 'qa',
            'uploaded_by' => $owner->id,
        ]);

        foreach (['company_owner', 'factory_owner'] as $ownerRole) {
            $typedOwner = User::query()->create([
                'name' => ucfirst(str_replace('_', ' ', $ownerRole)),
                'email' => "{$ownerRole}@example.com",
                'password' => 'test-password',
                'role' => $ownerRole,
            ]);

            $this->actingAs($typedOwner, 'sanctum')
                ->post('/api/documents', [
                    'file' => UploadedFile::fake()->createWithContent("{$ownerRole}.txt", 'Owner guidance.'),
                    'doc_type' => 'sop',
                    'department' => 'general',
                ])->assertCreated()
                ->assertJsonPath('status', 'uploaded');
        }

        Queue::assertPushed(ProcessDocumentJob::class);
    }
}
