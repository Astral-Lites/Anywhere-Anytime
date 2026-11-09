<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityUpdateRequest;
use App\Support\FacilityInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
    /** "My submissions": only the logged-in resident's own facilities, plus their update requests. */
    public function index(Request $request)
    {
        $facilities = $request->user()
            ->facilities()
            ->with('floors')
            ->latest()
            ->paginate(10);

        $updateRequests = FacilityUpdateRequest::with('facility')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->take(20)
            ->get();

        return view('resident.submissions.index', compact('facilities', 'updateRequests'));
    }

    public function create()
    {
        return view('resident.submissions.create');
    }

    public function store(Request $request)
    {
        // Validate on the server. Errors are sent back to the form.
        $snapshot = FacilityInput::validated($request);

        // The facility is saved under the logged-in user.
        // The status is always "pending" and is never taken from the form.
        DB::transaction(function () use ($request, $snapshot) {
            $facility = new Facility();
            $facility->user_id = $request->user()->id;
            $facility->status = 'pending';

            FacilityInput::save($facility, $snapshot);
        });

        return redirect()
            ->route('resident.submissions.index')
            ->with('status', 'Your facility was submitted and is waiting for review.');
    }

    /** Edit form. Allowed only for the owner, and only while pending or rejected. */
    public function edit(Request $request, Facility $facility)
    {
        $this->authorizeOwnerEdit($request, $facility);

        $facility->load('floors');

        return view('resident.submissions.edit', compact('facility'));
    }

    public function update(Request $request, Facility $facility)
    {
        $this->authorizeOwnerEdit($request, $facility);

        $snapshot = FacilityInput::validated($request);
        $wasRejected = $facility->status === 'rejected';

        DB::transaction(function () use ($facility, $snapshot, $wasRejected) {
            if ($wasRejected) {
                // Resubmitting a rejected facility puts it back in the review queue.
                $facility->status = 'pending';
                $facility->admin_remarks = null;
                $facility->reviewed_by = null;
                $facility->reviewed_at = null;
            }

            FacilityInput::save($facility, $snapshot);
        });

        return redirect()
            ->route('resident.submissions.index')
            ->with('status', $wasRejected
                ? "\"{$facility->name}\" was corrected and sent back for review."
                : "\"{$facility->name}\" was updated. It is still waiting for review.");
    }

    /** Withdraw a submission. Allowed only for the owner, and only while pending or rejected. */
    public function destroy(Request $request, Facility $facility)
    {
        $this->authorizeOwnerEdit($request, $facility);

        $name = $facility->name;
        $facility->delete();

        return redirect()
            ->route('resident.submissions.index')
            ->with('status', "\"{$name}\" was deleted.");
    }

    /**
     * Approved facilities are already public, so they cannot be edited directly.
     * Only the owner may edit, and only while the status is pending or rejected.
     */
    private function authorizeOwnerEdit(Request $request, Facility $facility): void
    {
        abort_unless($facility->user_id === $request->user()->id, 403);
        abort_unless($facility->isEditableByOwner(), 403, 'Approved facilities cannot be edited directly. Send an update request instead.');
    }
}
