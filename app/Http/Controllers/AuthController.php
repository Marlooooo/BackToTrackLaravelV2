<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:osy,maxima_tesda_school,sk_officials',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'success' => true,
            'role' => $user->role,
        ]);
    }

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
        return response()->json($request->user());
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
}