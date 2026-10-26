<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DayAssignment extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'local_date', 'day_type_id', 'source'];
}
