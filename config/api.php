<?php

// Единый источник лимитов и локалей HTTP API.
// Значения публикуются через GET /api/meta: фронтенд читает их оттуда и не
// хардкодит числа у себя.
return [
    'limits' => [
        // Максимум id треков в одном массовом обновлении меток (/tracks/batch-tags).
        'batch_tracks_max' => 200,
        // Максимум треков в одном запросе классификации ИИ.
        'ai_tracks_max' => 10,
        // Размер страницы списков (/api/tracks, /api/tags).
        'tracks_per_page' => 30,
        'tags_per_page' => 50,
        // Максимальные длины строк во входных данных.
        'search_max_length' => 200,
        'tag_name_max_length' => 80,
        'tag_name_ru_max_length' => 120,
    ],
    'locales' => [
        'default' => 'ru',
        'available' => ['ru'],
    ],
];
