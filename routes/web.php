<?php

use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackController::class, 'index'])->name('tracks.index');
Route::post('/tags', [TrackController::class, 'storeTag'])->name('tags.store');
Route::put('/tracks/{track}/tags', [TrackController::class, 'update'])->name('tracks.tags.update');
