<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class FoodPortion extends Model
{
    use HasUuids;

    protected $fillable = ['label', 'base_amount', 'base_unit'];

    protected static function booted(): void
    {
        static::saving(function (FoodPortion $portion): void {
            if ($portion->base_unit !== $portion->food()->firstOrFail()->nutrition()->firstOrFail()->basis_unit) {
                throw ValidationException::withMessages(['base_unit' => 'Portion unit must match the food nutrition basis.']);
            }
        });
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
