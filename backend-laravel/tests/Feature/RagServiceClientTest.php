<?php

namespace Tests\Feature;

use App\Services\RagServiceClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagServiceClientTest extends TestCase
{
    public function test_document_deletion_sends_the_workspace_as_a_query_parameter(): void
    {
        config([
            'services.rag.url' => 'https://rag.example.test/',
            'services.rag.api_token' => 'test-rag-token',
        ]);
        Http::fake([
            'https://rag.example.test/documents/42?workspace_id=7' => Http::response(['status' => 'ok']),
        ]);

        app(RagServiceClient::class)->deleteDocument(42, 7);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://rag.example.test/documents/42?workspace_id=7'
            && $request->hasHeader('X-RAG-API-Token', 'test-rag-token'));
    }
}
