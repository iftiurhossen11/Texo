<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\RagServiceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(
        public int $documentId,
    ) {}

    public function handle(RagServiceClient $ragServiceClient): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document === null) {
            Log::warning('ProcessDocumentJob: document not found.', [
                'document_id' => $this->documentId,
            ]);

            return;
        }

        $document->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            if ($document->source_type === 'url' && $document->source_url) {
                $absolutePath = '';
            } else {
                $absolutePath = Storage::disk('local')->path($document->storage_path);

                if (! is_file($absolutePath)) {
                    throw new \RuntimeException('Uploaded file not found on disk.');
                }
            }

            $response = $ragServiceClient->ingest(
                documentId: $document->id,
                workspaceId: $document->workspace_id,
                docType: $document->doc_type,
                department: $document->department,
                absoluteFilePath: $absolutePath,
                sourceUrl: $document->source_url,
            );

            DocumentChunk::query()->where('document_id', $document->id)->delete();

            foreach ($response['chunks'] ?? [] as $chunk) {
                DocumentChunk::query()->create([
                    'document_id' => $document->id,
                    'chunk_index' => $chunk['chunk_index'],
                    'section_heading' => $chunk['section_heading'] ?? null,
                    'page_number' => $chunk['page_number'] ?? null,
                    'content' => $chunk['content'],
                    'token_count' => $chunk['token_count'],
                    'vector_id' => $chunk['vector_id'],
                ]);
            }

            $document->update([
                'status' => 'indexed',
                'indexed_at' => now(),
                'error_message' => null,
            ]);

            Log::info('ProcessDocumentJob completed.', [
                'document_id' => $document->id,
                'chunks' => count($response['chunks'] ?? []),
            ]);
        } catch (Throwable $exception) {
            $this->fail($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $document->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Document processing failed.',
        ]);
    }
}
