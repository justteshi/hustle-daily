<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TargetProfile extends Model
{
    use HasUuids;

    protected $fillable = ['day_type_id', 'effective_from', 'effective_to', 'notes'];

    public function values(): HasMany
    {
        return $this->hasMany(TargetValue::class);
    }
}
