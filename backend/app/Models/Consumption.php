<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consumption extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['local_date', 'eaten_at', 'meal_category_id', 'notes', 'idempotency_key', 'request_fingerprint', 'calories', 'protein', 'carbohydrates', 'fat', 'fibre', 'sugar', 'salt', 'source_type', 'source_id', 'source_name', 'source_quantity', 'source_unit'];

    protected function casts(): array
    {
        return ['eaten_at' => 'datetime', 'local_date' => 'date:Y-m-d'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mealCategory(): BelongsTo
    {
        return $this->belongsTo(MealCategory::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsumptionItem::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ConsumptionRevision::class);
    }
}
