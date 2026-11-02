<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DocumentChunk;
use App\Models\QueryLog;
use App\Services\LlmClientFactory;
use App\Services\PromptTemplateBuilder;
use App\Services\RagServiceClient;
use App\Services\RoleAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ChatController extends Controller
{
    public function __construct(
        private readonly RagServiceClient $ragServiceClient,
        private readonly PromptTemplateBuilder $promptTemplateBuilder,
        private readonly RoleAccessService $roleAccessService,
    ) {}

    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'context_tag' => [
                'required',
                Rule::in(['exhibition_booth', 'internal_test']),
            ],
        ]);

        $session = ChatSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'workspace_id' => $request->user()->workspace_id,
            'user_id' => $request->user()->id,
            'context_tag' => $validated['context_tag'],
            'started_at' => now(),
        ]);

        return response()->json([
            'session_id' => $session->id,
            'session_uuid' => $session->session_uuid,
            'context_tag' => $session->context_tag,
            'started_at' => $session->started_at,
        ], 201);
    }

    public function storeMessage(Request $request, int $session): JsonResponse
    {
        $session = ChatSession::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->where('user_id', $request->user()->id)
            ->findOrFail($session);

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:500', 'regex:/\S/'],
            'department_filter' => [
                'nullable',
                Rule::in([
                    'all',
                    'production',
                    'qa',
                    'compliance_sustainability',
                    'supply_chain_merchandising',
                    'hr_training',
                    'it_cybersecurity',
                    'spinning',
                    'weaving',
                    'knitting',
                    'dyeing_finishing',
                    'quality_control',
                    'ppc',
                    'industrial_engineering',
                    'maintenance_engineering',
                    'store_warehouse',
                    'chemical_store',
                    'etp',
                    'accounts_finance',
                    'commercial_sales',
                    'it_edp',
                    'general',
                ]),
            ],
        ]);

        $startedAt = microtime(true);

        $userMessage = ChatMessage::query()->create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $validated['content'],
        ]);

        QueryLog::query()->create([
            'chat_message_id' => $userMessage->id,
            'ip_address' => $request->ip(),
        ]);

        try {
            $user = $request->user();
            $allowedDepartments = $this->roleAccessService->allowedDepartments($user);
            $requestedDepartment = $validated['department_filter'] ?? null;
            if ($requestedDepartment !== null && $requestedDepartment !== 'all') {
                $allowedDepartments = in_array($requestedDepartment, $allowedDepartments, true)
                    ? [$requestedDepartment]
                    : [];
            }
            $retrieved = $this->ragServiceClient->retrieve(
                $validated['content'],
                workspaceId: $user->workspace_id,
                topK: 3,
                userRole: $user->role,
                allowedDepartments: $allowedDepartments ?: ['__no_access__'],
            );

            if ($retrieved === []) {
                $assistantMessage = ChatMessage::query()->create([
                    'session_id' => $session->id,
                    'role' => 'assistant',
                    'content' => $this->noAnswerMessage(),
                    'retrieved_chunk_ids' => [],
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json([
                    'session_id' => $session->id,
                    'user_message' => $userMessage,
                    'assistant_message' => $assistantMessage,
                    'retrieved_chunks' => [],
                    'citations' => [],
                    'no_answer' => true,
                ]);
            }

            $vectorIds = collect($retrieved)->pluck('vector_id')->filter()->all();
            $dbChunks = DocumentChunk::query()
                ->with('document:id,title,original_filename,department')
                ->whereHas('document', fn ($query) => $query->where('workspace_id', $user->workspace_id))
                ->whereIn('vector_id', $vectorIds)
                ->get()
                ->keyBy('vector_id');

            $promptChunks = collect($retrieved)->map(function (array $item) use ($dbChunks, $user, $allowedDepartments): ?array {
                $dbChunk = $dbChunks->get($item['vector_id']);

                if (
                    $dbChunk === null
                    || ! $this->roleAccessService->canAccess($dbChunk->document?->department, $user)
                    || ! in_array($dbChunk->document?->department ?? 'general', $allowedDepartments, true)
                ) {
                    return null;
                }

                return [
                    'content' => $dbChunk?->content ?? $item['content'],
                    'section_heading' => $item['section_heading'] ?? $dbChunk?->section_heading,
                    'page_number' => $item['page_number'] ?? $dbChunk?->page_number,
                    'vector_id' => $item['vector_id'],
                    'similarity_score' => $item['similarity_score'] ?? null,
                    'document_chunk_id' => $dbChunk?->id,
                    'document' => $dbChunk?->document,
                ];
            })->filter()->values();

            if ($promptChunks->isEmpty()) {
                $assistantMessage = ChatMessage::query()->create([
                    'session_id' => $session->id,
                    'role' => 'assistant',
                    'content' => $this->accessDeniedMessage(),
                    'retrieved_chunk_ids' => [],
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json([
                    'session_id' => $session->id,
                    'user_message' => $userMessage,
                    'assistant_message' => $assistantMessage,
                    'retrieved_chunks' => [],
                    'citations' => [],
                    'access_denied' => true,
                ]);
            }

            $llmClient = LlmClientFactory::make();
            $answer = $llmClient->generate(
                $this->promptTemplateBuilder->buildMessages(
                    $promptChunks->map(fn (array $chunk) => [
                        'content' => $chunk['content'],
                        'section_heading' => $chunk['section_heading'],
                        'page_number' => $chunk['page_number'],
                    ])->all(),
                    $validated['content'],
                ),
                temperature: 0.1,
                maxTokens: 512,
            );

            $noAnswer = strcasecmp(trim($answer), $this->noAnswerMessage()) === 0;
            $chunkIds = $noAnswer
                ? []
                : $promptChunks->pluck('document_chunk_id')->filter()->values()->all();
            $responseChunks = $noAnswer ? collect() : $promptChunks;

            $assistantMessage = ChatMessage::query()->create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $answer,
                'retrieved_chunk_ids' => $chunkIds,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'session_id' => $session->id,
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
                'retrieved_chunks' => $responseChunks,
                'citations' => $responseChunks->map(function (array $chunk): array {
                    return [
                        'document_title' => $chunk['document']?->title,
                        'document_filename' => $chunk['document']?->original_filename,
                        'section_heading' => $chunk['section_heading'],
                        'page_number' => $chunk['page_number'],
                        'similarity_score' => $chunk['similarity_score'],
                    ];
                })->values(),
                'no_answer' => $noAnswer,
            ]);
        } catch (Throwable $exception) {
            $assistantMessage = ChatMessage::query()->create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => 'The assistant is temporarily unavailable. Please try again shortly.',
                'retrieved_chunk_ids' => [],
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'session_id' => $session->id,
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
                'error' => true,
                'message' => $exception->getMessage(),
            ], 503);
        }
    }

    public function showSession(Request $request, int $session): JsonResponse
    {
        $session = ChatSession::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->where('user_id', $request->user()->id)
            ->findOrFail($session);

        $session->load(['messages' => function ($query) {
            $query->orderBy('created_at');
        }]);

        return response()->json([
            'data' => $session,
        ]);
    }

    private function noAnswerMessage(): string
    {
        return 'I could not find this information in the uploaded documents.';
    }

    private function accessDeniedMessage(): string
    {
        return 'Access Denied. You do not have permission to view this information.';
    }
}
