<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityUpdateRequest extends Model
{
    protected $fillable = [
        'facility_id',
        'user_id',
        'changes',
        'reason',
        'status',
        'admin_remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Fields where the proposed value differs from the facility as it is now.
     * Returns [field => ['old' => ..., 'new' => ...]] with readable text.
     */
    public function differences(): array
    {
        $current = $this->facility->snapshot();
        $proposed = $this->changes ?? [];
        $diff = [];

        foreach (self::compare($current, $proposed) as $key) {
            $diff[$key] = [
                'label' => Facility::FIELD_LABELS[$key] ?? $key,
                'old' => Facility::formatValue($key, $current[$key] ?? null),
                'new' => Facility::formatValue($key, $proposed[$key] ?? null),
            ];
        }

        return $diff;
    }

    /** Keys whose values differ between two snapshots. */
    public static function compare(array $current, array $proposed): array
    {
        $keys = [];

        foreach ($proposed as $key => $value) {
            if (($current[$key] ?? null) != $value) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
