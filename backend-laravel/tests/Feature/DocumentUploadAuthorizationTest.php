<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_upload_general_documents_but_not_other_departments(): void
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
        ])->assertCreated()
            ->assertJsonPath('status', 'uploaded');

        $this->assertDatabaseHas('documents', [
            'original_filename' => 'factory-guide.txt',
            'department' => 'general',
            'uploaded_by' => $employee->id,
        ]);
        Queue::assertPushed(\App\Jobs\ProcessDocumentJob::class);

        $this->post('/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('qa-guide.txt', 'Quality guidance.'),
            'doc_type' => 'sop',
            'department' => 'qa',
        ])->assertForbidden();
    }
}
