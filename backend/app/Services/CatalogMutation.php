<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogMutation
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  Closure(): array<string, mixed>  $apply
     * @return array<string, mixed>
     */
    public function update(Request $request, string $resourceType, string $resourceId, array $validated, Closure $apply): array
    {
        $key = $request->header('Idempotency-Key');
        if (! $key || mb_strlen($key) > 120) {
            throw ValidationException::withMessages(['idempotency_key' => ['An Idempotency-Key header of at most 120 characters is required.']]);
        }
        $fingerprint = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $resourceType, $resourceId, $apply, $key, $fingerprint): array {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('catalog_mutation_keys')
                ->where('user_id', $request->user()->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_if(
                    $existing->resource_type !== $resourceType || $existing->resource_id !== $resourceId || $existing->request_fingerprint !== $fingerprint,
                    409,
                    'Idempotency key was already used with different data.',
                );

                return json_decode($existing->response_data, true, 512, JSON_THROW_ON_ERROR);
            }

            $response = $apply();
            DB::table('catalog_mutation_keys')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'response_data' => json_encode($response, JSON_THROW_ON_ERROR),
                'created_at' => now()->utc(),
            ]);

            return $response;
        });
    }
}
