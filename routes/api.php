<?php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Api\V1\Admin;
use Illuminate\Support\Facades\Route;

/*
| JSON API v1, consumed by the iOS app and the SSH TUI. Contract: docs/api.md
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // --- Public ---------------------------------------------------------
    Route::middleware('throttle:api')->group(function () {
        Route::get('site', [V1\PublicController::class, 'site'])->name('site');
        Route::get('about', [V1\PublicController::class, 'about'])->name('about');

        Route::get('posts', [V1\PostController::class, 'index'])->name('posts.index');
        Route::get('posts/{slug}', [V1\PostController::class, 'show'])->name('posts.show');
        Route::get('blog/categories', [V1\PostController::class, 'categories'])->name('blog.categories');

        Route::get('projects', [V1\ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{slug}', [V1\ProjectController::class, 'show'])->name('projects.show');

        Route::get('photos', [V1\PhotoController::class, 'index'])->name('photos.index');
        Route::get('photos/{slug}', [V1\PhotoController::class, 'show'])->name('photos.show');
        Route::get('photo-categories', [V1\PhotoController::class, 'categories'])->name('photo-categories');
    });

    Route::post('prints/checkout', [V1\PrintController::class, 'checkout'])->middleware('throttle:10,1')->name('prints.checkout');
    Route::post('contact', [V1\PublicController::class, 'contact'])->middleware('throttle:5,1')->name('contact');

    // --- Auth -------------------------------------------------------------
    Route::post('auth/token', [V1\AuthController::class, 'token'])->middleware('throttle:6,1')->name('auth.token');

    Route::middleware(['auth:sanctum', 'abilities:admin', 'can:admin'])->group(function () {
        Route::get('auth/me', [V1\AuthController::class, 'me'])->name('auth.me');
        Route::delete('auth/token', [V1\AuthController::class, 'revoke'])->name('auth.revoke');

        // --- Admin ----------------------------------------------------------
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
            Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');

            Route::get('posts', [Admin\PostController::class, 'index'])->name('posts.index');
            Route::post('posts', [Admin\PostController::class, 'store'])->name('posts.store');
            Route::get('posts/{post:id}', [Admin\PostController::class, 'show'])->name('posts.show');
            Route::patch('posts/{post:id}', [Admin\PostController::class, 'update'])->name('posts.update');
            Route::delete('posts/{post:id}', [Admin\PostController::class, 'destroy'])->name('posts.destroy');

            Route::get('blog-categories', [Admin\TaxonomyController::class, 'blogCategories'])->name('blog-categories');
            Route::get('blog-series', [Admin\TaxonomyController::class, 'blogSeries'])->name('blog-series');
            Route::get('photo-categories', [Admin\TaxonomyController::class, 'photoCategories'])->name('photo-categories');
            Route::get('tags', [Admin\TaxonomyController::class, 'tags'])->name('tags');

            Route::get('photos', [Admin\PhotoController::class, 'index'])->name('photos.index');
            Route::post('photos', [Admin\PhotoController::class, 'store'])->name('photos.store');
            Route::get('photos/{photo:id}', [Admin\PhotoController::class, 'show'])->name('photos.show');
            Route::patch('photos/{photo:id}', [Admin\PhotoController::class, 'update'])->name('photos.update');
            Route::delete('photos/{photo:id}', [Admin\PhotoController::class, 'destroy'])->name('photos.destroy');

            Route::get('social/accounts', [Admin\SocialController::class, 'accounts'])->name('social.accounts');
            Route::get('social/calendar', [Admin\SocialController::class, 'calendar'])->name('social.calendar');
            Route::get('social/posts', [Admin\SocialController::class, 'index'])->name('social.posts.index');
            Route::post('social/posts', [Admin\SocialController::class, 'store'])->name('social.posts.store');
            Route::get('social/posts/{post}', [Admin\SocialController::class, 'show'])->name('social.posts.show');
            Route::patch('social/posts/{post}', [Admin\SocialController::class, 'update'])->name('social.posts.update');
            Route::delete('social/posts/{post}', [Admin\SocialController::class, 'destroy'])->name('social.posts.destroy');
            Route::post('social/posts/{post}/publish', [Admin\SocialController::class, 'publish'])->name('social.posts.publish');

            Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        });
    });
});
