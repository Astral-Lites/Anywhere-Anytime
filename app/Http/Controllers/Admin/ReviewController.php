<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /** Review queue, filtered by status (pending is the default). */
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        if (! in_array($status, Facility::STATUSES, true)) {
            $status = 'pending';
        }

        $facilities = Facility::with(['submitter', 'floors'])
            ->where('status', $status)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $counts = Facility::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.submissions.index', compact('facilities', 'status', 'counts'));
    }

    /** Approve or reject one submission. */
    public function update(Request $request, Facility $facility)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            // A reason is required when rejecting.
            'admin_remarks' => ['nullable', 'string', 'max:500', 'required_if:status,rejected'],
        ]);

        $facility->update([
            'status' => $data['status'],
            'admin_remarks' => $data['admin_remarks'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', "\"{$facility->name}\" was {$data['status']}.");
    }
}
