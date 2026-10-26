<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DayTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DayController extends Controller
{
    public function update(Request $request, string $date, DayTargets $targets): JsonResponse
    {
        $validDate = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        abort_unless($validDate, 404);
        $data = $request->validate(['day_type_id' => ['required', 'uuid']]);
        $targets->ensureDefaults($request->user());
        $type = $request->user()->dayTypes()->findOrFail($data['day_type_id']);
        $assignment = $request->user()->dayAssignments()->updateOrCreate(['local_date' => $date], [
            'day_type_id' => $type->id, 'source' => 'manual',
        ]);

        return response()->json(['data' => [
            'local_date' => $date,
            'source' => $assignment->source,
            'day_type' => $targets->serialize($type, $date),
        ]]);
    }
}
