<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_listing_analytics_and_direct_access_are_workspace_scoped(): void
    {
        [$firstWorkspace, $firstUser] = $this->createWorkspaceUser('first');
        [$secondWorkspace, $secondUser] = $this->createWorkspaceUser('second');
        $firstDocument = $this->createDocument($firstWorkspace, $firstUser, 'First workspace SOP');
        $secondDocument = $this->createDocument($secondWorkspace, $secondUser, 'Second workspace SOP');

        $this->actingAs($firstUser, 'sanctum')
            ->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstDocument->id);

        $this->getJson('/api/analytics')
            ->assertOk()
            ->assertJsonPath('total_indexed', 1);

        $this->getJson("/api/documents/{$secondDocument->id}")->assertNotFound();
        $this->getJson("/api/documents/{$secondDocument->id}/view")->assertNotFound();
        $this->deleteJson("/api/documents/{$secondDocument->id}")->assertNotFound();
    }

    public function test_chat_sessions_and_retrieved_chunks_are_workspace_scoped(): void
    {
        [$firstWorkspace, $firstUser] = $this->createWorkspaceUser('first');
        [$secondWorkspace, $secondUser] = $this->createWorkspaceUser('second');
        $secondDocument = $this->createDocument($secondWorkspace, $secondUser, 'Private workspace SOP');
        $chunk = DocumentChunk::query()->create([
            'document_id' => $secondDocument->id,
            'chunk_index' => 0,
            'content' => 'Private factory information.',
            'token_count' => 4,
            'vector_id' => 'private-vector',
        ]);
        $secondSession = ChatSession::query()->create([
            'session_uuid' => '11111111-1111-4111-8111-111111111111',
            'workspace_id' => $secondWorkspace->id,
            'user_id' => $secondUser->id,
            'context_tag' => 'internal_test',
        ]);

        $this->actingAs($firstUser, 'sanctum')
            ->getJson("/api/chat/sessions/{$secondSession->id}")
            ->assertNotFound();

        config([
            'services.rag.url' => 'https://rag.test',
            'services.rag.api_token' => 'test-token',
        ]);
        Http::fake([
            'https://rag.test/retrieve' => Http::response([
                'results' => [[
                    'vector_id' => $chunk->vector_id,
                    'document_id' => $secondDocument->id,
                    'chunk_index' => 0,
                    'content' => $chunk->content,
                    'similarity_score' => 0.99,
                ]],
            ]),
        ]);

        $session = $this->postJson('/api/chat/sessions', [
            'context_tag' => 'internal_test',
        ])->assertCreated()->json('session_id');

        $this->postJson("/api/chat/sessions/{$session}/messages", [
            'content' => 'Show me private information',
        ])->assertOk()
            ->assertJsonPath('access_denied', true)
            ->assertJsonCount(0, 'retrieved_chunks');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://rag.test/retrieve'
            && $request['workspace_id'] === $firstWorkspace->id);
    }

    public function test_owner_can_issue_role_scoped_invites_without_exposing_hashes(): void
    {
        [$workspace, $owner] = $this->createWorkspaceUser('owner', 'company_owner');

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/workspace/invites', [
                'role' => 'qa',
                'uses' => 3,
                'expires_in_days' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'qa')
            ->assertJsonPath('data.uses_remaining', 3);

        $code = $response->json('data.code');
        $this->assertIsString($code);
        $this->assertDatabaseHas('workspace_invites', [
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'token_hash' => hash('sha256', $code),
            'role' => 'qa',
        ]);

        $this->getJson('/api/workspace/invites')
            ->assertOk()
            ->assertJsonMissingPath('data.0.token_hash')
            ->assertJsonMissingPath('data.0.code');

        $inviteId = $response->json('data.id');
        $this->deleteJson("/api/workspace/invites/{$inviteId}")
            ->assertOk();
        $this->assertDatabaseMissing('workspace_invites', ['id' => $inviteId]);
    }

    private function createWorkspaceUser(string $suffix, string $role = 'employee'): array
    {
        $workspace = Workspace::query()->create(['name' => "{$suffix} workspace"]);
        $user = User::query()->create([
            'name' => ucfirst($suffix),
            'email' => "{$suffix}@example.com",
            'password' => 'Texo-Strong-Pass-123',
            'role' => $role,
            'workspace_id' => $workspace->id,
        ]);
        if (in_array($role, ['owner', 'company_owner', 'factory_owner'], true)) {
            $workspace->update(['primary_owner_id' => $user->id]);
        }

        return [$workspace, $user];
    }

    private function createDocument(Workspace $workspace, User $uploader, string $title): Document
    {
        return Document::query()->create([
            'workspace_id' => $workspace->id,
            'title' => $title,
            'original_filename' => "{$title}.txt",
            'file_type' => 'txt',
            'doc_type' => 'sop',
            'department' => 'general',
            'status' => 'indexed',
            'uploaded_by' => $uploader->id,
        ]);
    }
}
