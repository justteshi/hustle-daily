<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeviceController extends Controller
{
    /**
     * Get one of your registered devices.
     *
     * Returns its name, platform, and last-seen time. Another user's device is not visible.
     */
    public function show(Request $request, string $device): JsonResponse
    {
        $record = $request->user()->devices()->findOrFail($device);
        Gate::authorize('view', $record);

        return response()->json(['data' => $record->only(['id', 'name', 'platform', 'last_seen_at'])]);
    }
}
