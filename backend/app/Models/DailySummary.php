<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySummary extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'local_date', 'status', 'last_conclusion', 'concluded_at'];

    protected function casts(): array
    {
        return ['local_date' => 'date:Y-m-d', 'last_conclusion' => 'array', 'concluded_at' => 'datetime'];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DailySummaryRevision::class)->orderBy('created_at')->orderBy('id');
    }
}
