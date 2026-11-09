<?php

use App\Http\Controllers\AiClassificationController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

// Страницы-оболочки: один Blade-шаблон с точкой монтирования Vue.
Route::get('/', [TrackController::class, 'index'])->name('tracks.index');
Route::get('/categories', [TrackController::class, 'index'])->name('categories.index');
Route::get('/tagging', [TrackController::class, 'index'])->name('tagging.index');

// Мутации: чтение каталога переехало в /api/*, а изменения остаются на
// web-маршрутах под CSRF-защитой страницы.
Route::post('/tags', [TrackController::class, 'storeTag'])->name('tags.store');
Route::put('/tags/{tag}/translation', [TrackController::class, 'updateTranslation'])->name('tags.translation.update');
Route::put('/tracks/{track}/tags', [TrackController::class, 'update'])->name('tracks.tags.update');
Route::post('/tracks/batch-tags', [TrackController::class, 'batchTags'])->name('tracks.tags.batch');

Route::post('/ai/classify', [AiClassificationController::class, 'classify'])->name('ai.classify');
Route::put('/ai/suggestions/{suggestion}', [AiClassificationController::class, 'decide'])->name('ai.suggestions.decide');
