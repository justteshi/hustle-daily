<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DailySummaryRevision extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'actor_user_id', 'action', 'before_state', 'after_state', 'idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array'];
    }
}
