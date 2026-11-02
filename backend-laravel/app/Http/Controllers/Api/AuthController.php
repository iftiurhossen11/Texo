<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->max(128)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'owner_setup_code' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'in:employee,company_owner,factory_owner'],
            'workspace_name' => ['nullable', 'string', 'max:255'],
            'invitation_code' => ['required_if:account_type,employee', 'nullable', 'string', 'max:255'],
        ]);

        $isConfiguredOwner = $this->isConfiguredOwnerEmail($validated['email']);
        $hasValidOwnerSetupCode = $this->ownerSetupCodeMatches($validated['owner_setup_code'] ?? null);
        $accountType = $validated['account_type'] ?? 'employee';
        $isOwnerBootstrap = $accountType !== 'employee'
            && $isConfiguredOwner
            && $hasValidOwnerSetupCode;

        if ($accountType !== 'employee' && ! $isOwnerBootstrap && empty($validated['invitation_code'])) {
            throw ValidationException::withMessages([
                'owner_setup_code' => ['Owner account creation requires the configured owner email and a valid setup code.'],
            ]);
        }

        if ($accountType === 'employee' && empty($validated['invitation_code'])) {
            throw ValidationException::withMessages([
                'invitation_code' => ['A workspace invitation code is required to create an account.'],
            ]);
        }

        $user = DB::transaction(function () use ($validated, $accountType, $isOwnerBootstrap): User {
            if ($isOwnerBootstrap) {
                if (! isset($validated['workspace_name']) || trim($validated['workspace_name']) === '') {
                    throw ValidationException::withMessages([
                        'workspace_name' => ['Enter your company or factory name.'],
                    ]);
                }

                $workspace = Workspace::query()
                    ->where('name', 'Texo Workspace')
                    ->whereNull('primary_owner_id')
                    ->first();

                if ($workspace === null) {
                    $workspace = Workspace::query()->create(['name' => trim($validated['workspace_name'])]);
                } else {
                    $workspace->update(['name' => trim($validated['workspace_name'])]);
                }

                $user = User::query()->create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'role' => $accountType,
                    'workspace_id' => $workspace->id,
                ]);
                $workspace->update(['primary_owner_id' => $user->id]);

                return $user;
            }

            $invite = WorkspaceInvite::query()
                ->where('token_hash', hash('sha256', $validated['invitation_code']))
                ->lockForUpdate()
                ->first();

            if ($invite === null || $invite->uses_remaining < 1 || $invite->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'invitation_code' => ['This invitation code is invalid, expired, or has no uses remaining.'],
                ]);
            }

            $isOwnerInvite = in_array($invite->role, ['company_owner', 'factory_owner'], true);
            if (($accountType !== 'employee' || $isOwnerInvite)
                && $invite->role !== $accountType) {
                throw ValidationException::withMessages([
                    'invitation_code' => ['This code was not issued for the selected owner account type.'],
                ]);
            }

            $invite->decrement('uses_remaining');

            return User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $invite->role,
                'workspace_id' => $invite->workspace_id,
            ]);
        });

        return response()->json([
            'token' => $user->createToken('texo-web')->plainTextToken,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'owner_setup_code' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'in:employee,company_owner,factory_owner'],
        ]);

        $user = User::query()->where('email', Str::lower($credentials['email']))->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $this->syncConfiguredOwnerRole(
            $user,
            $credentials['owner_setup_code'] ?? null,
            $credentials['account_type'] ?? null,
        );
        $token = $user->createToken('texo-demo')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    private function syncConfiguredOwnerRole(
        User $user,
        ?string $ownerSetupCode,
        ?string $accountType = null,
    ): User {
        $isConfiguredOwner = $this->isConfiguredOwnerEmail($user->email);
        $isOwnerRole = in_array($user->role, ['owner', 'company_owner', 'factory_owner'], true);
        $hasValidSetupCode = $this->ownerSetupCodeMatches($ownerSetupCode);
        $requestedOwnerRole = in_array($accountType, ['company_owner', 'factory_owner'], true)
            ? $accountType
            : null;

        if (! $isConfiguredOwner) {
            $role = $user->role;
        } elseif ($requestedOwnerRole !== null && $hasValidSetupCode) {
            $role = $requestedOwnerRole;
        } elseif ($isOwnerRole) {
            $role = $user->role;
        } elseif ($hasValidSetupCode) {
            $role = 'company_owner';
        } else {
            $role = $user->role;
        }

        if ($user->role !== $role) {
            $user->forceFill(['role' => $role])->save();
        }

        if (in_array($role, ['owner', 'company_owner', 'factory_owner'], true) && $user->workspace_id !== null) {
            Workspace::query()
                ->whereKey($user->workspace_id)
                ->whereNull('primary_owner_id')
                ->update(['primary_owner_id' => $user->id]);
        }

        return $user;
    }

    private function isConfiguredOwnerEmail(string $email): bool
    {
        return in_array(Str::lower($email), config('services.owner_emails', []), true);
    }

    private function ownerSetupCodeMatches(?string $providedCode): bool
    {
        $configuredCode = config('services.owner_setup_code');

        return is_string($configuredCode)
            && $configuredCode !== ''
            && is_string($providedCode)
            && hash_equals($configuredCode, $providedCode);
    }
}
