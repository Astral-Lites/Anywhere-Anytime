<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Support\FacilityInput;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    /** Admins can correct any facility record directly. */
    public function edit(Facility $facility)
    {
        $facility->load('floors');

        return view('admin.facilities.edit', compact('facility'));
    }

    public function update(Request $request, Facility $facility)
    {
        FacilityInput::save($facility, FacilityInput::validated($request));

        return redirect()
            ->route('admin.submissions.index', ['status' => $facility->status])
            ->with('status', "\"{$facility->name}\" was updated.");
    }
}
