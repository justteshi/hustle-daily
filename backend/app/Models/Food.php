<?php

namespace App\Models;

use Database\Factories\FoodFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Food extends Model
{
    /** @use HasFactory<FoodFactory> */
    use HasFactory, HasUuids;

    protected $table = 'foods';

    protected $fillable = ['user_id', 'name', 'brand', 'barcode', 'is_archived', 'idempotency_key', 'request_fingerprint'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nutrition(): HasOne
    {
        return $this->hasOne(FoodNutrition::class);
    }

    public function portions(): HasMany
    {
        return $this->hasMany(FoodPortion::class);
    }
}
