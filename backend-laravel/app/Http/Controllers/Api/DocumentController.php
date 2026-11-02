<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Rules\ValidDocumentMagicBytes;
use App\Services\RagServiceClient;
use App\Services\RoleAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    private const MIME_TO_FILE_TYPE = [
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'text/markdown' => 'md',
        'text/html' => 'html',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    private const EXTENSION_TO_FILE_TYPE = [
        'pdf' => 'pdf',
        'txt' => 'txt',
        'csv' => 'csv',
        'md' => 'md',
        'docx' => 'docx',
        'html' => 'html',
        'htm' => 'html',
        'mhtml' => 'html',
    ];

    public function store(Request $request): JsonResponse
    {
        if (! app(RoleAccessService::class)->canUpload($request->user())) {
            return response()->json([
                'message' => 'Only company and factory owners can upload documents.',
            ], 403);
        }

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480',
                'extensions:pdf,txt,csv,md,docx,html,htm,mhtml',
                new ValidDocumentMagicBytes,
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'doc_type' => [
                'required',
                Rule::in([
                    'sop',
                    'quality_manual',
                    'buyer_requirement',
                    'compliance_audit',
                    'production_guideline',
                    'hr_policy',
                    'cybersecurity_policy',
                    'supplier_guideline',
                    'other',
                ]),
            ],
            'department' => [
                'nullable',
                Rule::in([
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

        if (! app(RoleAccessService::class)->canAccess($validated['department'] ?? null, $request->user())) {
            return response()->json([
                'message' => 'You do not have permission to upload documents for this department.',
            ], 403);
        }

        $file = $request->file('file');
        $mimeType = $file->getMimeType() ?? '';
        $extension = strtolower($file->getClientOriginalExtension());
        $fileType = self::MIME_TO_FILE_TYPE[$mimeType]
            ?? self::EXTENSION_TO_FILE_TYPE[$extension]
            ?? null;

        if ($fileType === null) {
            return response()->json([
                'message' => 'Unsupported file type.',
            ], 422);
        }

        $originalFilename = $file->getClientOriginalName();
        $storageFilename = Str::uuid()->toString().'_'.$originalFilename;
        $storagePath = $file->storeAs(
            "workspaces/{$request->user()->workspace_id}/documents",
            $storageFilename,
            'local',
        );

        if ($storagePath === false) {
            return response()->json([
                'message' => 'Failed to store uploaded file.',
            ], 500);
        }

        $document = Document::query()->create([
            'workspace_id' => $request->user()->workspace_id,
            'title' => $validated['title'] ?? pathinfo($originalFilename, PATHINFO_FILENAME),
            'original_filename' => $originalFilename,
            'file_type' => $fileType,
            'storage_path' => $storagePath,
            'source_type' => $fileType === 'html' ? 'html' : 'file',
            'doc_type' => $validated['doc_type'],
            'department' => $validated['department'] ?? null,
            'status' => 'uploaded',
            'uploaded_by' => $request->user()->id,
        ]);

        ProcessDocumentJob::dispatch($document->id);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'document_id' => $document->id,
            'status' => $document->status,
            'suggested_questions' => $this->generateSuggestedQuestions($validated['doc_type'], $file),
        ], 201);
    }

    public function index(Request $request, RoleAccessService $roleAccessService): JsonResponse
    {
        $documents = Document::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->whereIn('department', $roleAccessService->allowedDepartments($request->user()))
            ->orderByDesc('created_at')
            ->get([
                'id',
                'title',
                'original_filename',
                'file_type',
                'source_type',
                'source_url',
                'doc_type',
                'department',
                'status',
                'error_message',
                'created_at',
                'indexed_at',
            ]);

        return response()->json([
            'data' => $documents,
        ]);
    }

    public function show(
        int $document,
        Request $request,
        RoleAccessService $roleAccessService,
    ): JsonResponse {
        $document = Document::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->findOrFail($document);

        if (! $roleAccessService->canAccess($document->department, $request->user())) {
            return response()->json([
                'message' => 'You do not have permission to view this document.',
            ], 403);
        }

        return response()->json([
            'data' => $document->loadCount('chunks'),
        ]);
    }

    public function view(int $document, Request $request, RoleAccessService $roleAccessService)
    {
        $document = Document::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->findOrFail($document);

        if (! $roleAccessService->canAccess($document->department, $request->user())) {
            return response()->json([
                'message' => 'You do not have permission to view this document.',
            ], 403);
        }

        if (! $document->storage_path || ! Storage::disk('local')->exists($document->storage_path)) {
            return response()->json([
                'message' => 'Document file not found.',
            ], 404);
        }

        $path = Storage::disk('local')->path($document->storage_path);
        $mimeType = Storage::disk('local')->mimeType($document->storage_path)
            ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($document->original_filename).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(
        int $document,
        Request $request,
        RoleAccessService $roleAccessService,
        RagServiceClient $ragServiceClient,
    ): JsonResponse {
        $document = Document::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->findOrFail($document);

        if (! $roleAccessService->canAccess($document->department, $request->user())) {
            return response()->json([
                'message' => 'You do not have permission to delete this document.',
            ], 403);
        }

        try {
            $ragServiceClient->deleteDocument($document->id, $document->workspace_id);
        } catch (\Throwable $exception) {
            report($exception);
        }

        if ($document->storage_path && Storage::disk('local')->exists($document->storage_path)) {
            Storage::disk('local')->delete($document->storage_path);
        }

        $document->chunks()->delete();
        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully.',
        ]);
    }

    private function generateSuggestedQuestions(string $docType, UploadedFile $file): array
    {
        $defaults = match ($docType) {
            'sop' => [
                'What are the inspection steps for this SOP?',
                'Who is responsible for defect tagging and escalation?',
                'What should an inspector do when a defect is found?',
            ],
            'quality_manual' => [
                'What are the key quality standards in this manual?',
                'What defect categories are covered in this document?',
                'Who should follow this quality checklist?',
            ],
            'buyer_requirement', 'compliance_audit' => [
                'What are the AQL requirements for major defects?',
                'What buyer compliance standards are required here?',
                'What should be prepared for an audit?',
            ],
            'production_guideline' => [
                'What are the production steps in this guideline?',
                'Who is responsible for each step?',
                'What should be done if a process issue is found?',
            ],
            'hr_policy' => [
                'What PPE is needed on Day 1?',
                'How should a worker report a safety issue?',
                'What are the attendance and shift rules?',
            ],
            'cybersecurity_policy' => [
                'How should an employee report a suspicious email?',
                'What are the phishing warning signs?',
                'What should be done when a suspicious link is received?',
            ],
            'supplier_guideline' => [
                'What are the supplier requirements in this guideline?',
                'What compliance expectations should suppliers meet?',
                'How should quality issues be escalated?',
            ],
            default => [
                'What is the main purpose of this document?',
                'What are the key responsibilities described here?',
                'What actions should be taken based on this guidance?',
            ],
        };

        $content = $this->extractTextSample($file);
        $derived = [];

        if ($content !== '') {
            $normalized = strtolower($content);
            $keywordMap = [
                'broken stitch' => 'What should an inspector do for a broken stitch?',
                'aql' => 'What are the AQL requirements for major defects?',
                'phishing' => 'How should an employee report a suspicious email?',
                'ppe' => 'What PPE is needed on Day 1?',
                'worker rights' => 'What are the worker rights and reporting procedures?',
                'audit' => 'What should be prepared for an audit?',
                'inspection' => 'What are the inspection steps in this document?',
            ];

            foreach ($keywordMap as $keyword => $question) {
                if (str_contains($normalized, $keyword)) {
                    $derived[] = $question;
                }
            }
        }

        $questions = array_values(array_unique(array_merge($derived, $defaults)));

        return array_slice($questions, 0, 3);
    }

    private function extractTextSample(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['txt', 'csv', 'md'], true)) {
            return '';
        }

        $content = file_get_contents($file->getRealPath());

        return is_string($content) ? substr($content, 0, 4000) : '';
    }
}
