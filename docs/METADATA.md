# Чтение аудиотегов — итерация 2

Ветка `feat/audio-metadata` расширяет первую итерацию сканера.

- getID3 читает embedded tags из MP3, FLAC, M4A и OGG; при отсутствии/ошибке используются имена файлов.
- При наличии берутся `title`, `artist`, `album` и `genre`. Несколько жанров можно хранить для одного трека.
- Импорт жанров **только добавляет** связи с `source=file`; ручные связи не удаляются. Обратная синхронизация удалённых из файла жанров пока отсутствует.
- Для уже проиндексированных файлов, у которых не менялись size и mtime, нужен `php artisan music:scan --refresh`: в обычном режиме они пропускаются.
- Сканер остаётся рекурсивным, оригинальные файлы не изменяются.
- `composer.lock` необходимо обновить после установки зависимости `james-heinrich/getid3`, а затем закоммитить. Окончательная проверка выполняется локально.

Проверка:
```bash
composer update james-heinrich/getid3 --with-all-dependencies
php artisan optimize:clear
php artisan migrate
php artisan test --filter=MusicCatalogTest
php artisan music:scan --refresh
```

Внешние API, OpenRouter, Python и Docker не используются.
