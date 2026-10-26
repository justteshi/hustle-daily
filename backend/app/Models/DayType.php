<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DayType extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'key', 'name', 'color', 'is_active', 'sort_order'];

    public function targetProfile(): HasOne
    {
        return $this->hasOne(TargetProfile::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DayAssignment::class);
    }
}
