<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OsyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OsyAccountController extends Controller
{
    private const SK_ROLE = 'sk_officials';
    private const OSY_ROLE = 'osy';

    // Letters and digits that are hard to mix up when read aloud (no 0/O, 1/l/I)
    private const PASSWORD_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    private const PASSWORD_LENGTH = 10;

    // POST: an SK official gives an OSY a new temporary password
    public function resetPassword(Request $request, OsyProfile $osyProfile): JsonResponse
    {
        abort_unless($request->user()->role === self::SK_ROLE, 403, 'Unauthorized');

        $user = $osyProfile->user;

        if (! $user) {
            return response()->json([
                'message' => 'This OSY does not have an account yet.',
            ], 422);
        }

        // This can only ever reset an OSY account, never an SK official or Maxima account
        abort_unless($user->role === self::OSY_ROLE, 403, 'Only OSY accounts can be reset here.');

        $temporary = $this->generateTemporaryPassword();

        // forceFill because must_change_password is not mass assignable
        $user->forceFill([
            'password' => Hash::make($temporary),
            'must_change_password' => true,
        ])->save();

        $user->tokens()->delete();

        // With database sessions, also end any browser session the OSY still has open
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        return response()
            ->json([
                'message' => 'Temporary password created.',
                'email' => $user->email,
                'temporary_password' => $temporary,
            ])
            ->header('Cache-Control', 'no-store'); // never cache a password
    }

    private function generateTemporaryPassword(): string
    {
        $max = strlen(self::PASSWORD_ALPHABET) - 1;

        // Always contains at least one letter and one number, so it passes the same rules as a real password
        do {
            $password = '';

            for ($i = 0; $i < self::PASSWORD_LENGTH; $i++) {
                $password .= self::PASSWORD_ALPHABET[random_int(0, $max)];
            }
        } while (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password));

        return $password;
    }
}