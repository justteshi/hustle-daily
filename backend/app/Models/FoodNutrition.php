<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class FoodNutrition extends Model
{
    use HasUuids;

    protected $table = 'food_nutrition';

    protected $fillable = ['basis_amount', 'basis_unit', 'calories', 'protein', 'carbohydrates', 'fat', 'fibre', 'sugar', 'salt'];

    protected static function booted(): void
    {
        static::saving(function (FoodNutrition $nutrition): void {
            if ($nutrition->food()->firstOrFail()->portions()->where('base_unit', '!=', $nutrition->basis_unit)->exists()) {
                throw ValidationException::withMessages(['basis_unit' => 'Remove or update incompatible food portions before changing the nutrition basis unit.']);
            }
            if (RecipeIngredient::where('food_id', $nutrition->food_id)->where('unit', '!=', 'portion')->where('unit', '!=', $nutrition->basis_unit)->exists()) {
                throw ValidationException::withMessages(['basis_unit' => 'Update recipes using this food before changing its nutrition basis unit.']);
            }
        });
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
