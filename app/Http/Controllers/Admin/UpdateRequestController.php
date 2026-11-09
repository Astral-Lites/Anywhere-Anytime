<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityUpdateRequest;
use App\Support\FacilityInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        if (! in_array($status, Facility::STATUSES, true)) {
            $status = 'pending';
        }

        $requests = FacilityUpdateRequest::with(['facility.floors', 'requester'])
            ->where('status', $status)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $counts = FacilityUpdateRequest::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.update-requests.index', compact('requests', 'status', 'counts'));
    }

    /** Approve (apply the change to the public record) or reject (leave it as it is). */
    public function update(Request $request, FacilityUpdateRequest $updateRequest)
    {
        abort_unless($updateRequest->status === 'pending', 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'admin_remarks' => ['nullable', 'string', 'max:500', 'required_if:status,rejected'],
        ]);

        DB::transaction(function () use ($request, $updateRequest, $data) {
            if ($data['status'] === 'approved') {
                $facility = $updateRequest->facility;
                FacilityInput::save($facility, $updateRequest->changes);
                $facility->update(['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            }

            $updateRequest->update([
                'status' => $data['status'],
                'admin_remarks' => $data['admin_remarks'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('status', $data['status'] === 'approved'
            ? 'The update was approved and the facility record is now changed.'
            : 'The update request was rejected.');
    }
}
