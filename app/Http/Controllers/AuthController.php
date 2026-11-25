<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\RecommendedCoursesForOsy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $request->session()->regenerate();
        $user = Auth::user();
        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'role' => $user->role,
            'name' => $user->name,
            'token' => $token,
            'must_change_password' => (bool) $user->must_change_password,
            'must_verify_email' => $user->needsEmailVerification(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->tokens()->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json(
            $user->toArray() + ['must_verify_email' => $user->needsEmailVerification()]
        );
    }

    // PUT: the logged-in user changes their own password
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'string',
                'max:72',
                'confirmed',
                'different:current_password',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.confirmed' => 'Passwords do not match.',
            'password.different' => 'Your new password must be different from your current password.',
        ]);

        // The 'hashed' cast on User hashes this automatically
        // forceFill because must_change_password is not mass assignable
        $request->user()->forceFill([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ])->save();

        return response()->json(['success' => true]);
    }

    /* ------------------------------------------------------------------ */
    /* Email verification (OSY accounts, shown at first login)            */
    /* ------------------------------------------------------------------ */

    // POST: emails a 6-digit code to the logged-in OSY
    public function sendEmailCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->needsEmailVerification()) {
            return response()->json(['verified' => true]);
        }

        $cooldown = (int) config('email_verification.resend_seconds');
        $minutes = (int) config('email_verification.code_ttl_minutes');
        $sentAt = $user->email_verification_sent_at;

        // A code went out a moment ago, so don't send another one yet
        if ($sentAt) {
            $elapsed = now()->timestamp - $sentAt->timestamp;

            if ($elapsed < $cooldown) {
                return response()->json([
                    'verified' => false,
                    'sent' => false,
                    'retry_after' => max(1, min($cooldown, $cooldown - $elapsed)),
                    'expires_in_minutes' => $minutes,
                    'email' => $this->maskEmail($user->email),
                ]);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Only a hash of the code is stored
        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes($minutes),
            'email_verification_attempts' => 0,
            'email_verification_sent_at' => now(),
        ])->save();

        try {
            Mail::raw(
                "Hi {$user->name},\n\n" .
                "Your BackToTrack verification code is: {$code}\n\n" .
                "It expires in {$minutes} minutes. If you did not expect this email, you can ignore it.\n\n" .
                "BackToTrack",
                function ($mail) use ($user) {
                    $mail->to($user->email)->subject('Your BackToTrack verification code');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Verification mail failed: ' . $e->getMessage());

            // Nothing was sent, so don't make the user wait out the cooldown
            $user->forceFill([
                'email_verification_code' => null,
                'email_verification_expires_at' => null,
                'email_verification_sent_at' => null,
            ])->save();

            return response()->json([
                'message' => 'Could not send the email. Please try again in a moment.',
            ], 500);
        }

        return response()->json([
            'verified' => false,
            'sent' => true,
            'retry_after' => $cooldown,
            'expires_in_minutes' => $minutes,
            'email' => $this->maskEmail($user->email),
        ]);
    }

    // POST: checks the code and marks the email as verified
    public function verifyEmailCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Enter the 6-digit code.',
            'code.digits' => 'The code must be 6 digits.',
        ]);

        $user = $request->user();

        if (! $user->needsEmailVerification()) {
            return response()->json(['success' => true]);
        }

        if (
            ! $user->email_verification_code
            || ! $user->email_verification_expires_at
            || $user->email_verification_expires_at->isPast()
        ) {
            return response()->json([
                'message' => 'This code has expired or was never sent. Request a new code.',
            ], 422);
        }

        if (! Hash::check($request->code, $user->email_verification_code)) {
            $maxAttempts = (int) config('email_verification.max_attempts');
            $attempts = $user->email_verification_attempts + 1;

            if ($attempts >= $maxAttempts) {
                $this->clearEmailCode($user);

                return response()->json([
                    'message' => 'Too many wrong attempts. Request a new code.',
                ], 422);
            }

            $user->forceFill(['email_verification_attempts' => $attempts])->save();

            $left = $maxAttempts - $attempts;

            return response()->json([
                'message' => 'That code is not correct. ' . $left . ' ' . ($left === 1 ? 'attempt' : 'attempts') . ' left.',
            ], 422);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'email_verification_attempts' => 0,
            'email_verification_sent_at' => null,
        ])->save();

        // The OSY can now enter the dashboard, so email their recommended courses.
        // send() never throws, so verification always succeeds.
        RecommendedCoursesForOsy::send($user);

        return response()->json(['success' => true]);
    }

    private function clearEmailCode(User $user): void
    {
        $user->forceFill([
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'email_verification_attempts' => 0,
        ])->save();
    }

    // juan@gmail.com -> j***@gmail.com
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1)
            . str_repeat('*', max(mb_strlen($local) - 1, 2))
            . '@' . $domain;
    }
}