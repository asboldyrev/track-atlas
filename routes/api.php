<?php

use App\Http\Controllers\Api\MetaApiController;
use App\Http\Controllers\Api\TagApiController;
use App\Http\Controllers\Api\TrackApiController;
use Illuminate\Support\Facades\Route;

// Только чтение. Мутации до переключения фронтенда продолжают жить на web-маршрутах
// (/tags, /tracks/{track}/tags, /tracks/batch-tags) и удаляются отдельной задачей.
Route::get('/tracks', [TrackApiController::class, 'index'])->name('api.tracks.index');
Route::get('/tags', [TagApiController::class, 'index'])->name('api.tags.index');
Route::get('/meta', [MetaApiController::class, 'show'])->name('api.meta.show');
