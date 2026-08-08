<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Livewire\Forms\FormBuilder;
use App\Livewire\Forms\FormView;
use App\Livewire\Forms\FormList;
use App\Livewire\Forms\FormSubmissions;

// Public home page
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('forms.index');
    }
    return redirect()->route('login');
});

// Dashboard route
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

// Public form routes (accessible without authentication)
Route::get('/f/{form}', FormView::class)->name('forms.show');
Route::post('/f/{form}/submit', [FormController::class, 'submit'])->name('forms.submit');

// Protected routes - require authentication
Route::middleware(['auth'])->group(function () {
    
    // Form management routes with permissions
    Route::middleware(['permission:view forms'])->group(function () {
        Route::get('/forms', FormList::class)->name('forms.index');
    });
    
    Route::middleware(['permission:create forms'])->group(function () {
        Route::get('/forms/create', FormBuilder::class)->name('forms.create');
        Route::get('/forms/import', \App\Livewire\Forms\FormImporter::class)->name('forms.import');
    });
    
    Route::middleware(['permission:edit forms'])->group(function () {
        Route::get('/forms/{form}/edit', FormBuilder::class)->name('forms.edit');
    });
    
    Route::middleware(['permission:delete forms'])->group(function () {
        Route::delete('/forms/{form}', [FormController::class, 'destroy'])->name('forms.destroy');
    });
    
    Route::middleware(['permission:publish forms'])->group(function () {
        Route::post('/forms/{form}/toggle-publish', [FormController::class, 'togglePublish'])->name('forms.toggle-publish');
    });
    
    Route::middleware(['permission:duplicate forms'])->group(function () {
        Route::post('/forms/{form}/duplicate', [FormController::class, 'duplicate'])->name('forms.duplicate');
    });
    
    // Submission routes with permissions
    Route::middleware(['permission:view submissions'])->group(function () {
        Route::get('/forms/{form}/submissions', FormSubmissions::class)->name('forms.submissions');
    });
    
    Route::middleware(['permission:export submissions'])->group(function () {
        Route::get('/forms/{form}/submissions/export', [FormController::class, 'exportSubmissions'])->name('forms.submissions.export');
    });
    
    Route::middleware(['permission:delete submissions'])->group(function () {
        Route::delete('/forms/{form}/submissions/{submission}', [FormController::class, 'deleteSubmission'])->name('forms.submissions.delete');
    });
    
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
// Admin routes - only for super-admin
Route::middleware(['auth', 'permission:manage permissions'])->prefix('admin')->group(function () {
    Route::get('/permissions', \App\Livewire\Admin\PermissionManager::class)->name('admin.permissions');
});
// Include authentication routes
require __DIR__.'/auth.php';