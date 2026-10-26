<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayType extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'key', 'name', 'color', 'is_active', 'sort_order'];

    public function targetProfiles(): HasMany
    {
        return $this->hasMany(TargetProfile::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DayAssignment::class);
    }
}
