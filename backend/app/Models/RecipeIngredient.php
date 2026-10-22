<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class RecipeIngredient extends Model
{
    use HasUuids;

    protected $fillable = ['food_id', 'food_portion_id', 'quantity', 'unit', 'sort_order'];

    protected static function booted(): void
    {
        static::saving(function (RecipeIngredient $ingredient): void {
            $recipe = Recipe::findOrFail($ingredient->recipe_id);
            $food = Food::with('nutrition')->findOrFail($ingredient->food_id);
            if ($recipe->user_id !== $food->user_id) {
                throw ValidationException::withMessages(['food_id' => 'Ingredient food must belong to the recipe owner.']);
            }
            if ($ingredient->unit === 'portion') {
                $portion = FoodPortion::find($ingredient->food_portion_id);
                if (! $portion || $portion->food_id !== $food->id || $portion->base_unit !== $food->nutrition?->basis_unit) {
                    throw ValidationException::withMessages(['food_portion_id' => 'Choose a portion belonging to this food with a matching basis unit.']);
                }
            } elseif ($ingredient->unit !== $food->nutrition?->basis_unit || $ingredient->food_portion_id) {
                throw ValidationException::withMessages(['unit' => 'Ingredient unit must match the food nutrition basis.']);
            }
        });
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function portion(): BelongsTo
    {
        return $this->belongsTo(FoodPortion::class, 'food_portion_id');
    }
}
