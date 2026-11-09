<?php

namespace App\Http\Controllers;

use App\Models\Facility;

class DirectoryController extends Controller
{
    /**
     * The explore map. Both roles can open it.
     * All approved facilities are sent to the page once, and the browser filters them
     * instantly (search, category, features, "my needs", distance) without reloading.
     */
    public function index()
    {
        $facilities = Facility::approved()
            ->with('floors')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get();

        $points = $facilities->map(function (Facility $f) {
            $features = [];
            foreach (array_keys(Facility::FEATURES) as $field) {
                $features[$field] = (bool) $f->$field;
            }

            return [
                'id' => $f->id,
                'name' => $f->name,
                'category' => $f->category,
                'address' => $f->address,
                'lat' => $f->latitude,
                'lng' => $f->longitude,
                'features' => $features,
                'amenities' => array_values($f->amenities ?? []),
                'score' => $f->accessibility_score,
                'level' => $f->access_level,
                'hours' => $f->hours,
                'contact' => $f->contact,
                'surface' => $f->surface,
                'slope' => $f->slope,
                'notes' => $f->notes,
                'verified' => optional($f->reviewed_at)->format('M d, Y'),
                'floors' => $f->floors->map(fn ($floor) => [
                    'level' => $floor->level,
                    'label' => $floor->label,
                    'access' => $floor->access,
                    'services' => $floor->services,
                ])->values(),
                'updateUrl' => auth()->user()->isAdmin() ? null : route('resident.update-requests.create', $f),
            ];
        })->values();

        $config = [
            'categories' => Facility::CATEGORIES,
            'features' => Facility::FEATURES,
            'featureIcons' => Facility::FEATURE_ICONS,
            'amenities' => Facility::AMENITIES,
            'floorAccess' => Facility::FLOOR_ACCESS,
            'needs' => Facility::NEEDS,
            'center' => [15.4869, 120.9734],
        ];

        return view('directory.index', compact('points', 'config'));
    }
}
