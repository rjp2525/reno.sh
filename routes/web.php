<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RawScreenshotController;
use App\Http\Controllers\ScreenshotController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$domainData = when(app()->isProduction(), ['domain' => 'i.reno.sh'], ['prefix' => 'i']);
Route::group($domainData, function () {
    Route::get('/{screenshot:id}', ScreenshotController::class)
        ->name('screenshot');
    Route::get('/{screenshot:id}/raw', RawScreenshotController::class)
        ->name('screenshot.raw');
    Route::get('/{screenshot:id}/og', RawScreenshotController::class)
        ->name('screenshot.opengraph');
});

Route::get('/', HomepageController::class)
    ->name('home');
Route::get('/about', AboutController::class)
    ->name('about');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
