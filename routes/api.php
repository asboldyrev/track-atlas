<?php

use App\Http\Controllers\Api\MetaApiController;
use App\Http\Controllers\Api\TagApiController;
use App\Http\Controllers\Api\TrackApiController;
use Illuminate\Support\Facades\Route;

// Чтение каталога. Старый web-маршрут чтения удалён: фронтенд ходит только сюда.
// Мутации остаются на web-маршрутах (/tags, /tracks/{track}/tags,
// /tracks/batch-tags, /ai/*) под CSRF-защитой страницы.
Route::get('/tracks', [TrackApiController::class, 'index'])->name('api.tracks.index');
Route::get('/tags', [TagApiController::class, 'index'])->name('api.tags.index');
Route::get('/meta', [MetaApiController::class, 'show'])->name('api.meta.show');
