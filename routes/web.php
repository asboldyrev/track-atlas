<?php

use App\Http\Controllers\TrackController;
use App\Http\Controllers\AiClassificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackController::class, 'index'])->name('tracks.index');
Route::get('/catalog', [TrackController::class, 'catalog'])->name('tracks.catalog');
Route::post('/tags', [TrackController::class, 'storeTag'])->name('tags.store');
Route::put('/tags/{tag}/translation', [TrackController::class, 'updateTranslation'])->name('tags.translation.update');
Route::put('/tracks/{track}/tags', [TrackController::class, 'update'])->name('tracks.tags.update');
Route::post('/tracks/batch-tags', [TrackController::class, 'batchTags'])->name('tracks.tags.batch');

Route::post('/ai/classify', [AiClassificationController::class, 'classify'])->name('ai.classify');
Route::put('/ai/suggestions/{suggestion}', [AiClassificationController::class, 'decide'])->name('ai.suggestions.decide');
