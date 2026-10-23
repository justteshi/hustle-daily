<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WebSessionController extends Controller
{
    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['data' => ['csrf_token' => $request->session()->token()]]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }
        $request->session()->regenerate();
        $request->session()->put('hustle_password_hash', Auth::user()->getAuthPassword());

        return response()->json(['data' => ['authenticated' => true]]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['logged_out' => true]]);
    }
}
