<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TargetValue extends Model
{
    use HasUuids;

    protected $fillable = ['target_profile_id', 'metric_code', 'target_value', 'unit', 'comparison_rule'];
}
