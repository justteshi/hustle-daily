<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealCategory extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'key', 'name', 'sort_order', 'is_active'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
