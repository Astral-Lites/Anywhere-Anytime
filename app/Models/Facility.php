<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    /** Edit this list to match the categories in your capstone. */
    public const CATEGORIES = [
        'Health',
        'Education',
        'Government',
        'Commercial',
        'Religious',
        'Recreation',
        'Transportation',
        'Other',
    ];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    /** Database column => label shown to users. */
    public const FEATURES = [
        'has_ramp' => 'Ramp',
        'has_accessible_entrance' => 'Accessible entrance',
        'has_accessible_restroom' => 'Accessible restroom',
        'has_accessible_parking' => 'Accessible parking',
        'has_elevator' => 'Elevator',
        'has_wide_doorways' => 'Wide doorways',
        'has_handrails' => 'Handrails',
        'has_tactile_paths' => 'Tactile paths',
        'has_accessible_signage' => 'Accessible signage',
        'has_priority_lane' => 'Priority lane or counter',
    ];

    /** Short symbol for each feature (used in map markers and cards). */
    public const FEATURE_ICONS = [
        'has_ramp' => '♿',
        'has_accessible_entrance' => '🚪',
        'has_accessible_restroom' => '🚻',
        'has_accessible_parking' => '🅿️',
        'has_elevator' => '🛗',
        'has_wide_doorways' => '↔️',
        'has_handrails' => '🤝',
        'has_tactile_paths' => '🦯',
        'has_accessible_signage' => '🪧',
        'has_priority_lane' => '⭐',
    ];

    /** Extra services a facility can offer: key => label. */
    public const AMENITIES = [
        'wheelchair_loan' => 'Wheelchair available',
        'sign_language' => 'Sign language support',
        'guide_dog' => 'Guide dogs welcome',
        'priority_seating' => 'Priority seating',
        'free_entry' => 'Free entry',
        'weekend_open' => 'Open on weekends',
        'drinking_water' => 'Drinking water',
        'assistance_staff' => 'Staff can assist visitors',
    ];

    public const SURFACES = ['Smooth concrete or tiles', 'Paved with some cracks', 'Gravel or sand', 'Grass or dirt', 'Mixed'];

    public const SLOPES = ['Flat', 'Gentle slope', 'Steep slope'];

    /** How people reach a floor: key => label. */
    public const FLOOR_ACCESS = [
        'ground' => 'Ground level, step-free',
        'elevator' => 'Elevator',
        'lift' => 'Platform lift',
        'ramp' => 'Ramp',
        'stairs' => 'Stairs only',
    ];

    /**
     * Accessibility "profiles". A user picks the one that fits them and the map highlights
     * the facilities that provide the features that profile needs.
     */
    public const NEEDS = [
        'wheelchair' => [
            'label' => 'Wheelchair user',
            'features' => ['has_ramp', 'has_accessible_entrance', 'has_accessible_restroom', 'has_accessible_parking', 'has_elevator', 'has_wide_doorways'],
            'amenities' => [],
        ],
        'mobility' => [
            'label' => 'Walking aid or limited mobility',
            'features' => ['has_ramp', 'has_handrails', 'has_accessible_entrance', 'has_accessible_parking', 'has_elevator'],
            'amenities' => ['priority_seating'],
        ],
        'vision' => [
            'label' => 'Blind or low vision',
            'features' => ['has_tactile_paths', 'has_accessible_signage', 'has_handrails', 'has_priority_lane'],
            'amenities' => ['guide_dog', 'assistance_staff'],
        ],
        'hearing' => [
            'label' => 'Deaf or hard of hearing',
            'features' => ['has_accessible_signage', 'has_priority_lane'],
            'amenities' => ['sign_language', 'assistance_staff'],
        ],
        'senior' => [
            'label' => 'Senior or with a stroller',
            'features' => ['has_ramp', 'has_elevator', 'has_accessible_restroom', 'has_handrails', 'has_accessible_parking'],
            'amenities' => ['priority_seating', 'drinking_water'],
        ],
    ];

    /**
     * One-click presets for the submission form. They only pre-fill the form;
     * the resident can change every value before submitting.
     */
    public const PRESETS = [
        'basic' => [
            'label' => 'Basic access (ramp and entrance)',
            'category' => null,
            'features' => ['has_ramp', 'has_accessible_entrance'],
            'amenities' => [],
            'floors' => [['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => '']],
        ],
        'full' => [
            'label' => 'Fully accessible building',
            'category' => null,
            'features' => ['has_ramp', 'has_accessible_entrance', 'has_accessible_restroom', 'has_accessible_parking', 'has_elevator', 'has_wide_doorways', 'has_handrails', 'has_tactile_paths', 'has_accessible_signage', 'has_priority_lane'],
            'amenities' => ['assistance_staff', 'priority_seating'],
            'floors' => [
                ['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => ''],
                ['level' => 1, 'label' => 'Second floor', 'access' => 'elevator', 'services' => ''],
            ],
        ],
        'health' => [
            'label' => 'Barangay health center',
            'category' => 'Health',
            'features' => ['has_ramp', 'has_accessible_entrance', 'has_wide_doorways', 'has_priority_lane'],
            'amenities' => ['priority_seating', 'assistance_staff'],
            'hours' => 'Mon to Fri, 8:00 AM to 5:00 PM',
            'floors' => [['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => 'Consultation room, pharmacy, waiting area']],
        ],
        'school' => [
            'label' => 'Public school',
            'category' => 'Education',
            'features' => ['has_ramp', 'has_accessible_entrance', 'has_handrails', 'has_accessible_restroom'],
            'amenities' => ['assistance_staff'],
            'hours' => 'Mon to Fri, 7:00 AM to 5:00 PM',
            'floors' => [
                ['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => 'Principal\'s office, library, classrooms'],
                ['level' => 1, 'label' => 'Second floor', 'access' => 'stairs', 'services' => 'Classrooms'],
            ],
        ],
        'hall' => [
            'label' => 'Barangay or municipal hall',
            'category' => 'Government',
            'features' => ['has_ramp', 'has_accessible_entrance', 'has_accessible_signage', 'has_priority_lane'],
            'amenities' => ['priority_seating', 'assistance_staff'],
            'hours' => 'Mon to Fri, 8:00 AM to 5:00 PM',
            'floors' => [['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => 'Records, cashier, PWD desk']],
        ],
        'market' => [
            'label' => 'Public market or store',
            'category' => 'Commercial',
            'features' => ['has_accessible_entrance', 'has_wide_doorways'],
            'amenities' => ['weekend_open'],
            'floors' => [['level' => 0, 'label' => 'Ground floor', 'access' => 'ground', 'services' => '']],
        ],
        'church' => [
            'label' => 'Church or place of worship',
            'category' => 'Religious',
            'features' => ['has_ramp', 'has_accessible_entrance', 'has_wide_doorways'],
            'amenities' => ['weekend_open', 'free_entry', 'priority_seating'],
            'floors' => [['level' => 0, 'label' => 'Main hall', 'access' => 'ramp', 'services' => 'Worship area']],
        ],
        'court' => [
            'label' => 'Covered court or park',
            'category' => 'Recreation',
            'features' => ['has_accessible_entrance', 'has_ramp'],
            'amenities' => ['free_entry', 'drinking_water'],
            'floors' => [['level' => 0, 'label' => 'Ground level', 'access' => 'ground', 'services' => '']],
        ],
        'terminal' => [
            'label' => 'Terminal or transport stop',
            'category' => 'Transportation',
            'features' => ['has_accessible_entrance', 'has_accessible_signage', 'has_priority_lane'],
            'amenities' => ['priority_seating'],
            'floors' => [['level' => 0, 'label' => 'Ground level', 'access' => 'ground', 'services' => 'Ticketing, waiting area']],
        ],
    ];

    /** Labels for the fields that can change in an update request. */
    public const FIELD_LABELS = [
        'name' => 'Facility name',
        'category' => 'Category',
        'address' => 'Address',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'hours' => 'Opening hours',
        'contact' => 'Contact',
        'surface' => 'Approach surface',
        'slope' => 'Approach slope',
        'notes' => 'Notes',
        'amenities' => 'Services',
        'floors' => 'Floors',
    ] + self::FEATURES;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'address',
        'latitude',
        'longitude',
        'has_ramp',
        'has_accessible_entrance',
        'has_accessible_restroom',
        'has_accessible_parking',
        'has_elevator',
        'has_tactile_paths',
        'has_accessible_signage',
        'has_wide_doorways',
        'has_handrails',
        'has_priority_lane',
        'hours',
        'contact',
        'surface',
        'slope',
        'amenities',
        'notes',
        'status',
        'admin_remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        $casts = [
            'latitude' => 'float',
            'longitude' => 'float',
            'amenities' => 'array',
            'reviewed_at' => 'datetime',
        ];

        foreach (array_keys(self::FEATURES) as $feature) {
            $casts[$feature] = 'boolean';
        }

        return $casts;
    }

    /** The resident who submitted this facility. */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The admin who approved or rejected it. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function floors(): HasMany
    {
        return $this->hasMany(FacilityFloor::class)->orderBy('level');
    }

    public function updateRequests(): HasMany
    {
        return $this->hasMany(FacilityUpdateRequest::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /** Residents may edit their own facility only while it is pending or rejected. */
    public function isEditableByOwner(): bool
    {
        return in_array($this->status, ['pending', 'rejected'], true);
    }

    /** Percentage (0 to 100) of the listed accessibility features that the facility has. */
    public function getAccessibilityScoreAttribute(): int
    {
        $total = count(self::FEATURES);
        $have = 0;
        foreach (array_keys(self::FEATURES) as $feature) {
            if ($this->$feature) {
                $have++;
            }
        }

        return (int) round($have / $total * 100);
    }

    public function getAccessLevelAttribute(): string
    {
        $score = $this->accessibility_score;

        return $score >= 70 ? 'High' : ($score >= 40 ? 'Partial' : 'Limited');
    }

    /**
     * The editable content of the facility in one normalized array.
     * Used to compare a facility with a proposed update and to show the differences.
     */
    public function snapshot(): array
    {
        $data = [
            'name' => (string) $this->name,
            'category' => (string) $this->category,
            'address' => (string) $this->address,
            'latitude' => round((float) $this->latitude, 7),
            'longitude' => round((float) $this->longitude, 7),
            'hours' => (string) ($this->hours ?? ''),
            'contact' => (string) ($this->contact ?? ''),
            'surface' => (string) ($this->surface ?? ''),
            'slope' => (string) ($this->slope ?? ''),
            'notes' => (string) ($this->notes ?? ''),
        ];

        foreach (array_keys(self::FEATURES) as $feature) {
            $data[$feature] = (bool) $this->$feature;
        }

        $amenities = array_values($this->amenities ?? []);
        sort($amenities);
        $data['amenities'] = $amenities;

        $data['floors'] = $this->floors
            ->map(fn ($floor) => [
                'level' => (int) $floor->level,
                'label' => (string) $floor->label,
                'access' => (string) $floor->access,
                'services' => (string) ($floor->services ?? ''),
            ])
            ->sortBy('level')
            ->values()
            ->all();

        return $data;
    }

    /** Turn a snapshot value into readable text for the admin comparison. */
    public static function formatValue(string $key, mixed $value): string
    {
        if (array_key_exists($key, self::FEATURES)) {
            return $value ? 'Yes' : 'No';
        }

        if ($key === 'amenities') {
            $labels = array_map(fn ($k) => self::AMENITIES[$k] ?? $k, (array) $value);

            return $labels ? implode(', ', $labels) : 'None';
        }

        if ($key === 'floors') {
            $rows = array_map(function ($floor) {
                $access = self::FLOOR_ACCESS[$floor['access']] ?? $floor['access'];
                $text = "Level {$floor['level']} ({$floor['label']}): {$access}";

                return $floor['services'] !== '' ? "{$text}; {$floor['services']}" : $text;
            }, (array) $value);

            return $rows ? implode(' | ', $rows) : 'None listed';
        }

        $text = trim((string) $value);

        return $text === '' ? 'Not provided' : $text;
    }
}
