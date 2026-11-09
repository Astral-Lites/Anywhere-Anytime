<?php

use App\Http\Controllers\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\UpdateRequestController as AdminUpdateRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\Resident\SubmissionController;
use App\Http\Controllers\Resident\UpdateRequestController;
use Illuminate\Support\Facades\Route;

// The home URL sends everyone to /dashboard.
// Logged-out visitors are then redirected to the login page by the "auth" middleware.
Route::get('/', fn () => redirect()->route('dashboard'));

// Pages only for logged-out visitors (logged-in users are sent to /dashboard).
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Pages for any logged-in user. Logged-out visitors are redirected to /login.
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/facilities', [DirectoryController::class, 'index'])->name('directory');

    // Resident pages. The "role:resident" middleware blocks every other role with a 403 page.
    Route::middleware('role:resident')->prefix('resident')->name('resident.')->group(function () {
        Route::get('/home', [DashboardController::class, 'resident'])->name('home');

        // Submit, view, edit (pending or rejected only), delete (pending or rejected only).
        Route::get('/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/create', [SubmissionController::class, 'create'])->name('submissions.create');
        Route::post('/submissions', [SubmissionController::class, 'store'])->name('submissions.store');
        Route::get('/submissions/{facility}/edit', [SubmissionController::class, 'edit'])->name('submissions.edit');
        Route::put('/submissions/{facility}', [SubmissionController::class, 'update'])->name('submissions.update');
        Route::delete('/submissions/{facility}', [SubmissionController::class, 'destroy'])->name('submissions.destroy');

        // Approved facilities cannot be edited directly. Residents send an update request.
        Route::get('/facilities/{facility}/update-request', [UpdateRequestController::class, 'create'])->name('update-requests.create');
        Route::post('/facilities/{facility}/update-request', [UpdateRequestController::class, 'store'])->name('update-requests.store');
    });

    // Admin pages. The "role:admin" middleware blocks every other role with a 403 page.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/home', [DashboardController::class, 'admin'])->name('home');
        Route::get('/submissions', [ReviewController::class, 'index'])->name('submissions.index');
        Route::patch('/submissions/{facility}', [ReviewController::class, 'update'])->name('submissions.update');

        Route::get('/facilities/{facility}/edit', [AdminFacilityController::class, 'edit'])->name('facilities.edit');
        Route::put('/facilities/{facility}', [AdminFacilityController::class, 'update'])->name('facilities.update');

        Route::get('/update-requests', [AdminUpdateRequestController::class, 'index'])->name('update-requests.index');
        Route::patch('/update-requests/{updateRequest}', [AdminUpdateRequestController::class, 'update'])->name('update-requests.update');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });
});
