<?php

use App\Http\Controllers\Api\UploadScreenshotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function (): void {
    Route::post('screenshot/upload', UploadScreenshotController::class)
        ->name('screenshot.upload');
    //->middleware('auth:sanctum');
});
