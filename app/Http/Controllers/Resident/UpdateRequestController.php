<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityUpdateRequest;
use App\Support\FacilityInput;
use Illuminate\Http\Request;

class UpdateRequestController extends Controller
{
    /** Form pre-filled with the current public record. Any resident may propose a change. */
    public function create(Facility $facility)
    {
        abort_unless($facility->status === 'approved', 404);

        $facility->load('floors');
        $hasPending = $this->pendingRequest($facility, auth()->id()) !== null;

        return view('resident.update-requests.create', compact('facility', 'hasPending'));
    }

    public function store(Request $request, Facility $facility)
    {
        abort_unless($facility->status === 'approved', 404);

        if ($this->pendingRequest($facility, $request->user()->id)) {
            return redirect()
                ->route('resident.submissions.index')
                ->withErrors(['update' => 'You already have a pending update request for this facility. Wait for the review first.']);
        }

        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $proposed = FacilityInput::validated($request);

        $facility->load('floors');
        if (FacilityUpdateRequest::compare($facility->snapshot(), $proposed) === []) {
            return back()
                ->withInput()
                ->withErrors(['reason' => 'Nothing was changed. Change at least one detail before sending an update request.']);
        }

        FacilityUpdateRequest::create([
            'facility_id' => $facility->id,
            'user_id' => $request->user()->id,
            'changes' => $proposed,
            'reason' => trim($request->input('reason')),
            'status' => 'pending',
        ]);

        return redirect()
            ->route('resident.submissions.index')
            ->with('status', 'Your update request was sent. The facility stays as it is until an admin approves the change.');
    }

    private function pendingRequest(Facility $facility, int $userId): ?FacilityUpdateRequest
    {
        return FacilityUpdateRequest::where('facility_id', $facility->id)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->first();
    }
}
