<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    private const INVITABLE_ROLES = [
        'employee',
        'gm',
        'qa',
        'hr',
        'compliance',
        'company_owner',
        'factory_owner',
    ];

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->workspace()->firstOrFail()->only(['id', 'name']),
        ]);
    }

    public function invites(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        return response()->json([
            'data' => $request->user()->workspace()->firstOrFail()->invites()
                ->latest()
                ->get(['id', 'role', 'uses_remaining', 'expires_at', 'created_at']),
        ]);
    }

    public function createInvite(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'role' => ['required', Rule::in(self::INVITABLE_ROLES)],
            'uses' => ['required', 'integer', 'min:1', 'max:50'],
            'expires_in_days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $code = Str::random(48);
        $invite = WorkspaceInvite::query()->create([
            'workspace_id' => $request->user()->workspace_id,
            'created_by' => $request->user()->id,
            'token_hash' => hash('sha256', $code),
            'role' => $validated['role'],
            'uses_remaining' => $validated['uses'],
            'expires_at' => now()->addDays($validated['expires_in_days']),
        ]);

        return response()->json([
            'data' => [
                'id' => $invite->id,
                'code' => $code,
                'role' => $invite->role,
                'uses_remaining' => $invite->uses_remaining,
                'expires_at' => $invite->expires_at,
            ],
        ], 201);
    }

    public function revokeInvite(Request $request, int $invite): JsonResponse
    {
        $this->authorizeOwner($request);
        $request->user()->workspace()->firstOrFail()->invites()->findOrFail($invite)->delete();

        return response()->json(['message' => 'Invitation revoked.']);
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, ['owner', 'company_owner', 'factory_owner'], true),
            403,
            'Only workspace owners can manage invitations.',
        );
    }
}
