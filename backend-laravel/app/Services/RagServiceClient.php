<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RagServiceClient
{
    private const TIMEOUT_SECONDS = 120;

    public function ingest(int $documentId, int $workspaceId, string $docType, ?string $department, string $absoluteFilePath, ?string $sourceUrl = null): array
    {
        $url = rtrim(config('services.rag.url'), '/').'/ingest';

        $request = Http::timeout(self::TIMEOUT_SECONDS)
            ->retry(2, 500, throw: false)
            ->withHeaders($this->ragHeaders());

        if ($sourceUrl !== null) {
            $response = $request->asMultipart()->post($url, [
                ['name' => 'document_id', 'contents' => (string) $documentId],
                ['name' => 'workspace_id', 'contents' => (string) $workspaceId],
                ['name' => 'doc_type', 'contents' => $docType],
                ['name' => 'department', 'contents' => $department ?? 'general'],
                ['name' => 'source_url', 'contents' => $sourceUrl],
            ]);
        } else {
            $response = $request->attach(
                'file',
                file_get_contents($absoluteFilePath),
                basename($absoluteFilePath),
            )->post($url, [
                'document_id' => $documentId,
                'workspace_id' => $workspaceId,
                'doc_type' => $docType,
                'department' => $department ?? 'general',
            ]);
        }

        if ($response->failed()) {
            $detail = $response->json('detail.detail') ?? $response->json('detail') ?? $response->body();
            Log::error('RAG ingest failed.', [
                'document_id' => $documentId,
                'status' => $response->status(),
                'detail' => $detail,
            ]);

            throw new RuntimeException(is_string($detail) ? $detail : 'RAG ingest failed.');
        }

        return $response->json();
    }

    public function retrieve(
        string $query,
        int $workspaceId,
        int $topK = 3,
        ?string $docTypeFilter = null,
        ?int $documentIdFilter = null,
        ?string $userRole = null,
        ?array $allowedDepartments = null,
    ): array {
        $url = rtrim(config('services.rag.url'), '/').'/retrieve';

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->retry(2, 500, throw: false)
            ->withHeaders($this->ragHeaders())
            ->post($url, array_filter([
                'query' => $query,
                'workspace_id' => $workspaceId,
                'top_k' => $topK,
                'doc_type_filter' => $docTypeFilter,
                'document_id_filter' => $documentIdFilter,
                'user_role' => $userRole,
                'allowed_departments' => $allowedDepartments,
            ], fn ($value) => $value !== null));

        if ($response->failed()) {
            Log::error('RAG retrieve failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('RAG retrieve failed.');
        }

        return $response->json('results') ?? [];
    }

    public function deleteDocument(int $documentId, int $workspaceId): void
    {
        $url = rtrim(config('services.rag.url'), '/')."/documents/{$documentId}?".http_build_query([
            'workspace_id' => $workspaceId,
        ]);

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->retry(2, 500, throw: false)
            ->withHeaders($this->ragHeaders())
            ->delete($url);

        if ($response->failed()) {
            Log::error('RAG delete failed.', [
                'document_id' => $documentId,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('RAG vector deletion failed.');
        }
    }

    public function health(): string
    {
        try {
            $response = Http::timeout(5)
                ->get(rtrim(config('services.rag.url'), '/').'/health');

            if ($response->successful() && ($response->json('status') === 'ok' || $response->json('embedding_model_loaded') === true)) {
                return 'ok';
            }
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('RAG health check failed.', ['message' => $exception->getMessage()]);
        }

        return 'error';
    }

    private function ragHeaders(): array
    {
        $token = config('services.rag.api_token');

        return is_string($token) && $token !== ''
            ? ['X-RAG-API-Token' => $token]
            : [];
    }
}
