<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LlmClientFactory;
use App\Services\RagServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(RagServiceClient $ragServiceClient): JsonResponse
    {
        $dbStatus = 'connected';

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
        } catch (\Throwable) {
            $dbStatus = 'error';
        }

        $ragStatus = $ragServiceClient->health();
        $llmProvider = config('services.llm.provider', 'groq');
        $llmStatus = LlmClientFactory::make($llmProvider)->health();

        $overall = ($dbStatus === 'connected' && $ragStatus === 'ok') ? 'ok' : 'degraded';

        return response()->json([
            'status' => $overall,
            'db' => $dbStatus,
            'rag_service' => $ragStatus,
            'llm_provider' => $llmProvider,
            'llm_provider_status' => $llmStatus,
        ], $overall === 'ok' ? 200 : 503);
    }
}
