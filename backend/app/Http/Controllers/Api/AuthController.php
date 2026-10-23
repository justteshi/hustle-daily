<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Sign in and create a mobile API token.
     *
     * Send email, password, and a device name. There is no public registration endpoint. Browser clients use the session login instead.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        return response()->json(['data' => [
            'token' => $user->createToken($credentials['device_name'])->plainTextToken,
            'user' => $this->userData($user),
        ]]);
    }

    /**
     * Get the signed-in user.
     *
     * Returns the account and its locale/time-zone settings. Requires a valid Sanctum token or browser session.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userData($request->user())]);
    }

    /**
     * Revoke the current mobile API token.
     *
     * Only the token used for this request is revoked; other devices stay signed in.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['logged_out' => true]]);
    }

    private function userData(User $user): array
    {
        $user->loadMissing('settings');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'settings' => $user->settings ? $user->settings->only(['locale', 'time_zone']) : null,
        ];
    }
}
