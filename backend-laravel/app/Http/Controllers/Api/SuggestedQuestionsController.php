<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\RoleAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestedQuestionsController extends Controller
{
    public function __invoke(Request $request, RoleAccessService $roleAccessService): JsonResponse
    {
        $documents = Document::query()
            ->where('status', 'indexed')
            ->whereIn('department', $roleAccessService->allowedDepartments($request->user()))
            ->select('department')
            ->distinct()
            ->get();

        $departments = $documents->pluck('department')->all();
        $suggestions = [];

        if (in_array('qa', $departments, true) || in_array('quality_control', $departments, true)) {
            $suggestions[] = 'What is the AQL for major defects?';
        }
        if (in_array('hr_training', $departments, true)) {
            $suggestions[] = 'What is the leave policy?';
        }
        if (in_array('it_cybersecurity', $departments, true)) {
            $suggestions[] = 'How to report a phishing email?';
        }
        if (in_array('general', $departments, true)) {
            $suggestions[] = 'What are the working hours?';
        }

        if ($suggestions === []) {
            $suggestions = [
                'What are the working hours?',
                'How to report a safety hazard?',
                'What is the shift schedule?',
            ];
        }

        return response()->json([
            'suggestions' => array_slice($suggestions, 0, 3),
        ]);
    }
}
