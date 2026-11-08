<?php

use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackController::class, 'index'])->name('tracks.index');
Route::get('/catalog', [TrackController::class, 'catalog'])->name('tracks.catalog');
Route::post('/tags', [TrackController::class, 'storeTag'])->name('tags.store');
Route::put('/tracks/{track}/tags', [TrackController::class, 'update'])->name('tracks.tags.update');
Route::post('/tracks/batch-tags', [TrackController::class, 'batchTags'])->name('tracks.tags.batch');
