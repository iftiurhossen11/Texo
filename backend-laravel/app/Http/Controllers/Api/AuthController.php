<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'password' => ['required', 'confirmed', Password::min(12)->max(128)],
            'owner_setup_code' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->isConfiguredOwnerEmail($validated['email'])
            && ! $this->ownerSetupCodeMatches($validated['owner_setup_code'] ?? null)) {
            throw ValidationException::withMessages([
                'owner_setup_code' => ['A valid owner setup code is required for this email address.'],
            ]);
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'employee',
        ]);
        $user = $this->syncConfiguredOwnerRole($user, $validated['owner_setup_code'] ?? null);

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
        ]);

        $user = User::query()->where('email', Str::lower($credentials['email']))->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $this->syncConfiguredOwnerRole($user, $credentials['owner_setup_code'] ?? null);
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

    private function syncConfiguredOwnerRole(User $user, ?string $ownerSetupCode): User
    {
        $isConfiguredOwner = $this->isConfiguredOwnerEmail($user->email);
        $mayBecomeOwner = $isConfiguredOwner
            && ($user->role === 'owner' || $this->ownerSetupCodeMatches($ownerSetupCode));
        $role = $mayBecomeOwner ? 'owner' : ($user->role === 'owner' ? 'employee' : $user->role);

        if ($user->role !== $role) {
            $user->forceFill(['role' => $role])->save();
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
