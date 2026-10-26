<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        $user = $request->user()->load('settings', 'devices');

        return response()->json(['data' => [
            'exported_at' => now()->utc()->toIso8601String(),
            'account' => $user->only(['id', 'name', 'email', 'created_at']),
            'settings' => $user->settings?->only(['locale', 'time_zone']),
            'devices' => $user->devices->map->only(['id', 'name', 'platform', 'last_seen_at'])->all(),
            'meal_categories' => $user->mealCategories()->orderBy('sort_order')->get()->toArray(),
            'foods' => $user->foods()->with(['nutrition', 'portions'])->get()->toArray(),
            'recipes' => $user->recipes()->with('ingredients')->get()->toArray(),
            'consumptions' => $user->consumptions()->withTrashed()->with(['items', 'revisions'])->orderBy('eaten_at')->get()->toArray(),
            'day_types' => $user->dayTypes()->with('targetProfile.values')->orderBy('sort_order')->get()->toArray(),
            'day_assignments' => $user->dayAssignments()->orderBy('local_date')->get()->toArray(),
        ]])->header('Cache-Control', 'private, no-store')
            ->header('Content-Disposition', 'attachment; filename="hustle-daily-data.json"');
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['The password is incorrect.']]);
        }

        DB::transaction(function () use ($user): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->recipes()->each(function ($recipe): void {
                $recipe->ingredients()->delete();
            });
            $locked->consumptions()->withTrashed()->each(function ($consumption): void {
                $consumption->revisions()->delete();
            });
            DB::table('sessions')->where('user_id', $locked->id)->delete();
            DB::table('password_reset_tokens')->where('email', $locked->email)->delete();
            $locked->delete();
        });

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
