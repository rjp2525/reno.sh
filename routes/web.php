<?php

use App\Http\Controllers\About\HobbiesController;
use App\Http\Controllers\About\PersonalController;
use App\Http\Controllers\About\ProfessionalController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\ProjectShowController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomepageController::class)
    ->name('home');

// About pages with URL-trackable tabs
Route::prefix('about')->name('about.')->group(function () {
    Route::get('/', fn () => redirect()->route('about.professional'));
    Route::get('/professional/{tab?}', ProfessionalController::class)->name('professional');
    Route::get('/personal/{tab?}', PersonalController::class)->name('personal');
    Route::get('/hobbies/{tab?}', HobbiesController::class)->name('hobbies');
});
Route::get('/projects', ProjectsController::class)
    ->name('projects');
Route::get('/projects/{project:slug}', ProjectShowController::class)
    ->name('projects.show');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
