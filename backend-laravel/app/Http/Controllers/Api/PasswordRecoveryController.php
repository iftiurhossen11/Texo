<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryController extends Controller
{
    public function sendResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        if (! $this->mailDeliveryConfigured()) {
            return response()->json([
                'message' => 'Password recovery email is not configured. Please contact support.',
            ], 503);
        }

        $email = Str::lower($validated['email']);
        $status = PasswordBroker::sendResetLink(['email' => $email]);

        if ($status === PasswordBroker::RESET_THROTTLED) {
            return response()->json([
                'message' => 'If an account exists for this email, a password reset link will be sent.',
            ]);
        }

        if (! in_array($status, [PasswordBroker::RESET_LINK_SENT, PasswordBroker::INVALID_USER], true)) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'If an account exists for this email, a password reset link will be sent.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->max(128)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $status = PasswordBroker::reset(
            [
                ...$validated,
                'email' => Str::lower($validated['email']),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Your password has been reset.']);
    }

    private function mailDeliveryConfigured(): bool
    {
        if (config('mail.default') !== 'smtp') {
            return false;
        }

        $frontendHost = parse_url((string) config('app.frontend_url'), PHP_URL_HOST);

        return filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.from.address'))
            && config('mail.from.address') !== 'hello@example.com'
            && is_string($frontendHost)
            && ! in_array($frontendHost, ['localhost', '127.0.0.1'], true);
    }
}
