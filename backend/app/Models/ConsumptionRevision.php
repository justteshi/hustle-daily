<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['user_id', 'actor_user_id', 'revision_number', 'reason', 'before_state', 'after_state', 'idempotency_key', 'request_fingerprint', 'created_at'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'created_at' => 'datetime'];
    }

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(Consumption::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
