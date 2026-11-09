<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityFloor extends Model
{
    protected $fillable = ['facility_id', 'level', 'label', 'access', 'services'];

    protected function casts(): array
    {
        return ['level' => 'integer'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
