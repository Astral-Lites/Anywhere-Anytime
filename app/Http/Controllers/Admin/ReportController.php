<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;

class ReportController extends Controller
{
    /** Accessibility gap report. Only approved facilities are counted. */
    public function index()
    {
        $facilities = Facility::approved()->with('floors')->orderBy('name')->get();
        $total = $facilities->count();

        // For each feature: how many approved facilities do not have it.
        $gaps = [];
        foreach (Facility::FEATURES as $field => $label) {
            $missing = $facilities->filter(fn ($f) => ! $f->$field)->values();
            $gaps[$field] = [
                'label' => $label,
                'missing' => $missing,
                'count' => $missing->count(),
                'percent' => $total ? (int) round($missing->count() / $total * 100) : 0,
            ];
        }

        // Gaps by category: average accessibility score and number of facilities.
        $byCategory = [];
        foreach (Facility::CATEGORIES as $category) {
            $group = $facilities->where('category', $category);
            if ($group->isNotEmpty()) {
                $byCategory[$category] = [
                    'count' => $group->count(),
                    'average' => (int) round($group->avg('accessibility_score')),
                ];
            }
        }

        // Multi-storey facilities where an upper floor can only be reached by stairs.
        $stairsOnly = $facilities->filter(
            fn ($f) => $f->floors->contains(fn ($floor) => $floor->level !== 0 && $floor->access === 'stairs')
        )->values();

        // Facilities that are missing coordinates cannot appear on the map.
        $unmapped = $facilities->filter(fn ($f) => $f->latitude === null || $f->longitude === null)->values();

        $lowest = $facilities->sortBy('accessibility_score')->take(5)->values();

        return view('admin.reports.index', compact('total', 'gaps', 'byCategory', 'stairsOnly', 'unmapped', 'lowest'));
    }

    /** Download the approved facilities as a CSV file (opens in Excel). */
    public function export()
    {
        $facilities = Facility::approved()->with('floors')->orderBy('name')->get();

        return response()->streamDownload(function () use ($facilities) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 correctly

            fputcsv($out, array_merge(
                ['Name', 'Category', 'Address', 'Latitude', 'Longitude', 'Accessibility score (%)'],
                array_values(Facility::FEATURES),
                ['Floors', 'Opening hours', 'Contact']
            ));

            foreach ($facilities as $f) {
                $row = [$f->name, $f->category, $f->address, $f->latitude, $f->longitude, $f->accessibility_score];
                foreach (array_keys(Facility::FEATURES) as $field) {
                    $row[] = $f->$field ? 'Yes' : 'No';
                }
                $row[] = $f->floors->count();
                $row[] = $f->hours;
                $row[] = $f->contact;
                fputcsv($out, $row);
            }

            fclose($out);
        }, 'accessibility-report-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
