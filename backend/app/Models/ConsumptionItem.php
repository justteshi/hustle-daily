<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionItem extends Model
{
    use HasUuids;

    protected $fillable = ['food_id', 'food_name', 'brand', 'quantity', 'unit', 'input_unit', 'food_portion_id', 'nutrition_snapshot', 'calories', 'protein', 'carbohydrates', 'fat', 'fibre', 'sugar', 'salt'];

    protected function casts(): array
    {
        return ['nutrition_snapshot' => 'array'];
    }

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(Consumption::class);
    }
}
