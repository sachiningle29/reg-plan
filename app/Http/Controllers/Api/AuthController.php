<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function adminLogin(Request $request)
    {
        return $this->attemptLogin($request, 'admin');
    }

    public function userLogin(Request $request)
    {
        return $this->attemptLogin($request, 'user');
    }

    protected function attemptLogin(Request $request, string $allowedRole)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials.',
            ]);
        }

        $user = Auth::user();

        if ($user->role !== $allowedRole) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'You are not authorized to log in here.',
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $user]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
