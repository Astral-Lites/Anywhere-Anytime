<?php

namespace App\Support;

use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * One place for the facility form rules, used by every form that edits facility
 * information (new submission, edit, update request, admin edit).
 * Changing a field in one place changes it everywhere.
 */
class FacilityInput
{
    /** Validate the request and return a normalized snapshot-shaped array. */
    public static function validated(Request $request): array
    {
        self::dropEmptyFloorRows($request);

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(Facility::CATEGORIES)],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'hours' => ['nullable', 'string', 'max:120'],
            'contact' => ['nullable', 'string', 'max:60'],
            'surface' => ['nullable', Rule::in(Facility::SURFACES)],
            'slope' => ['nullable', Rule::in(Facility::SLOPES)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => [Rule::in(array_keys(Facility::AMENITIES))],
            'floors' => ['nullable', 'array', 'max:12'],
            'floors.*.level' => ['required', 'integer', 'between:-5,60'],
            'floors.*.label' => ['required', 'string', 'max:60'],
            'floors.*.access' => ['required', Rule::in(array_keys(Facility::FLOOR_ACCESS))],
            'floors.*.services' => ['nullable', 'string', 'max:255'],
        ];

        foreach (array_keys(Facility::FEATURES) as $feature) {
            $rules[$feature] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules, [
            'floors.*.level.required' => 'Each floor needs a level number (0 is the ground floor).',
            'floors.*.label.required' => 'Each floor needs a name, such as "Ground floor".',
        ]);

        $levels = array_column($data['floors'] ?? [], 'level');
        if (count($levels) !== count(array_unique($levels))) {
            throw ValidationException::withMessages(['floors' => 'Each floor level can only be listed once.']);
        }

        $snapshot = [
            'name' => trim($data['name']),
            'category' => $data['category'],
            'address' => trim($data['address']),
            'latitude' => round((float) $data['latitude'], 7),
            'longitude' => round((float) $data['longitude'], 7),
            'hours' => trim((string) ($data['hours'] ?? '')),
            'contact' => trim((string) ($data['contact'] ?? '')),
            'surface' => (string) ($data['surface'] ?? ''),
            'slope' => (string) ($data['slope'] ?? ''),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];

        foreach (array_keys(Facility::FEATURES) as $feature) {
            $snapshot[$feature] = $request->boolean($feature);
        }

        $amenities = array_values(array_unique($data['amenities'] ?? []));
        sort($amenities);
        $snapshot['amenities'] = $amenities;

        $floors = array_map(fn ($floor) => [
            'level' => (int) $floor['level'],
            'label' => trim($floor['label']),
            'access' => $floor['access'],
            'services' => trim((string) ($floor['services'] ?? '')),
        ], $data['floors'] ?? []);
        usort($floors, fn ($a, $b) => $a['level'] <=> $b['level']);
        $snapshot['floors'] = $floors;

        return $snapshot;
    }

    /** Save a snapshot-shaped array to a facility (columns and floors). */
    public static function save(Facility $facility, array $snapshot): void
    {
        $floors = $snapshot['floors'];
        unset($snapshot['floors']);

        // Empty optional text is stored as NULL.
        foreach (['hours', 'contact', 'surface', 'slope', 'notes'] as $field) {
            if (($snapshot[$field] ?? '') === '') {
                $snapshot[$field] = null;
            }
        }

        $facility->fill($snapshot)->save();

        $facility->floors()->delete();
        foreach ($floors as $floor) {
            $facility->floors()->create($floor);
        }
    }

    /** Rows the user added but left completely blank are ignored. */
    private static function dropEmptyFloorRows(Request $request): void
    {
        $floors = $request->input('floors');

        if (! is_array($floors)) {
            return;
        }

        $kept = array_values(array_filter($floors, function ($row) {
            return is_array($row)
                && (trim((string) ($row['label'] ?? '')) !== '' || trim((string) ($row['services'] ?? '')) !== '');
        }));

        $request->merge(['floors' => $kept]);
    }
}
