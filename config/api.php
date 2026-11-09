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
        // Допустимые размеры страницы каталога (per_page). Белый список, а не
        // произвольное число: иначе один запрос выгрузит всю библиотеку.
        'tracks_per_page_options' => [10, 20, 30, 50, 100],
        // Допустимые поля сортировки каталога → колонки tracks. Ключи — то, что
        // приходит в ?sort=; значения — только известные колонки, поэтому
        // пользовательская строка никогда не попадает в ORDER BY.
        'tracks_sort_columns' => [
            'artist' => 'artist',
            'title' => 'title',
            'album' => 'album',
        ],
        'tracks_sort_directions' => ['asc', 'desc'],
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
