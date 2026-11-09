<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\FacilityUpdateRequest;
use App\Models\User;

class DashboardController extends Controller
{
    /** Send each role to its own home page after login. */
    public function index()
    {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.home')
            : redirect()->route('resident.home');
    }

    public function resident()
    {
        $user = auth()->user();

        $counts = [
            'total' => $user->facilities()->count(),
            'pending' => $user->facilities()->where('status', 'pending')->count(),
            'approved' => $user->facilities()->where('status', 'approved')->count(),
            'rejected' => $user->facilities()->where('status', 'rejected')->count(),
            'updates' => $user->updateRequests()->where('status', 'pending')->count(),
        ];

        $recent = $user->facilities()->latest()->take(5)->get();

        return view('resident.home', compact('counts', 'recent'));
    }

    public function admin()
    {
        $counts = [
            'pending' => Facility::where('status', 'pending')->count(),
            'approved' => Facility::where('status', 'approved')->count(),
            'rejected' => Facility::where('status', 'rejected')->count(),
            'residents' => User::where('role', 'resident')->count(),
            'updates' => FacilityUpdateRequest::where('status', 'pending')->count(),
        ];

        return view('admin.home', compact('counts'));
    }
}
