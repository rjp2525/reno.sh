<?php

use App\Http\Controllers\About\HobbiesController;
use App\Http\Controllers\About\PersonalController;
use App\Http\Controllers\About\ProfessionalController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\BlogSeriesController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\PhotoShowController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\ProjectShowController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomepageController::class)
    ->name('home');

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

Route::get('/gallery', GalleryController::class)
    ->name('gallery');
Route::get('/gallery/{photo:slug}', PhotoShowController::class)
    ->name('gallery.show');

Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', BlogController::class)->name('index');
    Route::get('/series/{series:slug}', BlogSeriesController::class)->name('series.show');
    Route::get('/post/{post:slug}', [BlogPostController::class, 'uncategorized'])->name('show.uncategorized');
    Route::get('/{category:slug}', BlogController::class)->name('category');
    Route::get('/{category:slug}/{post:slug}', BlogPostController::class)->name('show');
});

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send')
    ->middleware('throttle:5,1');

Route::feeds();

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
